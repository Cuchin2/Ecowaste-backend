<?php

// app/Models/WishlistItem.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WishlistItem extends Model
{
    protected $fillable = [
        'wishlist_id', 'product_sku_id', 'quantity', 'note', 'order'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'order' => 'integer',
    ];

    public function wishlist()
    {
        return $this->belongsTo(Wishlist::class);
    }

    public function sku()
    {
        return $this->belongsTo(ProductSku::class, 'product_sku_id');
    }
}