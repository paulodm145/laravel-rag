<?php

namespace App\Ai\Agents;

use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Tools\SimilaritySearch;

// O timeout padrão do agente é 60 segundos, e uma chamada com vários trechos de
// contexto passa disso. Quando estoura, o cliente HTTP lança exceção de conexão e
// o SDK reporta como falha de conexão com o provedor, o que engana.
//
// Não usamos o atributo Temperature: modelos de raciocínio da OpenAI rejeitam esse
// parâmetro, e o schema já restringe o formato da resposta.
#[Provider(Lab::OpenAI)]
#[MaxSteps(10)]
#[Timeout(180)]
class HabilitationAnalyst implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    public function __construct(public Document $document) {}

    public function instructions(): string
    {
        return <<<'PROMPT'
        Você analisa editais de licitação brasileiros e monta a lista de documentos que o
        licitante precisa apresentar para se habilitar.

        Use a ferramenta de busca para localizar os trechos do edital. Faça várias buscas,
        com termos diferentes, antes de responder. Sugestões de termos: habilitação
        jurídica, contrato social, regularidade fiscal, certidão negativa, FGTS, CNDT,
        falência e recuperação judicial, qualificação técnica, atestado de capacidade
        técnica, qualificação econômico-financeira, balanço patrimonial, declarações,
        anexo de documentos de habilitação.

        Regras que você deve seguir sem exceção:

        1. Liste apenas documentos que apareçam explicitamente exigidos no texto que a
           busca devolveu. Não complete a lista com o que a Lei 14.133/2021 normalmente
           exige, nem com o que outros editais costumam pedir. Se o edital não disse, não
           entra.

        2. Para cada documento, copie em "evidence" o trecho do edital que o exige, na
           forma literal em que ele aparece, sem reescrever. Informe em "page" a página
           indicada no resultado da busca.

        3. Quando um trecho apenas remeter a outra parte do edital, por exemplo dizendo
           que os documentos estão previstos em um anexo, isso não é uma exigência. Busque
           novamente para encontrar o anexo e a lista concreta.

        4. Se o edital exigir o mesmo documento em lugares diferentes com redações
           divergentes, ou se uma parte exigir algo que outra não menciona, registre isso
           em "warnings" citando as duas partes. Não escolha um lado.

        5. Marque "requirement" como "condicional" quando a exigência depender da natureza
           do licitante, do porte da empresa ou de alguma hipótese específica, e explique a
           condição em "notes".

        6. Use "confidence" igual a "baixa" quando o trecho recuperado for ambíguo,
           truncado ou insuficiente para afirmar a exigência com segurança.

        7. Se a busca não devolver base suficiente para montar a lista, devolva "documents"
           vazio e explique em "warnings" o que faltou. Não preencha a lista por dedução.

        Escreva em português. Use o nome do documento como ele aparece no edital.
        PROMPT;
    }

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            SimilaritySearch::usingModel(
                model: DocumentChunk::class,
                column: 'embedding',
                minSimilarity: 0.15,
                limit: 8,
                query: fn ($query) => $query->where('document_id', $this->document->id),
            )->withDescription(
                'Busca trechos do edital por similaridade semântica. Receba uma consulta '
                .'em linguagem natural e devolve os trechos mais próximos, com o número '
                .'da página de cada um.'
            ),
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'documents' => $schema->array()->items(
                $schema->object(fn ($schema) => [
                    'name' => $schema->string()->required(),
                    'category' => $schema->string()->enum([
                        'habilitacao_juridica',
                        'regularidade_fiscal_trabalhista',
                        'qualificacao_tecnica',
                        'qualificacao_economico_financeira',
                        'declaracoes',
                        'outros',
                    ])->required(),
                    'requirement' => $schema->string()
                        ->enum(['obrigatorio', 'condicional'])
                        ->required(),
                    'notes' => $schema->string()->required(),
                    'evidence' => $schema->string()->required(),
                    'page' => $schema->integer()->required(),
                    'confidence' => $schema->string()
                        ->enum(['alta', 'media', 'baixa'])
                        ->required(),
                ])
            )->required(),

            'warnings' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
