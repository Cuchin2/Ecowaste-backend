<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSku extends Model
{
    use HasFactory;

    protected $table = 'product_skus';

    protected $fillable = [
        'product_id',
        'color_flavor_id',
        'size_id',
        'empaque_id',
        'code',
        'name',
        'sell_price',
        'stock',
        'offer',
        'offer_price',
    ];

    protected $casts = [
        'sell_price' => 'decimal:2',
        'offer_price' => 'decimal:2',
        'offer' => 'boolean',
        'stock' => 'integer',
    ];

    // Relaciones
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function colorFlavor()
    {
        return $this->belongsTo(ColorFlavor::class);
    }

    public function size()
    {
        return $this->belongsTo(Size::class);
    }

    public function empaque()
    {
        return $this->belongsTo(Empaque::class);
    }
}