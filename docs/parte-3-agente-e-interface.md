# O agente, a checklist e o que um RAG mínimo deixa na mesa

> **RAG na prática · Parte 3 de 3**
>
> A versão publicável deste texto, em HTML para o editor do blog, está em
> `artigos/parte-3-agente-e-interface.html`, junto com o título e o excerpt.

Este arquivo registra a estrutura e as decisões da Parte 3. O texto corrido está no HTML.

## Estrutura do artigo

1. Abertura e sumário com links para as seções.
2. O que é um agente no vocabulário do SDK. `make:agent --structured`.
3. A tool `SimilaritySearch::usingModel()`, com destaque para o argumento `query` que
   escopa a busca ao documento, e para o papel do `$hidden` no `embedding`.
4. O schema, com a justificativa de `evidence`, `page`, `confidence` e `warnings`. Cada um
   deles responde a uma observação concreta da Parte 2.
5. As instruções, com as sete regras. Destaque para a regra 3, que manda buscar de novo
   quando um trecho só remete a um anexo, e que existe por causa do item 7 do edital.
6. Configuração por atributos: `Provider`, `MaxSteps`, `Timeout`. E a justificativa para
   **não** usar `Temperature`.
7. A chamada do agente e o comando de terminal.
8. O endpoint único, com a nota de que em uso real isso iria para fila.
9. A interface: React montado pelo Vite em um Blade, sem Inertia, com axios em hook
   próprio. O timeout de 300 segundos no axios.
10. Exportação real em PDF no navegador com `jspdf`, instalada somente nesta parte. O
    utilitário fica em `resources/js/utils/exportChecklistPdf.js` e é chamado pelo
    `Checklist.jsx`.
11. O que o projeto deixa na mesa: busca híbrida, corte por cláusula, medição de recall.
12. Encerramento e nota sobre a natureza do projeto.

Todos os blocos que criam ou alteram arquivos são precedidos pelo caminho relativo à raiz
do projeto. A cronologia de bugs encontrados durante o desenvolvimento não faz parte do
tutorial; o leitor recebe diretamente a implementação corrigida.

## Decisões de escopo confirmadas na escrita

- Sem Inertia. Justificado no texto: uma página só não paga o roteamento.
- Exportação em PDF com `jspdf`, sem enviar os dados novamente ao backend.
- Endpoint único em vez de fila, assumido explicitamente como escolha de estudo, com
  menção a `queue()` e broadcasting como o caminho de uso real.
- A seção "o que este projeto deixa na mesa" cumpre o papel que antes seria um quarto
  artigo de ajustes. Discute busca híbrida apontando para a coluna `content_tsv` criada na
  Parte 1 e nunca consultada, corte por cláusula, e ausência de medição de recall.

## Pendência

O gabarito de habilitação levantado à mão (`docs/gabarito-habilitacao.md`) não foi usado
para medir recall, e o artigo assume isso de forma explícita como limitação. Se você quiser
fechar esse ciclo depois, o material está pronto e rende um artigo curto de continuação.
