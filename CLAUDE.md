# rag-laravel

Projeto de estudo que acompanha uma série de três artigos para o blog paulorb.dev.

Os artigos em markdown ficam em `docs/`. A versão em HTML para o editor do blog, com os
títulos e excerpts, fica em `artigos/`. Instruções para agentes de revisão de código estão
em `AGENTS.md`.

## O que é

Um sistema que lê um edital de licitação em PDF e devolve a lista de documentos de
habilitação que o edital exige, com o trecho do edital e a página que embasam cada item.

O objetivo é **estudo de RAG**, não um produto acabado. Quando houver dúvida entre a
solução simples e a solução completa, escolha a simples e registre a alternativa na
seção de ajustes futuros deste arquivo. Refinamento prematuro atrapalha mais do que
ajuda aqui: a prioridade é o pipeline funcionando de ponta a ponta.

Documento de teste: Pregão Eletrônico 006/2026 do município de Vargem Bonita/SC,
serviços de arbitragem esportiva, 48 páginas, Lei 14.133/2021.
Fica em `storage/app/edital.pdf` (não versionado).

## Stack

- PHP 8.4, Laravel e React montado diretamente pelo Vite (sem Inertia)
- PostgreSQL com pgvector, via imagem `pgvector/pgvector:pg17`
- `laravel/ai` (Laravel AI SDK) como camada de IA
- OpenAI como provedor, `text-embedding-3-small`, 1536 dimensões
- `poppler-utils` (`pdftotext`, `pdfinfo`) para extração do PDF
- Tudo roda em Docker. Nenhum comando deve exigir PHP instalado no host.

Comandos rodam sempre dentro do container:

```bash
docker compose exec app php artisan <comando>
```

## Plano da série

| Parte | Escopo | Status |
| --- | --- | --- |
| 1 | Docker, projeto Laravel, AI SDK, migrations, extração do PDF | concluída |
| 2 | Ingestão: corte simples com número de página, embeddings com `Embeddings::for()`, busca por similaridade | concluída |
| 3 | Agente com resposta estruturada usando a tool `SimilaritySearch`, tela em React | concluída |

O foco da série é **demonstrar o Laravel AI SDK**. Tudo que não passa pelo SDK é ruído
e deve ficar no mínimo indispensável.

A especificação de cada parte, com o código e a justificativa de cada decisão, fica em
`docs/`. **Leia o arquivo da parte correspondente antes de implementar.** O texto do
artigo é a fonte da verdade: se o código divergir do que está escrito lá, avise em vez
de seguir em frente.

## Decisões fechadas

- Um edital por vez. Sem base multi-documento.
- RAG no banco próprio com pgvector, não em vector store do provedor. Motivo: controle
  sobre o corte dos pedaços, visibilidade dos vetores e independência de provedor.
- Corte do documento deliberadamente simples: parágrafos acumulados até um tamanho fixo,
  com sobreposição. Sem parser de cláusula e sem detecção de anexo.
- A página vem do form feed que o `pdftotext` insere entre páginas, não do rodapé
  impresso. Cada pedaço fica contido em uma página só, o que custa cortar um trecho na
  virada de página e em troca dá a citação de graça.
- Ingestão síncrona, por comando Artisan. Sem fila: é um arquivo, rodado à mão.
- Busca vetorial pura. A coluna `content_tsv` existe no banco mas não é usada.
- Ficam fora: busca híbrida com RRF, streaming, reranking, orquestrador multi-agente,
  consultas múltiplas por categoria e rastreio de cláusula.
- Recall importa mais que precisão, então o limiar de similaridade é baixo e o limite de
  trechos recuperados é alto. Aceita-se ruído.
- O agente deve se abster quando não tiver base no texto recuperado, em vez de inferir.

## Convenções de código

- **Nomes de variáveis, funções, arquivos e componentes em inglês.** Comentários e
  mensagens ao usuário em português.
- O front é React montado pelo Vite direto num Blade, sem Inertia. Uma página só não
  justifica o roteamento do Inertia, e axios em hook próprio é a preferência do projeto.
- A exportação em PDF usa `jspdf` no front e fica isolada na Parte 3, em
  `resources/js/utils/exportChecklistPdf.js`. O arquivo é gerado no navegador, sem nova
  chamada ao backend.
