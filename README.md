<h1 align="center">RAG com Laravel AI SDK</h1>

<p align="center">
  Análise de editais com recuperação vetorial, resposta estruturada e evidências verificáveis.
</p>

<p align="center">
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white" alt="Laravel 13"></a>
  <a href="https://www.php.net"><img src="https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white" alt="PHP 8.4"></a>
  <a href="https://laravel.com/docs/ai-sdk"><img src="https://img.shields.io/badge/Laravel_AI_SDK-0.11-FF2D20" alt="Laravel AI SDK"></a>
  <a href="https://www.postgresql.org"><img src="https://img.shields.io/badge/PostgreSQL-17-4169E1?logo=postgresql&logoColor=white" alt="PostgreSQL 17"></a>
  <a href="https://github.com/pgvector/pgvector"><img src="https://img.shields.io/badge/pgvector-HNSW-336791" alt="pgvector"></a>
  <a href="https://react.dev"><img src="https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=20232A" alt="React 19"></a>
  <a href="https://www.docker.com"><img src="https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white" alt="Docker Compose"></a>
  <a href="https://github.com/paulodm145/laravel-rag"><img src="https://img.shields.io/badge/GitHub-Repositório-181717?logo=github&logoColor=white" alt="Repositório no GitHub"></a>
  <img src="https://img.shields.io/badge/License-MIT-yellow.svg" alt="Licença MIT">
</p>

## Sobre o projeto

Este repositório é um guia de estudo sobre **RAG — Retrieval-Augmented Generation — no
Laravel**. Seu objetivo principal é mostrar, em um projeto pequeno e legível, como o
**Laravel AI SDK** conecta as etapas de um pipeline real: geração de embeddings, busca
vetorial, ferramentas para agentes e saída estruturada.

O caso de uso é a leitura de editais de licitação. A aplicação recebe um PDF, localiza as
exigências de habilitação e devolve uma checklist. Cada item vem acompanhado do trecho e
da página que sustentam a resposta, permitindo que o resultado seja conferido no
documento original.

