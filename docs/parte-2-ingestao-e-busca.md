# Ingestão e busca: o edital dentro do banco

> **RAG na prática · Parte 2 de 3**
>
> Na parte anterior o ambiente ficou de pé e o texto do PDF saiu limpo no terminal.
> Agora esse texto vai para o banco em pedaços, com os vetores que o Laravel AI SDK gera,
> e a primeira busca semântica começa a funcionar. Ainda sem modelo generativo no meio.

Vale relembrar onde estamos. O sistema precisa responder quais documentos um edital exige,
e para isso o modelo de linguagem vai precisar ler os trechos certos do edital. Essa parte
do trabalho não tem nada de inteligência artificial: é recortar o documento, transformar
cada recorte em um vetor e guardar no banco de um jeito que permita achar o recorte certo
depois. É a parte que sustenta todo o resto, e também a que costuma ser mal explicada.

A boa notícia é que o Laravel AI SDK resolve a parte dos vetores em uma linha. O resto é
PHP comum.

## Os dois models

Nada de especial no `Document`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    protected $fillable = [
        'original_name',
        'storage_path',
        'status',
        'page_count',
        'extracted_text',
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }
}
```

O `DocumentChunk` tem a única linha interessante, que é o cast:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    protected $fillable = [
        'document_id',
        'source',
        'clause',
        'page',
        'content',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => AsVector::class,
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
```

O `AsVector` faz a ponte entre o array de floats que você tem no PHP e o tipo `vector` do
Postgres. Sem ele você ficaria montando string no formato que o pgvector espera, o que
funciona e é chato. Com ele, você atribui um array e pronto.

O campo `page` já faz parte do `$fillable`, porque será gravado junto com cada pedaço.

## O comando de ingestão

Um comando só, que faz tudo: extrai, corta, pede os embeddings e grava.

```bash
docker compose exec app php artisan make:command IngestEdital
```

Vamos por partes. Primeiro a extração, igual à da parte anterior, só que no documento
inteiro em vez de três páginas:

```php
$result = Process::timeout(120)->run(['pdftotext', '-layout', $path, '-']);

if (! $result->successful()) {
    $this->error($result->errorOutput());

    return self::FAILURE;
}

$text = $result->output();
```

Repare que não tem `trim()` no `$text`. Isso é de propósito, e o motivo aparece na próxima
seção.

## Como saber a página de cada pedaço

Quando o sistema disser que a certidão negativa de falência está exigida, você vai querer
conferir no PDF. Então cada pedaço precisa carregar a página de onde saiu.

O caminho óbvio seria ler o rodapé do documento, que traz o número da página. Foi o que eu
ia fazer, até olhar a saída crua da parte anterior e ver que o rodapé sai assim:

```
P á g i n a 12 | 48
```

O Word aplicou espaçamento entre as letras e o `pdftotext` devolve esse espaçamento como
espaço de verdade. Uma expressão regular escrita para a palavra `Página` não casaria com
nada, e falharia em silêncio.

Existe um caminho melhor e que não depende do documento. O `pdftotext` insere um caractere
de form feed, o `\f`, entre uma página e outra. Ele está sempre lá, em qualquer PDF, seja
o rodapé bonito ou inexistente. Então quebrar o texto nesse caractere entrega as páginas
em ordem, e o índice do array é o número da página. Algumas versões também encerram a
saída com form feed; nesse caso, o `explode` cria um elemento vazio que precisa sair antes
da contagem:

```php
$pages = explode("\f", $text);

while ($pages !== [] && trim($pages[array_key_last($pages)]) === '') {
    array_pop($pages);
}

foreach ($pages as $index => $pageText) {
    $page = $index + 1;

    foreach ($this->splitPage($pageText) as $content) {
        $chunks[] = [
            'content' => $content,
            'page' => $page,
        ];
    }
}
```

O texto completo não passa por `trim()` antes da separação. Só os elementos vazios do fim
são removidos depois do `explode`, e a contagem correta vem de `count($pages)`.

