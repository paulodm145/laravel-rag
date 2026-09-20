# Lendo um edital de licitação sem ler o edital inteiro

> **RAG na prática · Parte 1 de 3**
>
> Vamos construir, do zero e dentro do Laravel, um sistema que lê um edital em PDF e devolve a lista de documentos que ele exige. Nesta primeira parte: ambiente em Docker, banco preparado para busca vetorial e a primeira extração do texto.

RAG costuma aparecer em exemplos que escondem justamente a parte mais importante: como um documento real sai do disco, vira trechos pesquisáveis e chega a um modelo de linguagem com contexto suficiente para produzir uma resposta verificável. Nesta série vamos montar esse caminho inteiro com Laravel, sem pular da teoria direto para uma chamada de API.

O projeto usa o Laravel AI SDK para gerar embeddings, fazer busca vetorial e executar um agente com saída estruturada. O PostgreSQL, com pgvector, guarda e recupera os trechos; no final, uma interface em React apresenta o resultado. A proposta é entender o papel de cada etapa e enxergar onde a qualidade de um RAG pode se perder.

Para não construir o tutorial sobre frases artificiais, vamos trabalhar com um documento público de 48 páginas: o [edital do Pregão Eletrônico nº 006/2026 de Vargem Bonita/SC](https://vargembonita.sc.gov.br/uploads/sites/93/2026/02/PL012.2026-PE006.2026-ARBITRAGEM.pdf). Você pode baixar o PDF agora e usar o mesmo arquivo durante toda a série.

A pergunta escolhida para testar o pipeline é objetiva: quais documentos de habilitação esse edital exige? A resposta está distribuída entre o corpo do documento e os anexos, o que nos obriga a lidar com recuperação, contexto, citações e contradições. O sistema deverá devolver cada documento acompanhado do trecho e da página que sustentam a resposta.

A série está dividida em três partes:

1. preparar o ambiente, o PostgreSQL com pgvector e a extração do PDF;
2. cortar o texto, gerar embeddings e testar a busca por similaridade;
3. entregar a busca a um agente, validar a resposta com um schema e construir a interface.

Ao final, teremos um RAG pequeno o bastante para ser estudado arquivo por arquivo, mas completo o bastante para expor problemas que exemplos prontos raramente mostram: página calculada de forma errada, dado descartado pelo Eloquent, recuperação de um trecho parecido mas inútil e timeout apresentado como falha de conexão.

## O que vamos usar

O Laravel AI SDK, pacote oficial que unifica o acesso aos provedores de IA. O PostgreSQL com a extensão pgvector, que o Laravel já suporta nativamente com uma coluna `vector` e métodos de busca por similaridade no query builder. A OpenAI como provedor. E Docker para tudo isso rodar igual na sua máquina e na minha.

O edital que vamos usar é o Pregão Eletrônico 006/2026 do município de Vargem Bonita, em Santa Catarina, para contratação de serviços de arbitragem esportiva. São 48 páginas, sob a Lei 14.133/2021, com termo de referência, estudo técnico preliminar e quatro anexos. O [PDF pode ser baixado diretamente no site da prefeitura](https://vargembonita.sc.gov.br/uploads/sites/93/2026/02/PL012.2026-PE006.2026-ARBITRAGEM.pdf).

> **Antes de começar**
>
> O Laravel AI SDK é recente. Confira a versão do pacote e do framework antes de copiar os comandos, porque a API pode ter mudado desde a publicação deste texto.

## Parte 1: ambiente e fundação

O objetivo desta primeira parte é modesto e bem definido: deixar o ambiente rodando, o banco preparado para receber vetores e conseguir extrair o texto do PDF. Nada de embedding, nada de modelo de linguagem ainda. Só a fundação.

### Subindo o ambiente com Docker

Três coisas precisam existir: PHP com as extensões do PostgreSQL, o banco com pgvector já instalado, e o `pdftotext` para extrair o conteúdo do PDF. O Docker resolve as três de uma vez e evita a conversa chata de instalar pgvector na mão.

Crie a pasta do projeto e dentro dela um arquivo `docker/php/Dockerfile`:

```dockerfile
FROM php:8.4-cli-bookworm

RUN apt-get update && apt-get install -y \
        git \
        unzip \
        curl \
        ca-certificates \
        libzip-dev \
        libpq-dev \
        libicu-dev \
        poppler-utils \
    && docker-php-ext-install pdo_pgsql pgsql zip intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

EXPOSE 8000 5173
```

O pacote que importa aqui é o `poppler-utils`. É ele que traz o `pdftotext`, a ferramenta de linha de comando que vai extrair o texto do edital. O Node entra porque o Vite vai precisar dele quando chegarmos na interface.

Agora o `docker-compose.yml` na raiz:

```yaml
services:
  app:
    build: ./docker/php
    working_dir: /var/www
    volumes:
      - .:/var/www
    ports:
      - "8000:8000"
      - "5173:5173"
    command: php artisan serve --host=0.0.0.0 --port=8000
    depends_on:
      - db
      - redis

  db:
    image: pgvector/pgvector:pg17
    environment:
      POSTGRES_DB: edital
      POSTGRES_USER: edital
      POSTGRES_PASSWORD: secret
    ports:
      - "5432:5432"
    volumes:
      - pgdata:/var/lib/postgresql/data

  redis:
    image: redis:7-alpine

volumes:
  pgdata:
```

Repare na imagem do banco. Em vez do `postgres` oficial, usamos `pgvector/pgvector:pg17`, que é o Postgres com a extensão já compilada dentro. Economiza uma dor de cabeça inteira.

Neste projeto de estudo a ingestão será síncrona. Uma fila seria a escolha natural em produção, mas esconderia parte do fluxo que queremos observar.

### Criando o projeto Laravel

Se você tem o instalador do Laravel na máquina, o caminho mais curto é:

```bash
laravel new edital-rag --react
```

Se não tem PHP instalado localmente, dá para criar o projeto usando um container descartável:

```bash
docker run --rm -it -v "$PWD":/app -w /app composer:2 \
    composer create-project laravel/laravel edital-rag
```

Neste segundo caso o projeto vem sem React, que será adicionado na Parte 3, quando a interface entrar em cena. Para o que vamos fazer agora, backend puro, não faz diferença.

Mova os arquivos do Docker para dentro da pasta do projeto e suba o ambiente:

```bash
docker compose build
docker compose up -d
```

### Configurando a conexão

No `.env`, aponte o banco para o serviço do compose:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=edital
DB_USERNAME=edital
DB_PASSWORD=secret

OPENAI_API_KEY=sua-chave-aqui
```

O nome do host é `db` e não `localhost` porque, de dentro do container da aplicação, o banco é outro container. Esse é o erro número um de quem está começando com Docker, e vale a pena dizer em voz alta.

### Instalando o AI SDK

Todos os comandos daqui para frente rodam dentro do container:

```bash
docker compose exec app composer require laravel/ai

docker compose exec app php artisan vendor:publish \
    --provider="Laravel\Ai\AiServiceProvider"
```

O `vendor:publish` cria o `config/ai.php` e traz as migrations do pacote, que montam as tabelas de conversa que o SDK usa para guardar histórico. Não vamos precisar delas neste projeto, mas deixe rodar, custa nada.

No `config/ai.php` vale conferir o modelo padrão de embeddings. Vamos usar o `text-embedding-3-small` da OpenAI, que gera vetores de 1536 dimensões e é barato o bastante para você reprocessar o mesmo edital dez vezes enquanto ajusta o corte dos pedaços sem sentir no cartão.

### Modelando o banco

Duas tabelas. Uma para o documento, outra para os pedaços dele.

```bash
docker compose exec app php artisan make:migration create_documents_table
docker compose exec app php artisan make:migration create_document_chunks_table
```

A primeira é simples:

```php
Schema::create('documents', function (Blueprint $table) {
    $table->id();
    $table->string('original_name');
    $table->string('storage_path');
    $table->string('status')->default('pending');
    $table->unsignedInteger('page_count')->nullable();
    $table->longText('extracted_text')->nullable();
    $table->timestamps();
});
```

O campo `extracted_text` guarda o texto que saiu do PDF. Guardar isso no banco parece desperdício, mas salva muito tempo: quando a busca devolver resultado estranho, você vai querer olhar exatamente o texto que foi indexado, sem reprocessar o arquivo.

A segunda tabela é onde mora a parte interessante:

```php
use Illuminate\Support\Facades\DB;

Schema::ensureVectorExtensionExists();

Schema::create('document_chunks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('document_id')->constrained()->cascadeOnDelete();
    $table->string('source')->nullable();
    $table->string('clause')->nullable();
    $table->unsignedInteger('page')->nullable();
    $table->text('content');
    $table->vector('embedding', dimensions: 1536)->nullable()->index();
    $table->timestamps();
});

DB::statement("
    ALTER TABLE document_chunks
    ADD COLUMN content_tsv tsvector
    GENERATED ALWAYS AS (to_tsvector('portuguese', content)) STORED
");

DB::statement("
    CREATE INDEX document_chunks_content_tsv_idx
    ON document_chunks USING GIN (content_tsv)
");
```

Três coisas para comentar aqui.

O `Schema::ensureVectorExtensionExists()` roda o `CREATE EXTENSION IF NOT EXISTS vector` para você. Precisa vir antes de qualquer coluna vetorial.

O método `vector()` com `index()` cria a coluna e um índice HNSW com distância de cosseno. Num documento só o índice não faz diferença de performance, porque o volume é pequeno demais. Ele está aí porque o dia que você indexar cem editais vai fazer.

A coluna `content_tsv` não tem equivalente no Blueprint do Laravel, por isso o SQL cru. Ela é uma coluna gerada: o Postgres calcula o `tsvector` automaticamente a partir do `content`, toda vez que a linha muda. Você nunca escreve nela. É ela que vai sustentar a busca por palavra-chave mais adiante, e é o que vai salvar o sistema quando alguém procurar por CNDT ou por "art. 68" e o embedding não der conta.

Os campos `source`, `clause` e `page` ficam nulos por enquanto. São eles que vão permitir a citação, e a gente preenche na Parte 2, quando começar a cortar o documento em pedaços.

Rode as migrations:

```bash
docker compose exec app php artisan migrate
```

### O primeiro teste de verdade: extrair o texto

Antes de pensar em embedding, vetor ou modelo de linguagem, tem uma pergunta que precisa ser respondida: esse PDF é legível por máquina. Muito edital de município pequeno é digitalizado, ou seja, é uma foto de papel dentro de um arquivo PDF. Nesse caso a extração devolve string vazia e o projeto inteiro para antes de começar.

Vamos descobrir isso já. Crie o comando:

```bash
docker compose exec app php artisan make:command ExtractPdfText
```

E o conteúdo:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class ExtractPdfText extends Command
{
    protected $signature = 'edital:extract {path} {--pages=1}';

    protected $description = 'Extract raw text from a PDF file for inspection';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $info = Process::run(['pdfinfo', $path]);
        $this->line($info->output());

        $result = Process::run([
            'pdftotext',
            '-layout',
            '-f', '1',
            '-l', (string) $this->option('pages'),
            $path,
            '-',
        ]);

        if (! $result->successful()) {
            $this->error($result->errorOutput());

            return self::FAILURE;
        }

        $text = trim($result->output());

        if ($text === '') {
            $this->error(
                'No text extracted. This PDF is probably scanned and would need OCR.'
            );

            return self::FAILURE;
        }

        $this->newLine();
        $this->line($text);

        return self::SUCCESS;
    }
}
```

Baixe o edital, coloque na pasta `storage/app/` do projeto e rode:

```bash
docker compose exec app php artisan edital:extract storage/app/edital.pdf --pages=3
```

A flag `-layout` pede ao `pdftotext` que preserve o alinhamento das colunas. Sem ela, qualquer tabela vira uma sopa de números embaralhados. Com ela, a tabela pelo menos continua parecendo uma tabela, o que vai importar quando chegarmos no corte dos pedaços.

O `pdfinfo` no começo do comando não é enfeite. Ele mostra a contagem de páginas, que a gente vai usar depois, e já dá uma pista sobre o arquivo: se o PDF tiver sido gerado por um scanner, isso normalmente aparece ali nos metadados do produtor.

### O que você deve ver

Rodando nas três primeiras páginas do edital de Vargem Bonita, o resultado traz o cabeçalho com dados do município, o objeto da contratação, o valor total, a data da sessão pública, e o início das seções numeradas: item 1 sobre o objeto, item 2 sobre credenciamento e participação.

Se o texto aparecer, o projeto está de pé. É só isso que a Parte 1 precisa provar.

Vale ler o cabeçalho do `pdfinfo` com atenção, porque ele antecipa a qualidade do que
vem. Neste edital o campo `Creator` diz `Acrobat PDFMaker 25 para Word`, ou seja, o
documento nasceu digital, foi escrito no Word e exportado. Por isso a extração sai
limpa. Quando esse campo aponta um scanner, ou vem vazio num arquivo grande, é sinal de
que o PDF é imagem e vai precisar de OCR.

Outra coisa que aparece já nessas três páginas: a numeração das cláusulas vem sempre no
começo da linha, no formato `2.5.1.`, e os títulos de seção vêm em caixa alta. Guarde
isso, porque é o que vai permitir cortar o documento por cláusula na próxima parte em
vez de cortar por número de caracteres.

Vale dar uma olhada rápida no que veio junto. O cabeçalho e o rodapé se repetem em todas as páginas, com nome do município, número da página, endereço e CNPJ. Existe uma discussão longa sobre limpar esse tipo de ruído antes de indexar, e ela é legítima, mas não agora: fazer o pipeline funcionar de ponta a ponta vale mais do que refiná-lo cedo. Vamos deixar essa e outras melhorias para o fim da série, quando já houver um sistema completo para comparar antes e depois.

Mas presta atenção em uma coisa que o rodapé traz: o número da página, de graça, em todo
lugar do documento. Vamos aproveitar isso na Parte 2, e assim cada trecho indexado carrega
a página de origem sem esforço nenhum. Quando o sistema disser que a certidão negativa de
falência está exigida, ele vai poder dizer também em que página, e você abre o PDF e
confere em cinco segundos.

E aqui vai o primeiro aprendizado que só a saída real ensina. O rodapé não sai como
`Página 3 | 48`, como seria de esperar. Sai assim:

```
P á g i n a 3 | 48
```

O Word aplicou espaçamento entre as letras da palavra, e o `pdftotext` devolve esse
espaçamento como espaço de verdade. Uma expressão regular escrita para `Página` não
casaria com nada, e o pior é que ela falharia em silêncio: você acabaria com a página
nula em todos os trechos e só descobriria o motivo muito depois. É o tipo de detalhe que
não aparece em tutorial com documento de exemplo, e a lição geral é essa: olhe a saída
crua antes de escrever qualquer regra sobre ela.

## Na próxima parte

Essa é a fundação: ambiente rodando, banco preparado para vetor e para busca textual, e a certeza de que o PDF é legível. Na Parte 2 a gente resolve o problema mais difícil do projeto, que é transformar 48 páginas em pedaços que façam sentido sozinhos. É lá que o edital começa a mostrar as manhas dele.

---

## Referências

LARAVEL. **Laravel AI SDK**. Documentação oficial, versão 13.x. Disponível em: https://laravel.com/framework/docs/ai-sdk. Acesso em: 18 set. 2026.

MUNICÍPIO DE VARGEM BONITA. **Pregão Eletrônico nº 006/2026: contratação de serviços de arbitragem esportiva**. Processo Administrativo nº 012/2026. Vargem Bonita, SC, 2 fev. 2026. Disponível em: https://vargembonita.sc.gov.br/uploads/sites/93/2026/02/PL012.2026-PE006.2026-ARBITRAGEM.pdf. Acesso em: 18 set. 2026.

---

## Anotações de trabalho (não publicar)

Rascunho da série, para organizar a sequência dos próximos textos.

### Plano da série

| Parte | Escopo | Status |
| --- | --- | --- |
| 1 | Docker, projeto Laravel, AI SDK, migrations, extração do PDF | escrita, aguardando teste |
| 2 | Corte do documento, embeddings e busca vetorial | concluída |
| 3 | Agente com saída estruturada e interface React | concluída |
| 4 | Agente com resposta estruturada, múltiplas consultas por categoria, tela em React | a escrever |

### Decisões já tomadas

- Um edital por vez, sem base multi-documento.
- OpenAI como provedor, `text-embedding-3-small` com 1536 dimensões.
- RAG no banco próprio com pgvector, não em vector store do provedor. Justificativa: controle sobre o corte dos pedaços, visibilidade dos vetores e independência de provedor.
- Streaming e processamento em fila ficam fora do projeto para manter o fluxo introdutório visível de ponta a ponta.
- Reranking com CrossEncoder fica fora. Exige outro provedor e outra chave, e o ganho sobre a fusão simples é pequeno em um documento só.
- MCP para extração fica fora. Resolve escala, e aqui é um arquivo por vez.
- Orquestrador multi-agente fica fora. O princípio de contexto isolado entra na forma de uma consulta de recuperação por categoria de habilitação.

### Pontos de ajuste para o fim da série

- Limpeza de cabeçalho e rodapé repetidos nas 48 páginas.
- Deduplicação de trechos quase idênticos. No edital de Vargem Bonita, as seções 9 e 16 do corpo tratam ambas de sanções com texto muito parecido, e a "descrição da solução como um todo" aparece quase igual no Termo de Referência e no Estudo Técnico Preliminar.
- Conversão para Markdown antes de indexar, para preservar tabelas e hierarquia.
- OCR para editais digitalizados.
- Reranking.
- Base com vários editais e busca comparativa.

### Achados do edital de Vargem Bonita

Levantados na leitura do PDF, para usar nas Partes 2 a 4.

**A numeração reinicia em cada anexo.** O corpo tem um item 7 (HABILITAÇÃO). O Termo de Referência tem outro item 7 (CRITÉRIOS DE MEDIÇÃO E PAGAMENTO). O Estudo Técnico Preliminar tem um terceiro item 7 (DESCRIÇÃO DA SOLUÇÃO). Por isso o endereço de um trecho precisa ser a dupla `source` mais `clause`, algo como `anexo_ii` e `2.f`. Guardar só o número produz citação ambígua.

**A resposta está em três lugares, e um deles não responde nada.** O item 7 do corpo se chama HABILITAÇÃO e não lista documento nenhum: só remete ao Anexo II e diz que a documentação pode ser substituída pelo registro no SICAF. Uma busca semântica por "quais documentos são exigidos" vai ranquear esse trecho no topo e devolver uma resposta correta e inútil. O item 8.3 do Termo de Referência lista as quatro categorias em termos genéricos, citando os artigos 66 a 69 da Lei 14.133. A lista concreta está no Anexo II. Além disso, quatro exigências aparecem fora do Anexo II, nos itens 7.5, 7.6 e 7.7 do corpo. É o argumento a favor de disparar várias consultas por categoria em vez de uma pergunta genérica.

**O edital se contradiz.** O item 8.3.4 do Termo de Referência afirma que a qualificação econômico-financeira será comprovada por balanço patrimonial e demonstrações contábeis do último exercício. O Anexo II, que é a lista oficial, não pede balanço: pede apenas certidão negativa de falência e recuperação judicial. O comportamento correto do sistema é sinalizar o conflito e citar as duas cláusulas, não escolher uma. É o que justifica o campo de confiança e o guardrail de abstenção.

**Referências cruzadas quebradas.** O texto remete a itens 2.7.2, 2.7.3 e 2.7.4 que não correspondem ao conteúdo citado, e menciona um item 16.3 num contexto que não bate. O documento tem erro de numeração próprio, então não convém fazer o agente seguir referência cruzada cegamente.

**Tabelas embaralhadas.** As tabelas de preços do PNCP, nas páginas 27 e 28, saem com colunas misturadas no meio das descrições quando extraídas como texto puro. É o principal argumento para a conversão em Markdown.

### Gabarito de habilitação

Levantado manualmente a partir do Anexo II e do corpo do edital. Serve para medir o recall do sistema na Parte 3 e fechar a série com um número real. Alvo: dezoito itens mais três observações.

**Habilitação jurídica.** Registro comercial para empresa individual, ou ato constitutivo, estatuto ou contrato social em vigor devidamente registrado (com documentos de eleição dos administradores no caso de sociedade por ações), ou inscrição do ato constitutivo com prova de diretoria em exercício para sociedades civis, ou decreto de autorização para empresa estrangeira.

**Regularidade fiscal.** Inscrição no CNPJ atualizada. Certidão negativa de tributos federais e dívida ativa da União. Regularidade com a Fazenda Estadual. Regularidade com a Fazenda Municipal da sede. Regularidade do FGTS. CNDT. Certidão negativa de falência e recuperação judicial com emissão de até 60 dias.

**Qualificação técnica.** Atestado de capacidade técnica emitido por pessoa jurídica de direito público ou privado.

**Declarações.** Não emprego de menor. Carta de preposto conforme o Anexo IV. Inexistência de fatos impeditivos e de declaração de inidoneidade. Conhecimento integral do edital.

**Fora do Anexo II, no corpo.** Declaração de que atende aos requisitos de habilitação (item 7.5). Declaração sobre reserva de cargos para pessoa com deficiência e reabilitado (item 7.6). Declaração sobre a integralidade dos custos trabalhistas na proposta (item 7.7).

**Observações que o sistema deveria capturar.** A documentação de habilitação jurídica, fiscal, social, trabalhista e econômico-financeira pode ser substituída pelo registro no SICAF. Para microempresa e empresa de pequeno porte, a regularidade fiscal e trabalhista só é exigida para efeito de contratação, não como condição de participação. E o conflito sobre o balanço patrimonial descrito acima.

### Pendências

- Testar a extração do PDF, com atenção às páginas 27 e 28.
- Confirmar se o `laravel/ai` está estável ou em preview, e qual versão do framework ele exige. Muda o tom da abertura.
- Definir título final. Candidatos: "RAG na prática: lendo editais de licitação com Laravel, pgvector e busca híbrida" ou "Como extrair a lista de documentos de um edital sem ler o edital inteiro".
- Ao fim da série, gerar a versão em HTML compatível com o editor Quill, mais o excerpt.
- Nome do repositório no GitHub, para acompanhar o código pelo terminal.
