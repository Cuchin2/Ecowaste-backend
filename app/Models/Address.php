<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Address extends Model
{
    //
    protected $fillable = [
        'user_id',
        'name',
        'address_line',
        'reference'
    ];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
