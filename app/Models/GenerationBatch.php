<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GenerationBatch extends Model
{
    protected $fillable = [
        'name',
        'template_id',
        'record_ids',
        'print_config',
        'pdf_path',
        'generated_at',
    ];

    protected $casts = [
        'record_ids' => 'array',
        'print_config' => 'array',
        'generated_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
