<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trace extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'level',
    ];
    public function productVariants()
    {
        return $this->belongsToMany(ColorFlavorProduct::class, 'variant_ingredients', 'ingredient_id', 'color_flavor_product_id');
    }
}