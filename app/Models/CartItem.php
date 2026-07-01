<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory;
    protected $fillable = ['cart_id', 'product_sku_id', 'quantity', 'price_at_add'];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function sku()
    {
        return $this->belongsTo(ProductSku::class, 'product_sku_id');
    }

    public function getPriceAttribute()
    {
        return $this->price_at_add ?? $this->sku->sell_price;
    }

    public function getSubtotalAttribute()
    {
        return $this->quantity * $this->price;
    }
}