Esse arranjo tem um efeito colateral que vale assumir de forma explícita. Como o corte
acontece dentro de cada página, um trecho que atravessa a virada de página fica partido em
dois. Perde-se um pouco de continuidade e ganha-se a citação, e para um sistema em que
conferir importa mais que ler bonito, é uma troca que vale.

## O corte, deliberadamente burro

Aqui cabe uma escolha de escopo. Dá para cortar o edital por cláusula, aproveitando que a
numeração vem sempre no começo da linha, no formato `7.1.1.`. Fica melhor, e fica mais
código. Como o objetivo aqui é mostrar o SDK e entender RAG, o corte vai ser o mais simples
que funciona: acumula parágrafos até encher um tamanho fixo e repete o final do pedaço
anterior como sobreposição.

```php
private const CHUNK_SIZE = 1200;

private const CHUNK_OVERLAP = 200;

private function splitPage(string $pageText): array
{
    $paragraphs = preg_split('/\n\s*\n/', $pageText, flags: PREG_SPLIT_NO_EMPTY) ?: [];

    $chunks = [];
    $current = '';

    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);

        if ($paragraph === '') {
            continue;
        }

        if (mb_strlen($current) + mb_strlen($paragraph) > self::CHUNK_SIZE && $current !== '') {
            $chunks[] = trim($current);
            $current = mb_substr($current, -self::CHUNK_OVERLAP)."\n\n";
        }

        $current .= $paragraph."\n\n";
    }

    if (trim($current) !== '') {
        $chunks[] = trim($current);
    }

    return $chunks;
}
```

A sobreposição merece uma palavra, porque é o parâmetro que mais confunde. Se você cortar
o texto em pedaços justapostos, sem repetição, uma exigência que caia exatamente na
fronteira vira duas metades sem sentido, e nenhuma das duas responde à pergunta. Repetir
os últimos caracteres do pedaço anterior no começo do seguinte resolve isso na maior parte
dos casos. O custo é redundância, que é barata.

## A parte que é o SDK

Chegamos ao ponto do artigo. Gerar os embeddings é isto:

```php
use Laravel\Ai\Embeddings;

$response = Embeddings::for($texts)->generate();

$response->embeddings; // [[0.12, -0.04, ...], [0.98, 0.31, ...], ...]
```

Você passa uma lista de strings e recebe uma lista de vetores na mesma ordem. Nada de
cliente HTTP, nada de montar payload, nada de tratar resposta. O modelo e as dimensões
vêm do `config/ai.php`, então trocar de provedor depois é mudar configuração, não código.

Em lotes, para não estourar o limite de uma requisição só, e já gravando:

```php
foreach (array_chunk($chunks, 32) as $batch) {
    $response = retry(
        3,
        fn () => Embeddings::for(array_column($batch, 'content'))
            ->timeout(120)
            ->cache()
            ->generate(),
        sleepMilliseconds: 2000,
    );

    foreach ($batch as $index => $chunk) {
        $document->chunks()->create([
            'content' => $chunk['content'],
            'page' => $chunk['page'],
            'embedding' => $response->embeddings[$index],
        ]);
    }

}
```

Vale mencionar que o SDK também sabe cachear embeddings, o que ajuda quando você vai
reprocessar o mesmo documento várias vezes ajustando o tamanho do pedaço. Basta ligar
`ai.caching.embeddings.cache` na configuração, ou chamar `->cache()` na requisição. O
cache usa provedor, modelo, dimensões e conteúdo como chave, então trocar qualquer um
desses gera vetor novo.

## Rodando

```bash
docker compose exec app php artisan edital:ingest storage/app/edital.pdf
```

No edital de Vargem Bonita, 48 páginas, o resultado foram 117 pedaços. Levou poucos
segundos e custou alguns centavos.

## A busca, sem nenhuma IA generativa

Agora a parte que mais ensina, e que quase todo tutorial atropela. Antes de colocar um
modelo de linguagem na história, vale olhar a recuperação sozinha, porque é ela que
determina o teto da qualidade de todo o resto. Se o trecho certo não for recuperado,
nenhum modelo vai adivinhar o conteúdo dele.

