<?php

namespace App\Console\Commands;

use App\Ai\Agents\HabilitationAnalyst;
use App\Models\Document;
use Illuminate\Console\Command;

class AnalyzeEdital extends Command
{
    protected $signature = 'edital:analyze {document?}';

    protected $description = 'Ask the agent for the habilitation checklist of an ingested document';

    public function handle(): int
    {
        $document = $this->argument('document')
            ? Document::query()->where('status', 'ready')->findOrFail($this->argument('document'))
            : Document::query()->where('status', 'ready')->latest()->firstOrFail();

        $this->info("Analisando {$document->original_name}...");

        $response = HabilitationAnalyst::make(document: $document)
            ->prompt('Monte a lista de documentos de habilitação exigidos por este edital.');

        $rows = collect($response['documents'] ?? [])->map(fn (array $item) => [
            $item['name'],
            $item['category'],
            $item['requirement'],
            $item['page'] ?? '',
            $item['confidence'],
        ])->all();

        $this->newLine();
        $this->table(['Documento', 'Categoria', 'Exigência', 'Pág.', 'Confiança'], $rows);

        $warnings = $response['warnings'] ?? [];

        if ($warnings !== []) {
            $this->newLine();
            $this->warn('Alertas:');

            foreach ($warnings as $warning) {
                $this->line(' - '.$warning);
            }
        }

        $this->newLine();
        $this->info(count($rows).' documentos identificados.');

        return self::SUCCESS;
    }
}
