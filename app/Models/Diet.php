<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Diet extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'image',
        'description',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];
}