- CSS escrito à mão, sem framework.
- Paginação em endpoints Laravel usa `skip` e `take`.
- No React, requisições com axios dentro de hooks próprios. Não usar `fetch`.
- Evitar biblioteca externa desnecessária. Antes de adicionar dependência, verificar se
  o Laravel ou o Postgres já resolvem.
- Migrations: usar o Blueprint sempre que existir método equivalente. SQL cru só onde o
  Blueprint não cobre, como a coluna gerada `tsvector`.

## Ajustes deixados para o fim da série

Não implementar agora. A lista existe para ser retomada no último artigo, comparando
antes e depois.

- Corte por cláusula, usando a numeração no início da linha
- Rastreio de cláusula e de origem, para citação mais precisa que a página (as colunas
  `source` e `clause` já existem no banco, sempre nulas)
- Pedaços que atravessam a virada de página, hoje cortados ali
- Busca híbrida: somar a busca por palavra-chave sobre `content_tsv` e fundir com RRF
- Consultas múltiplas, uma por categoria de habilitação
- Limpeza de cabeçalho e rodapé repetidos em todas as páginas
- Deduplicação de trechos quase idênticos
- Conversão para Markdown antes de indexar, preservando tabelas e hierarquia
- Ingestão em fila
- OCR para editais digitalizados
- Reranking
- Base com vários editais e busca comparativa

## Particularidades do edital de teste

Levantadas na leitura do PDF e confirmadas na saída real. Nenhuma delas é tratada no
código: estão aqui porque explicam o comportamento do sistema e alimentam o artigo de
ajustes no fim da série.

- **A numeração reinicia em cada anexo.** O corpo tem um item 7 (HABILITAÇÃO), o Termo de
  Referência tem outro item 7, o Estudo Técnico Preliminar tem um terceiro. Por isso o
  endereço de um trecho é a dupla `source` + `clause`, nunca o número sozinho.
- **A lista de documentos está espalhada.** O item 7 do corpo se chama HABILITAÇÃO e não
  lista documento nenhum, só remete ao Anexo II. O item 8.3 do Termo de Referência lista
  categorias genéricas. A lista concreta está no Anexo II. E quatro exigências aparecem
  fora dele, nos itens 7.5, 7.6 e 7.7 do corpo.
- **O edital se contradiz.** O item 8.3.4 do Termo de Referência pede balanço patrimonial
  para qualificação econômico-financeira; o Anexo II não pede. O comportamento correto é
  sinalizar o conflito e citar as duas cláusulas, não escolher uma.
- **Referências cruzadas quebradas.** O texto remete a itens que não correspondem ao
  conteúdo citado. Não fazer o agente seguir referência cruzada cegamente.
- **Tabelas embaralhadas.** As tabelas de preços nas páginas 27 e 28 saem com colunas
  misturadas na extração de texto puro.
- **O rodapé sai como `P á g i n a 3 | 48`**, com espaço entre as letras, porque o Word
  aplicou espaçamento e o `pdftotext` o devolve literalmente. Motivo pelo qual a página é
  lida do form feed, e não do rodapé. Verificado na saída real.
- **A numeração das cláusulas vem no início da linha**, no formato `2.5.1.`, e os títulos
  de seção vêm em caixa alta. É o que permite cortar por cláusula.
- **O PDF nasceu digital** (`Creator: Acrobat PDFMaker 25 para Word`), por isso a
  extração sai limpa. Não serve para testar o caminho de OCR.

## Gabarito

`docs/gabarito-habilitacao.md` tem a lista de exigências levantada à mão no edital de
teste. É o alvo contra o qual o recall do sistema será medido na Parte 3. Não alterar
esse arquivo para fazer o sistema parecer melhor.

## Estado atual

**Parte 1 concluída e validada.** Containers rodando, `laravel/ai` instalado, migrations
aplicadas e extração do PDF funcionando. O schema foi conferido no banco: `embedding` como
`vector(1536)`, `content_tsv` como coluna gerada, índice HNSW com `vector_cosine_ops` e
índice GIN.

Correção aplicada depois da validação: a migration
`2026_09_18_131500_make_document_chunks_embedding_nullable.php` remove o `not null` de
`embedding`. O `$table->vector()` cria a coluna como not null, o que impediria gravar os
pedaços na Parte 2 antes de gerar os vetores na Parte 3.

