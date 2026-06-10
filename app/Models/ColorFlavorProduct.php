<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ColorFlavorProduct extends Pivot
{
    protected $table = 'color_flavor_product';
    
    // Si no usas timestamps en esta tabla, desactívalos:
    // public $timestamps = false;

    // Relación muchos a muchos con Ingredient
    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'variant_ingredients', 'color_flavor_product_id', 'ingredient_id')
                    ->withTimestamps();
    }

    public function aptitudes()
    {
        return $this->belongsToMany(Aptitude::class, 'variant_aptitudes', 'color_flavor_product_id', 'aptitude_id')
                    ->withTimestamps();
    }

    public function traces()
    {
        return $this->belongsToMany(Trace::class, 'variant_traces', 'color_flavor_product_id', 'trace_id')
                    ->withTimestamps();
    }
}