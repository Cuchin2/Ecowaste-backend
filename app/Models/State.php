<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class State extends Model
{
    protected $table = 'states';

    protected $fillable = [
        'country_id',
        'iso2',
        'name',
        'type',
    ];

    // Relación: Un estado pertenece a un país
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    // Relación: Un estado tiene muchas ciudades
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}