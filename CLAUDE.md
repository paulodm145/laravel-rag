# Instruções do repositório

Os arquivos `AGENTS.md` e `CLAUDE.md` devem permanecer idênticos. Qualquer mudança nas instruções
precisa ser aplicada aos dois arquivos no mesmo commit.

## Projeto

Aplicação didática em Laravel que recebe um edital em PDF e devolve os documentos de
habilitação exigidos, acompanhados do trecho e da página de origem. O projeto demonstra
um pipeline de RAG com Laravel AI SDK, OpenAI, PostgreSQL com pgvector e React.

O código acompanha uma série de três artigos publicada no
[paulorb.dev](https://paulorb.dev). Os artigos não são mantidos neste repositório; a
documentação técnica local fica em `docs/`.

## Prioridades

1. Correção: evitar perda silenciosa de dados, páginas incorretas, erros de encoding e
   uso incorreto das APIs do Laravel ou do Laravel AI SDK.
2. Segurança: validar uploads, limitar tamanho, usar o disco privado, não expor exceções
   internas e não versionar PDFs, credenciais ou dados processados.
3. Comportamento do agente: manter as sete regras de
   `app/Ai/Agents/HabilitationAnalyst.php` compatíveis com o schema.
4. Clareza didática: preferir código direto e legível a abstrações que escondam o
   mecanismo estudado.
5. Consistência: comandos, README, arquivos em `docs/` e implementação devem descrever
   o mesmo fluxo.

## Decisões de arquitetura

- PHP 8.4, Laravel 13 e Laravel AI SDK.
- OpenAI como provedor; embeddings `text-embedding-3-small` com 1536 dimensões.
- PostgreSQL 17 com pgvector e índice HNSW.
- Extração por `pdftotext -layout`; o form feed identifica as páginas.
- Pedaços de aproximadamente 1200 caracteres com 200 de sobreposição.
- Embeddings em lotes de 32, timeout de 120 segundos, cache e três tentativas.
- Busca vetorial pura, sempre limitada ao documento escolhido.
- Agente com `SimilaritySearch`, saída estruturada e timeout de 180 segundos.
- React montado pelo Vite em um Blade, sem Inertia.
- Requisições Axios concentradas em hooks próprios.
- PDF gerado no navegador com `jspdf`, instalado somente na etapa de interface.
- CSS escrito à mão, sem framework visual.
- Ingestão síncrona para manter o pipeline visível.

## Convenções

- Nomes de arquivos, classes, funções e variáveis em inglês.
- Comentários e mensagens ao usuário em português.
- Endpoints paginados usam `skip` e `take`, não `page`.
- Migrations usam o Blueprint quando houver método equivalente. SQL cru fica restrito ao
  que o Blueprint não cobre, como a coluna gerada `tsvector`.
- Caminhos do disco local são obtidos com `Storage::disk('local')->path()`.
- O campo `embedding` permanece em `$hidden` no `DocumentChunk`.
- Não usar o atributo `Temperature` no agente.
- Evitar dependências novas quando Laravel, PostgreSQL ou APIs do navegador já resolverem
  o problema de forma clara.

## Commits e Git

- Todo commit deve ser **semântico**, seguindo Conventional Commits:
  `feat:`, `fix:`, `docs:`, `refactor:`, `test:`, `chore:` ou `build:`.
- Todo commit deve ser **atômico**: uma única mudança coerente, fácil de revisar e
  reverter. Não misturar correções independentes, documentação sem relação ou formatação
  ampla no mesmo commit.
- A mensagem deve ser curta, no imperativo e explicar o resultado, por exemplo:
  `fix: scope vector search to current document`.
- Antes de commitar, revisar `git diff --staged` e confirmar que não há `.env`, PDFs,
  uploads, logs, dependências instaladas ou artefatos de build.
- Não reescrever histórico compartilhado nem usar push forçado sem autorização explícita.
- Pull requests devem explicar objetivo, mudanças e validações executadas.

## Verificação

Tudo roda em Docker:

```bash
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
docker compose exec app npm run build
```

Comandos que chamam a OpenAI custam dinheiro. Não executar ingestão ou agente apenas para
validar uma alteração que possa ser conferida localmente.

## Fora do escopo

Não apresentar como defeito conhecido:

- ausência de fila, autenticação, rate limiting, cache de resposta ou multiusuário;
- busca híbrida, corte por cláusula, limpeza de cabeçalho e rodapé;
- deduplicação, reranqueamento, OCR e avaliação automatizada de recall;
- troca de SDK, banco, provedor ou arquitetura do front-end.

Esses itens foram omitidos deliberadamente para preservar o caráter introdutório.

## Arquivos principais

```text
app/Ai/Agents/HabilitationAnalyst.php
app/Services/EditalIngestor.php
app/Http/Controllers/EditalController.php
app/Console/Commands/
app/Models/Document.php
app/Models/DocumentChunk.php
resources/js/
resources/css/app.css
resources/views/app.blade.php
docs/
```