**Parte 2 concluída.** Models `Document` e `DocumentChunk`, comandos `edital:ingest` e
`edital:search`. Ingestão validada no edital de teste: 48 páginas resultaram em 117
pedaços. A coluna `page` é preenchida a partir do form feed do `pdftotext`; `source` e
`clause` seguem nulas por escolha de escopo.

Bug corrigido depois da primeira execução: `page` não estava no `$fillable` do
`DocumentChunk`, então o `create()` descartava o valor em silêncio e a coluna ficava nula.
Quem reprocessar precisa rodar `migrate:fresh` antes, porque os pedaços antigos não têm
página.

Observado na busca real, e deixado como material para o artigo de ajustes: o trecho mais
similar à pergunta "documentos exigidos para habilitação" é o item 7 do edital, que não
lista documento nenhum e só remete ao Anexo II. O Anexo II aparece em quarto lugar. É o
limite da busca por similaridade pura, que mede semelhança de assunto e não presença de
resposta. Também apareceu um pedaço quase vazio, com fragmento cortado no meio de palavra
mais o rodapé, efeito da sobreposição somada à ausência de limpeza de ruído.

**Parte 3 gravada, ainda não executada.** O que entrou:

- `app/Services/EditalIngestor.php` concentra extração, corte e vetorização. O comando
  `edital:ingest` e o controller usam o mesmo serviço.
- `app/Ai/Agents/HabilitationAnalyst.php`: agente com `HasTools` e `HasStructuredOutput`,
  `MaxSteps(10)` e `Timeout(180)`. A tool é `SimilaritySearch::usingModel()` escopada ao
  documento. As instruções proíbem completar a lista com conhecimento da Lei 14.133,
  exigem o trecho literal em `evidence`, mandam buscar de novo quando um trecho só remete
  a um anexo, e mandam registrar contradições em `warnings` sem escolher lado.
- `edital:analyze`, para rodar o agente pelo terminal.
- `EditalController::analyze`, endpoint único que indexa e analisa na mesma requisição.
- Front em `resources/js`: `app.jsx`, `components/DropZone.jsx`,
  `components/Checklist.jsx`, `hooks/useEditalAnalysis.js`.
- `README.md` com a nota sobre a natureza e os limites do projeto.

Correções aplicadas:

- `DocumentChunk` ganhou `$hidden` para o `embedding`. A tool de busca entrega os models
  serializados ao agente, e 1536 floats por trecho entupiriam o contexto.
- O caminho absoluto do upload vem de `Storage::disk('local')->path()`. A partir do
  Laravel 11 a raiz do disco local é `storage/app/private`, não `storage/app`, então
  montar o caminho com `storage_path('app/'.$path)` aponta para arquivo inexistente. O
  sintoma era um erro de I/O do `pdftotext` dizendo que o arquivo não existe, logo depois
  de o upload ter sido gravado com sucesso.

**Parte 3 concluída e validada pela tela.** Fluxo completo funcionando: upload, ingestão,
agente e checklist com exportação em PDF pelo jsPDF no navegador.

Os três artigos estão escritos e revisados, em `docs/` e `artigos/`.

`AGENTS.md` foi substituído: antes trazia o bootstrap do Laravel Boost que veio com o
skeleton, agora traz as instruções de revisão de código. O bootstrap do Boost pode ser
recuperado rodando `php artisan boost:install`, se algum dia for útil.

Revisão posterior corrigiu três pontos: a página vazia criada pelo form feed final do
`pdftotext` não entra mais na contagem; `edital:search` e `edital:analyze` consideram apenas
documentos prontos e a busca fica escopada ao documento escolhido; o endpoint web não
expõe mais mensagens internas de exceção. O schema passou a exigir `page` e `notes`, em
acordo com as regras 2 e 5 do agente. O `.env.example` agora já aponta para o PostgreSQL do
Compose e declara `OPENAI_API_KEY`.

O serviço `worker` foi removido do Compose porque a ingestão desta série é síncrona. O
Redis permanece disponível para o cache de embeddings mostrado no primeiro artigo.

Se a geração de embeddings falhar depois de o registro ter sido criado, o documento passa
para `failed`; os comandos de busca e análise ignoram esse estado. Isso evita que uma
ingestão parcial seja escolhida como se estivesse pronta.
