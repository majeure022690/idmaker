<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $fillable = [
        'name',
        'width',
        'height',
        'unit',
        'has_back',
        'front_design',
        'back_design',
    ];

    protected $casts = [
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'has_back' => 'boolean',
        'front_design' => 'array',
        'back_design' => 'array',
    ];
}
