<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Size extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'tipo_unidad',
        'code',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',  // ← agregado
    ];
    // Opcional: scope para ordenar por defecto
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_size');
    }
    public function skus()
    {
        return $this->hasMany(ProductSku::class);
    }
}
