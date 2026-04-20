<?php

// app/Models/Category.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'parent_id', 'level', 'order', 'is_active'];

    protected $casts = [
    'is_active' => 'boolean',
];

    // Relación con el padre
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // Relación con los hijos
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('order');
    }

    // Recursivo para obtener todos los descendientes (opcional)
    public function allChildren()
    {
        return $this->children()->with('allChildren');
    }

    // Scope para categorías raíz (primer nivel)
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }
}