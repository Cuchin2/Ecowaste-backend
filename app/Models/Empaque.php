<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empaque extends Model
{
    use HasFactory;

    protected $table = 'empaques';

    protected $fillable = [
        'name',
        'code',
        'tipo',
    ];

    protected $casts = [
        'tipo' => 'boolean',
    ];
    public function products()
    {
        return $this->belongsToMany(Product::class, 'empaque_product');
    }
}
