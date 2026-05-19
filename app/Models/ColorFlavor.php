<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ColorFlavor extends Model
{
    use HasFactory;

    protected $table = 'color_flavor'; // opcional si la tabla no sigue convención plural

    protected $fillable = [
        'name',
        'hex',
        'code',
        'type',
    ];

    protected $casts = [
        'type' => 'string',
    ];
}
