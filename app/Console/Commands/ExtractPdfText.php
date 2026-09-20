<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class ExtractPdfText extends Command
{
    protected $signature = 'edital:extract {path} {--pages=1}';

    protected $description = 'Extract raw text from a PDF file for inspection';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $info = Process::run(['pdfinfo', $path]);
        $this->line($info->output());

        $result = Process::run([
            'pdftotext',
            '-layout',
            '-f', '1',
            '-l', (string) $this->option('pages'),
            $path,
            '-',
        ]);

        if (! $result->successful()) {
            $this->error($result->errorOutput());

            return self::FAILURE;
        }

        $text = trim($result->output());

        if ($text === '') {
            $this->error(
                'No text extracted. This PDF is probably scanned and would need OCR.'
            );

            return self::FAILURE;
        }

        $this->newLine();
        $this->line($text);

        return self::SUCCESS;
    }
}
