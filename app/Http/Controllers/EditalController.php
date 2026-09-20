<?php

namespace App\Http\Controllers;

use App\Ai\Agents\HabilitationAnalyst;
use App\Services\EditalIngestor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class EditalController extends Controller
{
    public function __construct(private EditalIngestor $ingestor) {}

    /**
     * Recebe o PDF, indexa e devolve a lista de documentos de habilitação.
     *
     * É uma requisição longa de propósito: indexar e analisar leva algumas
     * dezenas de segundos, e num projeto de estudo isso vale a simplicidade de
     * ter um único endpoint. Em uso real esse trabalho iria para uma fila.
     */
    public function analyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'edital' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $file = $validated['edital'];
        $path = $file->store('editais', 'local');

        // O caminho absoluto vem do próprio disco. A partir do Laravel 11 a raiz
        // do disco local é storage/app/private, e não storage/app, então montar
        // o caminho à mão aponta para um arquivo que não existe.
        $absolutePath = Storage::disk('local')->path($path);

        try {
            $document = $this->ingestor->ingest(
                path: $absolutePath,
                originalName: $file->getClientOriginalName(),
            );

            $response = HabilitationAnalyst::make(document: $document)
                ->prompt('Monte a lista de documentos de habilitação exigidos por este edital.');

            return response()->json([
                'document' => [
                    'id' => $document->id,
                    'name' => $document->original_name,
                    'pages' => $document->page_count,
                    'chunks' => $document->chunks()->count(),
                ],
                'documents' => $response['documents'] ?? [],
                'warnings' => $response['warnings'] ?? [],
            ]);
        } catch (Throwable $e) {
            Log::error('Falha ao analisar o edital', ['exception' => $e]);

            return response()->json([
                'message' => 'Não foi possível analisar o edital. Consulte os logs da aplicação.',
            ], 500);
        }
    }
}
