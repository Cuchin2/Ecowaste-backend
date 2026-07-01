<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'cart_token'];

    // Generar automáticamente el token al crear un carrito sin token
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($cart) {
            if (empty($cart->cart_token)) {
                $cart->cart_token = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    // Método para obtener o crear carrito activo
    public static function getActiveCart($user = null, $cartToken = null)
    {
        if ($user) {
            return static::firstOrCreate(['user_id' => $user->id]);
        }

        if ($cartToken) {
            $cart = static::where('cart_token', $cartToken)->first();
            if ($cart) {
                return $cart;
            }
        }

        // Si no hay usuario ni token, creamos uno nuevo (se generará token automáticamente)
        return static::create();
    }
}