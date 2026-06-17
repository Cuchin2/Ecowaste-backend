<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    use HasFactory;

    protected $fillable = ['path', 'alt'];

public function productSkus()
{
    return $this->belongsToMany(ProductSku::class, 'product_sku_images')
                ->withPivot('order')
                ->orderBy('order'); // ✅
}
}