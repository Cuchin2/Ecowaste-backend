<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empaque extends Model
{
    use HasFactory;

    protected $table = 'empaques';

    protected $fillable = [
        'name',
        'code',
        'tipo',
        'order', 
    ];
    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }
    protected $casts = [
        'tipo' => 'boolean',
        'order' => 'integer',
    ];
    public function products()
    {
        return $this->belongsToMany(Product::class, 'empaque_product');
    }
    public function skus()
    {
        return $this->hasMany(ProductSku::class);
    }
}
