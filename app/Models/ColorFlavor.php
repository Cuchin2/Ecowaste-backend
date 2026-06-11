<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ColorFlavor extends Model
{
    use HasFactory;

    protected $table = 'color_flavor'; // opcional si la tabla no sigue convención plural

    protected $fillable = [
        'name',
        'hex',
        'code',
        'type',
    ];

    protected $casts = [
        'type' => 'string',
    ];
    public function products()
    {
        return $this->belongsToMany(Product::class, 'color_flavor_product')
                    ->withPivot('order');
    }
    public function skus()
    {
        return $this->hasMany(ProductSku::class);
    }
}
