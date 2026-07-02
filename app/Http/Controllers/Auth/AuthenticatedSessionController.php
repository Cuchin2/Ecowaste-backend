<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Cart;
class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
public function store(LoginRequest $request): Response
{
    $request->authenticate();
    $request->session()->regenerate();

    $user = Auth::user();
    $cartToken = $request->header('X-Cart-Token');

    // Buscar o crear carrito para el usuario (siempre necesario)
    $userCart = Cart::firstOrCreate(['user_id' => $user->id]);

    if ($cartToken) {
        $guestCart = Cart::where('cart_token', $cartToken)->first();
        if ($guestCart) {
            // Fusionar items
            foreach ($guestCart->items as $guestItem) {
                $existingItem = $userCart->items()->where('product_sku_id', $guestItem->product_sku_id)->first();
                if ($existingItem) {
                    $existingItem->quantity += $guestItem->quantity;
                    $existingItem->save();
                } else {
                    $guestItem->cart_id = $userCart->id;
                    $guestItem->save();
                }
            }
            $guestCart->delete();
        }
    }

    return response()->json([
        'user' => $user,
        'cart_token' => $userCart->cart_token, // opcional
    ]);
}

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): Response
    {
        // Revocar token actual
        $user = Auth::user();
        if ($user) {
            $user->tokens()->delete(); // o eliminar específico
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}