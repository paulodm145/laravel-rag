<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    protected $fillable = [
        'original_name',
        'storage_path',
        'status',
        'page_count',
        'extracted_text',
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }
}
