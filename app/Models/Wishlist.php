<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    protected $fillable = ['user_id', 'name', 'slug', 'is_default', 'is_public', 'description', 'order'];
    
    public function user() { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(WishlistItem::class)->orderBy('order'); }
    public function shares() { return $this->hasMany(WishlistShare::class); }
    
    // Scope para lista por defecto
    public function scopeDefault($query) {
        return $query->where('is_default', true);
    }

}
