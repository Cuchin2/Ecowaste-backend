<?php

// app/Models/ColorFlavorProduct.php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot; 
use App\Models\Ingredient;
use App\Models\Aptitude;
use App\Models\Trace;
use App\Models\Octogon;

class ColorFlavorProduct extends Pivot  
{
    protected $table = 'color_flavor_product';

    protected $fillable = ['product_id', 'color_flavor_id', 'order'];

    // Relación con Product
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Relación con ColorFlavor
    public function colorFlavor()
    {
        return $this->belongsTo(ColorFlavor::class);
    }

    // ✅ Relaciones con las variantes (con withPivot)
    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'variant_ingredients', 'color_flavor_product_id', 'ingredient_id')
                    ->withPivot('order')
                    ->orderBy('order')
                    ->withTimestamps();
    }

    public function aptitudes()
    {
        return $this->belongsToMany(Aptitude::class, 'variant_aptitudes', 'color_flavor_product_id', 'aptitude_id')
                    ->withPivot('order')
                    ->orderBy('order')
                    ->withTimestamps();
    }

    public function traces()
    {
        return $this->belongsToMany(Trace::class, 'variant_traces', 'color_flavor_product_id', 'trace_id')
                    ->withPivot('order')
                    ->orderBy('order')
                    ->withTimestamps();
    }

    public function octogons()
    {
        return $this->belongsToMany(Octogon::class, 'variant_octogons', 'color_flavor_product_id', 'octogon_id')
                    ->withPivot('order')
                    ->orderBy('order')
                    ->withTimestamps();
    }
}