<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $table = 'countries';

    protected $fillable = [
        'iso2',
        'name',
        'iso3',
        'phone_code',
        'currency',
        'currency_name',
        'currency_symbol',
        'flag',
        'region',
        'subregion',
    ];

    // Relación: Un país tiene muchos estados/provincias
    public function states(): HasMany
    {
        return $this->hasMany(State::class);
    }

    // Relación: Un país tiene muchas ciudades (acceso directo)
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}