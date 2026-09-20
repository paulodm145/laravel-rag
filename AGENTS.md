# AGENTS.md

Instruções para agentes de código que forem revisar ou modificar este repositório.

## O que é este projeto

Aplicação Laravel que lê um edital de licitação em PDF e devolve a lista de documentos de
habilitação exigidos, com o trecho e a página de origem de cada exigência. Implementa um
fluxo de RAG (geração aumentada por recuperação) usando o Laravel AI SDK (`laravel/ai`) e
PostgreSQL com pgvector.

**É um projeto de estudo**, escrito para acompanhar uma série de três artigos técnicos. O
código prioriza legibilidade e clareza do mecanismo sobre robustez. Isso é deliberado e
não é um defeito a corrigir.

## Tarefa de revisão

O objetivo da revisão é encontrar defeitos reais, não sugerir arquitetura alternativa.

### Revise

1. **Correção.** Bugs, condições de erro não tratadas, dados que se perdem em silêncio,
   uso incorreto de API do Laravel ou do SDK, problemas de encoding em texto português.
2. **Segurança.** Validação de upload, caminho de arquivo, injeção em SQL cru, exposição
   de dado sensível em log ou em resposta, tamanho de arquivo aceito.
3. **Comportamento do agente de IA.** As instruções em
   `app/Ai/Agents/HabilitationAnalyst.php` têm sete regras numeradas. Verifique se o
   schema permite cumprir cada uma e se há regra que o schema torna impossível.
4. **Consistência entre código e artigos.** Os textos em `docs/` e `artigos/` mostram
   trechos do código. Divergência entre o que o artigo afirma e o que o código faz é
   defeito de documentação e deve ser apontada.
5. **Convenções.** Ver a seção abaixo.

### Não reporte

- Ausência de testes automatizados. Conhecida e aceita no escopo.
- Ausência de fila, cache de resposta, rate limiting, autenticação ou multiusuário.
- As limitações listadas em `CLAUDE.md` na seção de ajustes futuros. Elas foram cortadas
  de propósito e estão discutidas nos artigos. Em particular: busca híbrida, corte por
  cláusula, limpeza de cabeçalho e rodapé, deduplicação, reranqueamento, OCR e avaliação
  automatizada de recall.
- Sugestões de trocar o SDK, o banco, o provedor de IA ou o modelo de front por outro.
- Reescrita de trecho funcional por questão de estilo pessoal.

### Formato da resposta

Para cada achado: arquivo e linha, o que está errado, em que situação concreta isso falha,
e a correção mínima. Ordene por severidade. Se não houver achado em alguma categoria, diga
isso em uma linha em vez de preencher com observações genéricas.

## Convenções do projeto

- Nomes de variáveis, funções, arquivos e componentes em **inglês**. Comentários e
  mensagens ao usuário em **português**.
- Paginação em endpoints Laravel usa `skip` e `take`, não `page`.
- No front, requisições com **axios dentro de hooks próprios**. Não usar `fetch`.
- Sem Inertia. React é montado pelo Vite direto em um Blade. Uma página só não justifica
  o roteamento do Inertia.
- A biblioteca `jspdf` entra somente na Parte 3. A exportação é gerada no navegador pelo
  utilitário `resources/js/utils/exportChecklistPdf.js`, sem chamada adicional ao backend.
- CSS escrito à mão, sem framework.
- Evitar dependência nova. Antes de sugerir uma, verificar se o Laravel ou o Postgres já
  resolvem.
- Migrations usam o Blueprint sempre que existir método equivalente. SQL cru só onde o
  Blueprint não cobre, como a coluna gerada `tsvector`.

## Arquitetura

