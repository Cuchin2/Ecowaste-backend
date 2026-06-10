<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Ingredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'slug',
    ];

    protected static function boot()
    {
        parent::boot();

        // Al crear: generar slug si está vacío
        static::creating(function ($ingredient) {
            if (empty($ingredient->slug)) {
                $ingredient->slug = Str::slug($ingredient->name);
            }
        });

        // Al actualizar: si cambia el nombre y no se modificó manualmente el slug, regenerar
        static::updating(function ($ingredient) {
            if ($ingredient->isDirty('name') && !$ingredient->isDirty('slug')) {
                $ingredient->slug = Str::slug($ingredient->name);
            }
        });
    }
    public function productVariants()
    {
        return $this->belongsToMany(ColorFlavorProduct::class, 'variant_ingredients', 'ingredient_id', 'color_flavor_product_id');
    }
}