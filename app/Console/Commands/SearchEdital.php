<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Console\Command;

class SearchEdital extends Command
{
    protected $signature = 'edital:search {query} {document?} {--limit=5}';

    protected $description = 'Search the ingested chunks by similarity';

    public function handle(): int
    {
        $query = $this->argument('query');
        $document = $this->argument('document')
            ? Document::query()->where('status', 'ready')->findOrFail($this->argument('document'))
            : Document::query()->where('status', 'ready')->latest()->firstOrFail();

        // O whereVectorSimilarTo aceita a string direta: o Laravel gera o
        // embedding da busca por conta própria e ordena por similaridade.
        $chunks = DocumentChunk::query()
            ->where('document_id', $document->id)
            ->whereVectorSimilarTo('embedding', $query, minSimilarity: 0.2)
            ->limit((int) $this->option('limit'))
            ->get();

        if ($chunks->isEmpty()) {
            $this->warn('Nenhum trecho encontrado. Tente baixar o minSimilarity.');

            return self::SUCCESS;
        }

        foreach ($chunks as $chunk) {
            $this->newLine();
            $this->line("<fg=yellow>--- página {$chunk->page} · pedaço #{$chunk->id} ---</>");
            $this->line($chunk->content);
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
