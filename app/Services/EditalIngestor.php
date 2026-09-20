<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Process;
use Laravel\Ai\Embeddings;
use RuntimeException;
use Throwable;

class EditalIngestor
{
    /**
     * Tamanho do pedaço em caracteres, e quanto de sobreposição entre eles.
     * A sobreposição evita que uma exigência que caia exatamente na fronteira
     * seja cortada em duas metades sem sentido.
     */
    private const CHUNK_SIZE = 1200;

    private const CHUNK_OVERLAP = 200;

    private const EMBEDDING_BATCH = 32;

    private const EMBEDDING_ATTEMPTS = 3;

    /**
     * O padrão do SDK é 30 segundos, e um lote de trechos longos passa disso
     * quando a rede oscila. O erro chega disfarçado de falha de conexão com o
     * provedor, e só o stack trace mostra que veio da chamada de embeddings.
     */
    private const EMBEDDING_TIMEOUT = 120;

    /**
     * Extrai, corta, vetoriza e grava o documento.
     *
     * @param  callable|null  $onBatch  Chamado a cada lote de embeddings, para
     *                                  o comando de terminal mostrar progresso.
     */
    public function ingest(string $path, ?string $originalName = null, ?callable $onBatch = null): Document
    {
        $text = $this->extractText($path);
        $pages = $this->extractPages($text);
        $chunks = $this->splitIntoChunks($pages);

        $document = Document::create([
            'original_name' => $originalName ?? basename($path),
            'storage_path' => $path,
            'status' => 'processing',
            'page_count' => count($pages),
            'extracted_text' => trim($text),
        ]);

        try {
            foreach (array_chunk($chunks, self::EMBEDDING_BATCH) as $batch) {
                // O cache usa provedor, modelo, dimensões e conteúdo como chave, então
                // reprocessar o mesmo documento não gera custo novo. Ajuda bastante
                // enquanto se ajusta o tamanho do pedaço.
                $response = retry(
                    self::EMBEDDING_ATTEMPTS,
                    fn () => Embeddings::for(array_column($batch, 'content'))
                        ->timeout(self::EMBEDDING_TIMEOUT)
                        ->cache()
                        ->generate(),
                    sleepMilliseconds: 2000,
                );

                foreach ($batch as $index => $chunk) {
                    $document->chunks()->create([
                        'content' => $chunk['content'],
                        'page' => $chunk['page'],
                        'embedding' => $response->embeddings[$index],
                    ]);
                }

                if ($onBatch) {
                    $onBatch(count($batch));
                }
            }
        } catch (Throwable $e) {
            $document->update(['status' => 'failed']);

            throw $e;
        }

        $document->update(['status' => 'ready']);

        return $document->refresh();
    }

    private function extractText(string $path): string
    {
        $result = Process::timeout(120)->run(['pdftotext', '-layout', $path, '-']);

        if (! $result->successful()) {
            throw new RuntimeException('Falha ao extrair o texto do PDF: '.$result->errorOutput());
        }

        $text = $result->output();

        if (trim($text) === '') {
            throw new RuntimeException(
                'Nenhum texto foi extraído. O PDF provavelmente é digitalizado e precisaria de OCR.'
            );
        }

        return $text;
    }

    /**
     * O pdftotext separa as páginas com um form feed, então quebrar nele dá o
     * número da página de graça, sem depender do rodapé impresso no documento.
     * Cada pedaço fica contido em uma página só.
     *
     * @return string[]
     */
    private function extractPages(string $text): array
    {
        $pages = explode("\f", $text);

        // Algumas versões do pdftotext encerram a saída com form feed. Nesse
        // caso o explode cria uma página vazia que não existe no PDF.
        while ($pages !== [] && trim($pages[array_key_last($pages)]) === '') {
            array_pop($pages);
        }

        return $pages;
    }

    /**
     * @param  string[]  $pages
     * @return array<int, array{content: string, page: int}>
     */
    private function splitIntoChunks(array $pages): array
    {
        $chunks = [];

        foreach ($pages as $index => $pageText) {
            foreach ($this->splitPage($pageText) as $content) {
                $chunks[] = [
                    'content' => $content,
                    'page' => $index + 1,
                ];
            }
        }

        return $chunks;
    }

    /**
     * Corte deliberadamente simples: acumula parágrafos até encher o pedaço e
     * repete o final do anterior como sobreposição. Não entende a estrutura do
     * edital, e para o objetivo deste estudo não precisa.
     *
     * @return string[]
     */
    private function splitPage(string $pageText): array
    {
        $paragraphs = preg_split('/\n\s*\n/', $pageText, flags: PREG_SPLIT_NO_EMPTY) ?: [];

        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($current) + mb_strlen($paragraph) > self::CHUNK_SIZE && $current !== '') {
                $chunks[] = trim($current);
                $current = mb_substr($current, -self::CHUNK_OVERLAP)."\n\n";
            }

            $current .= $paragraph."\n\n";
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }
}
