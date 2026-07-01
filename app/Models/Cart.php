<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'session_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public static function getActiveCart($user = null, $sessionId = null)
    {
        if ($user) {
            return static::firstOrCreate(['user_id' => $user->id]);
        }
        if ($sessionId) {
            return static::firstOrCreate(['session_id' => $sessionId]);
        }
        throw new \Exception('Se requiere usuario o session_id');
    }
}