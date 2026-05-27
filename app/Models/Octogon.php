<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Octogon extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'image',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];
}