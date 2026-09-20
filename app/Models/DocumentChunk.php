<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    protected $fillable = [
        'document_id',
        'source',
        'clause',
        'page',
        'content',
        'embedding',
    ];

    /**
     * O embedding fica fora da serialização. A tool de busca do SDK entrega os
     * models serializados ao agente, e 1536 floats por trecho entupiriam o
     * contexto sem acrescentar nada que o modelo saiba ler.
     */
    protected $hidden = [
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => AsVector::class,
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
