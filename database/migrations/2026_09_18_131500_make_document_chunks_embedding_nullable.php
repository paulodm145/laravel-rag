<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * O $table->vector() cria a coluna como not null. Isso impede gravar os
     * pedaços do documento antes de gerar os embeddings, que é justamente o
     * fluxo do projeto: a Parte 2 fatia e grava o texto, a Parte 3 preenche
     * os vetores em fila. Por isso a coluna passa a aceitar nulo.
     *
     * O change() do Blueprint não cobre o tipo vector, daí o SQL cru.
     */
    public function up(): void
    {
        DB::statement('
            ALTER TABLE document_chunks
            ALTER COLUMN embedding DROP NOT NULL
        ');
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE document_chunks
            ALTER COLUMN embedding SET NOT NULL
        ');
    }
};
