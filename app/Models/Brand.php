<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    ];

    protected $casts = [
        'order' => 'integer',
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
}