O código acompanha uma série de três artigos publicada no
[blog paulorb.dev](https://paulorb.dev). A prioridade é tornar o mecanismo compreensível:
este é um projeto didático, não um produto pronto para uso em processos licitatórios.

> A saída é indicativa e pode conter omissões. Ela não substitui a leitura integral do
> edital nem a conferência das cláusulas citadas.

## Série de artigos

1. [Laravel AI SDK e pgvector: a base de um RAG em PHP](https://paulorb.dev/blog/laravel-ai-sdk-e-pgvector-a-base-de-um-rag-em-php)  
   Ambiente Docker, PostgreSQL com pgvector, migrations e extração do PDF.

2. [Embeddings e busca vetorial no Laravel com pgvector](https://paulorb.dev/blog/parte-2-laravel-rag-com-aisdk-embeddings-e-busca-vetorial-no-laravel-com-pgvector)  
   Corte por página, embeddings, ingestão e inspeção da recuperação semântica.

3. [O agente, a checklist e os limites de um RAG mínimo](https://paulorb.dev/blog/parte-3-o-agente-a-checklist-e-os-limites-de-um-rag-minimo)  
   Agente com ferramenta de busca, schema, interface React e geração do PDF.

Os textos publicados são mantidos no blog. As notas técnicas e o gabarito do edital de
teste ficam em [docs/](docs/).

## O que a aplicação demonstra

- extração com `pdftotext -layout`, preservando a origem por página;
- divisão do texto em pedaços com sobreposição;
- geração de embeddings em lotes pelo Laravel AI SDK;
- armazenamento em uma coluna `vector(1536)` no PostgreSQL;
- índice HNSW e recuperação por similaridade de cosseno;
- ferramenta `SimilaritySearch` limitada ao edital da requisição;
- agente com sete regras de análise e resposta validada por schema;
- checklist em React com evidências, alertas e níveis de confiança;
- geração de PDF paginado no navegador com jsPDF.

## Como funciona

```text
PDF
 └─ pdftotext -layout
     └─ páginas e pedaços
         └─ embeddings pelo Laravel AI SDK
             └─ PostgreSQL + pgvector
                 └─ SimilaritySearch
                     └─ agente com saída estruturada
                         └─ checklist React + PDF
```

O agente não recebe o edital inteiro. Ele usa a busca vetorial como ferramenta, faz
consultas com termos diferentes e monta a resposta somente a partir dos trechos
recuperados.

## Tecnologias

| Camada | Tecnologia |
| --- | --- |
| Backend | PHP 8.4, Laravel 13 e Laravel AI SDK |
| Provedor de IA | OpenAI |
| Embeddings | `text-embedding-3-small`, 1536 dimensões |
| Banco | PostgreSQL 17 com pgvector |
| Front-end | React 19, Axios e Vite |
| PDF | Poppler para extração e jsPDF para exportação |
| Cache | Redis |
| Ambiente | Docker Compose |

## Pré-requisitos

- Docker com o plugin Docker Compose;
- chave da API da OpenAI;
- PDF com camada de texto para testar a ingestão.

Não é necessário instalar PHP, Composer, Node, PostgreSQL ou Redis no host.

> A ingestão e a análise chamam a API da OpenAI e podem gerar cobrança. O cache de
> embeddings evita repetir esse custo quando o mesmo conteúdo é processado novamente.

## Ambiente de desenvolvimento

### 1. Obtenha o projeto

```bash
git clone git@github.com:paulodm145/laravel-rag.git rag-laravel
cd rag-laravel
```

Se você já recebeu os arquivos por outro meio, apenas abra um terminal na raiz do
repositório.

### 2. Configure o ambiente

```bash
cp .env.example .env
```

Abra o arquivo `.env` e informe a chave:

```dotenv
OPENAI_API_KEY=sua-chave-aqui
```

As configurações de PostgreSQL e Redis do `.env.example` já correspondem aos serviços
do Compose.

### 3. Construa a imagem e instale as dependências

```bash
docker compose build
docker compose run --rm app composer install
docker compose run --rm app npm ci
```

### 4. Suba os serviços

```bash
docker compose up -d
```

Confira o estado dos containers:

```bash
docker compose ps
```

### 5. Prepare o Laravel e o banco

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

### 6. Inicie o Vite em modo de desenvolvimento

O servidor PHP já é iniciado pelo Compose em `http://localhost:8000`. Em outro terminal,
inicie o Vite com recarga automática:

```bash
docker compose exec app npm run dev -- --host 0.0.0.0
```

Acesse:

- aplicação: [http://localhost:8000](http://localhost:8000);
- Vite: [http://localhost:5173](http://localhost:5173);
- PostgreSQL no host: `localhost:5434`.

Envie um edital em PDF pela tela e aguarde a extração, a geração dos embeddings e a
análise do agente. Documentos maiores podem levar alguns minutos.

### Parando o ambiente

```bash
docker compose down
```

O volume `pgdata` preserva o banco. Para remover também os dados locais:

```bash
docker compose down -v
```

## Usando pelo terminal

O [PDF empregado na série](https://vargembonita.sc.gov.br/uploads/sites/93/2026/02/PL012.2026-PE006.2026-ARBITRAGEM.pdf)
tem 48 páginas. Para repetir os comandos dos artigos, salve-o como
`storage/app/edital.pdf`.

```bash
# Confere se o PDF tem texto extraível e mostra as três primeiras páginas
docker compose exec app php artisan edital:extract storage/app/edital.pdf --pages=3

# Extrai, corta, gera embeddings e grava o documento
docker compose exec app php artisan edital:ingest storage/app/edital.pdf

# Pesquisa no último documento pronto, sem passar pelo agente
docker compose exec app php artisan edital:search "certidão negativa de débitos trabalhistas"

# Executa o agente sobre o último documento pronto
docker compose exec app php artisan edital:analyze
```

Também é possível informar o ID de um documento:

```bash
docker compose exec app php artisan edital:search "FGTS" 1 --limit=8
docker compose exec app php artisan edital:analyze 1
```

## Verificações

```bash
# Testes PHP
docker compose exec app php artisan test

# Padrão de código PHP
docker compose exec app vendor/bin/pint --test

# Build de produção do front-end
docker compose exec app npm run build
```

## Estrutura principal

```text
app/
├── Ai/Agents/HabilitationAnalyst.php       agente, ferramenta e schema
├── Console/Commands/                       comandos para estudar o pipeline
├── Http/Controllers/EditalController.php   upload e análise pela web
└── Services/EditalIngestor.php             extração, corte e embeddings

resources/
├── css/app.css                             estilos da interface
└── js/
    ├── components/                         upload e checklist
    ├── hooks/useEditalAnalysis.js          chamada HTTP e estado
    └── utils/exportChecklistPdf.js         geração do PDF no navegador

docs/                                       notas técnicas e material de apoio
```

## Limites assumidos

Para manter o pipeline introdutório legível, o projeto usa ingestão síncrona, corte por
tamanho e busca vetorial pura. Ficaram fora do escopo:

- OCR para PDFs digitalizados;
- busca híbrida por palavra-chave e vetor;
- corte por cláusula;
- reranqueamento e deduplicação;
- limpeza automática de cabeçalho e rodapé;
- processamento em fila;
- avaliação automatizada de recall.

Essas limitações e as decisões do estudo estão registradas em [CLAUDE.md](CLAUDE.md).

## Blog

Mais artigos sobre Laravel, PHP, inteligência artificial e desenvolvimento de software
estão em **[paulorb.dev](https://paulorb.dev)**.

## Licença

O projeto declara a licença MIT no `composer.json`.
