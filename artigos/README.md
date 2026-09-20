# Artigos da série

HTML pronto para colar no editor Quill. Cada arquivo contém só o conteúdo do corpo, sem
`<html>`, `<head>` ou wrapper, que é o formato que o Quill armazena.

O texto usa HTML simples para colagem no Quill: `h2`, `h3`, `p`, `ul`, `ol`, `li`, `a` e `br`.
Os trechos de código seguem o formato `<pre><code>...</code></pre>`, sem classes ou estilos
inline. Caracteres de HTML dentro desses blocos permanecem escapados.

Cada artigo começa com um sumário. Os títulos das seções recebem `id` e os itens do
sumário apontam para eles com links internos. Antes de todo bloco que cria ou altera um
arquivo, o texto informa o caminho relativo à raiz do projeto.

O título de cada artigo não está no HTML, para você preencher no campo de título do blog.
Os metadados revisados também estão em `excerpts-e-tags.txt`.

---

## Parte 1

**Arquivo:** `parte-1-ambiente-e-fundacao.html`

**Título:** Lendo um edital de licitação sem ler o edital inteiro

**Subtítulo sugerido:** Parte 1: ambiente, banco vetorial e a primeira extração

**Excerpt:**

> Editais de licitação têm dezenas de páginas e uma pergunta só importa: quais documentos
> eles exigem. Nesta série vamos construir um sistema que responde isso, usando o Laravel
> AI SDK, PostgreSQL com pgvector e React. Na primeira parte, o ambiente em Docker, o banco
> preparado para busca vetorial e a primeira extração do PDF, com uma armadilha que só a
> saída real revela.

**Tags sugeridas:** Laravel, RAG, IA, PostgreSQL, pgvector, Docker, PHP

---

## Parte 2

**Arquivo:** `parte-2-ingestao-e-busca.html`

**Título:** Ingestão e busca: o edital dentro do banco

**Subtítulo sugerido:** Parte 2: cortar o documento, gerar os vetores e buscar por
similaridade

**Excerpt:**

> O texto do edital vai para o banco em pedaços, com os vetores que o Laravel AI SDK gera
> em uma linha, e a busca semântica começa a funcionar sem nenhum modelo generativo no
> meio. Depois montamos uma busca semântica isolada para entender, antes do agente, o que
> a recuperação realmente entrega.

**Tags sugeridas:** Laravel, RAG, IA, embeddings, pgvector, PHP

---

## Parte 3

**Arquivo:** `parte-3-agente-e-interface.html`

**Título:** O agente, a checklist e o que um RAG mínimo deixa na mesa

**Subtítulo sugerido:** Parte 3: saída estruturada, interface em React e evidências para conferência

**Excerpt:**

> Última parte da série: um agente do Laravel AI SDK recebe a busca vetorial como
> ferramenta, consulta o edital por conta própria e devolve a lista de documentos em JSON
> validado por schema, com o trecho e a página de cada exigência. Mais a tela em React, a
> geração de um PDF paginado no navegador e uma discussão honesta sobre os limites desse
> RAG introdutório.

**Tags sugeridas:** Laravel, RAG, IA, agentes, React, PHP
