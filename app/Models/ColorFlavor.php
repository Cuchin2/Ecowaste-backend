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
        'order',
    ];

    protected $casts = [
        'type' => 'string',
        'order' => 'integer', 
    ];
        // Scope para ordenar por defecto
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
    public function products()
    {
        return $this->belongsToMany(Product::class, 'color_flavor_product')
                    ->withPivot('order');
    }
    public function skus()
    {
        return $this->hasMany(ProductSku::class);
    }
}
