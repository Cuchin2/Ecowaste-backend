<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ColorFlavorType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'order'];

    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }
    public function colorFlavors()
    {
        return $this->hasMany(ColorFlavor::class);
    }
}