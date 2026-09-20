<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cria a extensão pgvector caso ainda não exista. Precisa vir antes
        // de qualquer coluna do tipo vector.
        Schema::ensureVectorExtensionExists();

        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();

            // Endereço do trecho dentro do documento. Ficam nulos na Parte 1
            // e passam a ser preenchidos quando o documento for fatiado.
            $table->string('source')->nullable();
            $table->string('clause')->nullable();
            $table->unsignedInteger('page')->nullable();

            $table->text('content');

            // index() cria um índice HNSW com distância de cosseno.
            $table->vector('embedding', dimensions: 1536)->index();

            $table->timestamps();
        });

        // Coluna gerada para a busca por palavra-chave. O Postgres recalcula
        // o tsvector sozinho a cada alteração de content, então a aplicação
        // nunca escreve nela. Não há equivalente no Blueprint, daí o SQL cru.
        DB::statement("
            ALTER TABLE document_chunks
            ADD COLUMN content_tsv tsvector
            GENERATED ALWAYS AS (to_tsvector('portuguese', content)) STORED
        ");

        DB::statement('
            CREATE INDEX document_chunks_content_tsv_idx
            ON document_chunks USING GIN (content_tsv)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
