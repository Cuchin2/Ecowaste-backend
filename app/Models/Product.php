<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'image',
        'img_nutrition',
        'description',
        'state',
        'category_id',
        'brand_id',
        'code',
        'sell_price',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
        'sell_price' => 'decimal:2',
    ];

    // Relaciones
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
    public function empaques()
    {
        return $this->belongsToMany(Empaque::class, 'empaque_product');
    }
    // Auto-generar slug y code
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            // Slug desde nombre
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
            // Código de 4 dígitos secuencial (0001, 0002...)
            if (empty($product->code)) {
                $maxCode = static::max('code');
                if ($maxCode) {
                    $next = (int)$maxCode + 1;
                    $product->code = str_pad($next, 4, '0', STR_PAD_LEFT);
                } else {
                    $product->code = '0001';
                }
            }
        });

        static::updating(function ($product) {
            if ($product->isDirty('name') && !$product->isDirty('slug')) {
                $product->slug = Str::slug($product->name);
            }
        });
    }
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}