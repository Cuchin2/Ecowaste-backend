<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class City extends Model
{
    protected $table = 'cities';

    protected $fillable = [
        'country_id',
        'state_id',
        'name',
        'latitude',
        'longitude',
    ];

    // Relación: Una ciudad pertenece a un estado
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    // Relación: Una ciudad pertenece a un país
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}