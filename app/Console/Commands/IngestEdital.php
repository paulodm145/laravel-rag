<?php

namespace App\Console\Commands;

use App\Services\EditalIngestor;
use Illuminate\Console\Command;
use Throwable;

class IngestEdital extends Command
{
    protected $signature = 'edital:ingest {path}';

    protected $description = 'Extract, chunk and embed a PDF into the database';

    public function handle(EditalIngestor $ingestor): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $this->info('Extraindo e vetorizando...');

        try {
            $document = $ingestor->ingest(
                path: $path,
                onBatch: fn () => $this->output->write('.'),
            );
        } catch (Throwable $e) {
            $this->newLine();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine(2);
        $this->info("Documento {$document->id} pronto com {$document->chunks()->count()} pedaços em {$document->page_count} páginas.");

        return self::SUCCESS;
    }
}
