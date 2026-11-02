<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str; 

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'code',
        'image',
        'description',
        'order',
        'is_active',
    ];

    protected $casts = [
        'order' => 'integer',
        'is_active' => 'boolean',
    ];
    // Auto-generate slug from name when creating
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($brand) {
            if (empty($brand->slug)) {
                $brand->slug = Str::slug($brand->name);
            }
        });

        static::updating(function ($brand) {
            if ($brand->isDirty('name') && !$brand->isDirty('slug')) {
                $brand->slug = Str::slug($brand->name);
            }
        });
    }
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
