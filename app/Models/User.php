<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens; // ✅ IMPORTANTE
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany; // ← Importante: desde Illuminate
use Spatie\Permission\Traits\HasRoles;
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles; // ✅ AGREGAR HasApiTokens

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Relación uno a uno para obtener la dirección predeterminada.
     */
    public function defaultAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('is_default', true);
    }
}
