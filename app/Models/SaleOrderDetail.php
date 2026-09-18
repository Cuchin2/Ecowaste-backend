<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleOrderDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_order_id',
        'user_id',
        'name',
        'brand',
        'image',
        'quantity',
        'sell_price',
        'color_flavor',
        'slug',
        'sku',
        'sku_id',
        'product_id',
    ];

    protected $casts = [
        'sell_price' => 'decimal:2',
        'quantity' => 'integer',
    ];

    // Relación con la orden
    public function saleOrder()
    {
        return $this->belongsTo(SaleOrder::class);
    }

    // Relación con el usuario (opcional, pero útil para auditoría)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function details() 
    { 
        return $this->hasMany(SaleOrderDetail::class); 
    }

}