```php
$chunks = DocumentChunk::query()
    ->where('document_id', $document->id)
    ->whereVectorSimilarTo('embedding', $query, minSimilarity: 0.2)
    ->limit((int) $this->option('limit'))
    ->get();
```

A primeira condição impede que uma nova ingestão misture trechos de editais diferentes.
O `$query` é a pergunta em texto puro; o Laravel gera o embedding, filtra pela similaridade
mínima e já ordena o resultado, sem precisar de `orderBy`.

O `minSimilarity` vai de 0 a 1, e é o parâmetro que você vai mexer mais. Alto demais e a
busca não devolve nada. Baixo demais e devolve ruído. Num sistema de edital, onde deixar
uma exigência passar custa a licitação, o erro menos grave é o do ruído, então o valor
fica baixo de propósito.

```bash
docker compose exec app php artisan edital:search "documentos exigidos para habilitação"
```

## O que a saída real mostrou

E aqui o edital de verdade dá uma aula que documento de exemplo nunca daria.

O primeiro resultado, o mais similar de todos, foi o item 7 do edital, cujo título é
exatamente HABILITAÇÃO. Faz todo sentido para um modelo de embeddings: a pergunta era
sobre documentos de habilitação e esse trecho é o que mais fala sobre habilitação no
documento inteiro. O problema é que o item 7 não lista documento nenhum. Ele diz assim:

> Os documentos previstos no ANEXO II deste edital, necessários e suficientes para
> demonstrar a capacidade do licitante de realizar o objeto da licitação, serão exigidos
> para fins de habilitação.

Ou seja, a busca acertou o trecho mais parecido com a pergunta e entregou uma resposta
correta e completamente inútil. A lista concreta, com CNPJ, certidões, FGTS e CNDT, está
no Anexo II, que apareceu só no quarto lugar, na página 38.

Essa é a diferença entre parecido e útil, e é o limite fundamental da busca por
similaridade pura. O vetor mede semelhança de assunto, não presença de resposta.

Também apareceu um pedaço quase vazio, com um fragmento de frase cortado no meio de uma
palavra seguido do rodapé da página. É a sobreposição funcionando literalmente: ela pega
os últimos duzentos caracteres sem olhar onde termina a palavra. Some com a limpeza de
cabeçalho e rodapé, que a gente deixou de fora de propósito, e o resultado é um pedaço
que só ocupa lugar no ranking.

Nada disso é motivo para consertar agora. É material para o último artigo, quando a gente
volta com a lista de melhorias e mede quanto cada uma vale.

## O que ficou de fora

De propósito, e vale listar para você saber que existe: corte por cláusula em vez de
tamanho fixo, busca por palavra-chave combinada com a vetorial, limpeza de cabeçalho e
rodapé, deduplicação de pedaços repetidos, ingestão em fila e OCR para editais
digitalizados.

Cada um desses melhora o resultado, e nenhum é necessário para entender o mecanismo. O
último artigo da série volta nessa lista.

## Na próxima parte

O banco já tem o edital vetorizado e a busca já encontra os trechos certos, com a ressalva
de que o mais parecido não é o mais útil. Falta transformar isso em resposta. Na Parte 3
entra o agente do SDK com saída estruturada, que recebe os trechos recuperados e devolve
a lista de documentos em formato de checklist, mais uma tela simples para usar sem
terminal.

---

## Referências

LARAVEL. **Laravel AI SDK**. Documentação oficial, versão 13.x. Disponível em: https://laravel.com/framework/docs/ai-sdk. Acesso em: 18 set. 2026.

MUNICÍPIO DE VARGEM BONITA. **Pregão Eletrônico nº 006/2026: contratação de serviços de arbitragem esportiva**. Processo Administrativo nº 012/2026. Vargem Bonita, SC, 2 fev. 2026. Disponível em: https://vargembonita.sc.gov.br/uploads/sites/93/2026/02/PL012.2026-PE006.2026-ARBITRAGEM.pdf. Acesso em: 18 set. 2026.
