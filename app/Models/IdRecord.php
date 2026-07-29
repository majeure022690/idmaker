<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IdRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'data',
        'photo_path',
        'signature_path',
    ];

    protected $casts = [
        'data' => 'array',
    ];
}
