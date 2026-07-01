<?php
// app/Http/Controllers/Api/WishlistController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WishlistItem;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    /**
     * Obtener el wishlist del usuario o del token de invitado
     */
    public function index(Request $request)
    {
        $items = $this->getWishlistItems($request);
        $items->load('sku.images');

        return response()->json($items);
    }

    /**
     * Añadir un SKU al wishlist
     */
    public function add(Request $request)
    {
        $request->validate([
            'sku_id' => 'required|exists:product_skus,id',
        ]);

        $user = Auth::user();
        $cartToken = $request->header('X-Cart-Token');

        $data = ['product_sku_id' => $request->sku_id];
        if ($user) {
            $data['user_id'] = $user->id;
        } else {
            $data['cart_token'] = $cartToken ?? (string) \Illuminate\Support\Str::uuid();
        }

        // Evitar duplicados
        $existing = WishlistItem::where($data)->first();
        if ($existing) {
            return response()->json(['message' => 'Ya está en tu wishlist'], 422);
        }

        $item = WishlistItem::create($data);

        return response()->json([
            'message' => 'Añadido a wishlist',
            'item' => $item->load('sku'),
        ], 201);
    }

    /**
     * Eliminar un SKU del wishlist
     */
    public function remove(Request $request, $skuId)
    {
        $user = Auth::user();
        $cartToken = $request->header('X-Cart-Token');

        $query = WishlistItem::where('product_sku_id', $skuId);
        if ($user) {
            $query->where('user_id', $user->id);
        } else {
            $query->where('cart_token', $cartToken);
        }

        $item = $query->firstOrFail();
        $item->delete();

        return response()->json(['message' => 'Eliminado de wishlist']);
    }

    /**
     * Mover un SKU del wishlist al carrito
     */
    public function moveToCart(Request $request, $skuId)
    {
        $user = Auth::user();
        $cartToken = $request->header('X-Cart-Token');

        // 1. Verificar que el SKU existe en el wishlist
        $wishlistQuery = WishlistItem::where('product_sku_id', $skuId);
        if ($user) {
            $wishlistQuery->where('user_id', $user->id);
        } else {
            $wishlistQuery->where('cart_token', $cartToken);
        }
        $wishlistItem = $wishlistQuery->firstOrFail();

        // 2. Obtener el carrito activo (usuario o token)
        $cart = Cart::getActiveCart($user, $cartToken);

        // 3. Agregar al carrito (cantidad 1 por defecto)
        $cartItem = $cart->items()->where('product_sku_id', $skuId)->first();
        if ($cartItem) {
            $cartItem->quantity += 1;
            $cartItem->save();
        } else {
            $cart->items()->create([
                'product_sku_id' => $skuId,
                'quantity' => 1,
            ]);
        }

        // 4. Eliminar del wishlist
        $wishlistItem->delete();

        return response()->json([
            'message' => 'Producto movido al carrito',
            'cart' => $cart->load('items.sku'),
        ]);
    }

    /**
     * Obtener los items del wishlist según usuario o token
     */
    private function getWishlistItems(Request $request)
    {
        $user = Auth::user();
        $cartToken = $request->header('X-Cart-Token');

        $query = WishlistItem::with('sku');

        if ($user) {
            $query->where('user_id', $user->id);
        } else {
            $query->where('cart_token', $cartToken);
        }

        return $query->get();
    }
}