```
app/
  Ai/Agents/HabilitationAnalyst.php   Agente: instruções, tool de busca, schema de saída
  Console/Commands/
    ExtractPdfText.php                Inspeção da extração, sem gravar nada
    IngestEdital.php                  Ingestão pelo terminal
    AnalyzeEdital.php                 Executa o agente pelo terminal
    SearchEdital.php                  Busca vetorial pura, sem modelo generativo
  Http/Controllers/EditalController.php   Endpoint único: upload, ingestão e análise
  Models/Document.php
  Models/DocumentChunk.php            Cast AsVector, embedding em $hidden
  Services/EditalIngestor.php         Extração, corte, vetorização e gravação
resources/
  css/app.css                         Estilos da interface
  js/utils/exportChecklistPdf.js      Geração do PDF no navegador com jsPDF
  js/app.jsx                          Raiz do React
  js/components/DropZone.jsx
  js/components/Checklist.jsx
  js/hooks/useEditalAnalysis.js
docs/                                 Texto dos artigos em markdown
artigos/                              Mesmo conteúdo em HTML para o editor do blog
```

### Fluxo de dados

1. `pdftotext -layout` converte o PDF em texto. O caractere de form feed (`\f`) entre
   páginas fornece o número da página de cada trecho. Por isso o texto extraído **não**
   passa por `trim()` antes do `explode`.
2. O texto é cortado em pedaços de 1200 caracteres com 200 de sobreposição, respeitando
   fronteira de parágrafo e de página.
3. `Embeddings::for()` gera os vetores em lotes de 32, com timeout de 120 segundos, cache
   ligado e três tentativas.
4. Os pedaços são gravados em `document_chunks`, com a coluna `embedding` do tipo
   `vector(1536)` e índice HNSW.
5. O agente recebe a tool `SimilaritySearch::usingModel()` escopada ao documento da
   requisição, faz várias buscas e devolve JSON validado pelo schema.

## Armadilhas conhecidas

Já foram encontradas e corrigidas. Não reintroduzir, e conferir se ainda estão corretas:

- **`$table->vector()` cria a coluna como `not null`.** Precisa de `nullable()`, porque o
  pedaço pode ser gravado antes do vetor existir.
- **Campo fora do `$fillable` é descartado em silêncio.** Foi o que aconteceu com `page`:
  nenhum erro, coluna nula. Qualquer coluna nova precisa entrar na lista.
- **A raiz do disco `local` é `storage/app/private` a partir do Laravel 11.** Montar
  caminho com `storage_path('app/'.$path)` aponta para arquivo inexistente. Usar
  `Storage::disk('local')->path()`.
- **`embedding` precisa estar em `$hidden`.** A tool de busca entrega os models
  serializados ao modelo de linguagem, e 1536 floats por trecho entupiriam o contexto.
- **Timeouts do SDK são curtos e diferentes por operação.** 30 segundos em embeddings,
  60 no agente. Quando estouram, o cliente HTTP lança `ConnectionException` e o erro
  aparece como falha de conexão com o provedor, o que engana. Configurados em 120 e 180.
- **Não usar o atributo `Temperature` no agente.** Modelos de raciocínio da OpenAI
  rejeitam esse parâmetro.

## Como rodar

Tudo em Docker, nenhum comando exige PHP no host.

```bash
docker compose up -d
docker compose exec app php artisan migrate
docker compose exec app npm run build
```

```bash
# Inspeciona a extração sem gravar
docker compose exec app php artisan edital:extract storage/app/edital.pdf --pages=3

# Ingestão completa
docker compose exec app php artisan edital:ingest storage/app/edital.pdf

# Busca vetorial pura
docker compose exec app php artisan edital:search "certidão negativa de débitos trabalhistas"

# Agente sobre o último documento indexado
docker compose exec app php artisan edital:analyze
```

Requer `OPENAI_API_KEY` no `.env`. Os comandos que chamam o provedor custam dinheiro, e
`edital:ingest` custa proporcionalmente ao tamanho do PDF. O cache de embeddings evita
cobrança em reprocessamento do mesmo conteúdo.

## Contexto adicional

`CLAUDE.md` tem o histórico de decisões, o que ficou fora de escopo e por quê, e as
particularidades do edital usado como teste. Leia antes de propor mudança de escopo.
