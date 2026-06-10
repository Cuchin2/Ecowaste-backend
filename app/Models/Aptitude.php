<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aptitude extends Model
{
    use HasFactory;

    protected $table = 'aptitudes';

    protected $fillable = [
        'name',
        'description',
        'image_banner',
        'image_tag',
        'type',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];
    public function productVariants()
    {
        return $this->belongsToMany(ColorFlavorProduct::class, 'variant_ingredients', 'ingredient_id', 'color_flavor_product_id');
    }
}