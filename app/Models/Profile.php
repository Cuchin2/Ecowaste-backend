<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Profile extends Model
{
    protected $fillable = [
        'user_id',
        'paternal_lastname',
        'maternal_lastname',
        'document_type',
        'document_number',
        'phone',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
