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
    public function products()
    {
        return $this->belongsToMany(Product::class, 'octogon_product');
    }
    public function productVariants()
    {
        return $this->belongsToMany(ColorFlavorProduct::class, 'variant_ingredients', 'ingredient_id', 'color_flavor_product_id');
    }
}