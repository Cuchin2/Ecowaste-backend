<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    /**
     * Obtener el wishlist del usuario autenticado.
     */
    public function index()
    {
        $items = auth()->user()->wishlistItems()->with('sku.images')->get();
        return response()->json($items);
    }

    /**
     * Añadir un SKU al wishlist.
     */
    public function add(Request $request)
    {
        $request->validate([
            'sku_id' => 'required|exists:product_skus,id',
        ]);

        $user = auth()->user();
        $existing = $user->wishlistItems()->where('product_sku_id', $request->sku_id)->first();

        if ($existing) {
            return response()->json(['message' => 'Ya está en tu wishlist'], 422);
        }

        $item = $user->wishlistItems()->create([
            'product_sku_id' => $request->sku_id,
        ]);

        return response()->json([
            'message' => 'Añadido a wishlist',
            'item' => $item->load('sku'),
        ], 201);
    }

    /**
     * Eliminar un SKU del wishlist.
     */
    public function remove($skuId)
    {
        $item = auth()->user()->wishlistItems()->where('product_sku_id', $skuId)->firstOrFail();
        $item->delete();

        return response()->json(['message' => 'Eliminado de wishlist']);
    }

    /**
     * Mover un SKU del wishlist al carrito.
     * Elimina el item del wishlist y lo agrega al carrito con cantidad 1.
     */
    public function moveToCart($skuId)
    {
        $user = auth()->user();
        $wishlistItem = $user->wishlistItems()->where('product_sku_id', $skuId)->firstOrFail();

        // Añadir al carrito (o sumar cantidad si ya existe)
        $cartItem = $user->cartItems()->where('product_sku_id', $skuId)->first();
        if ($cartItem) {
            $cartItem->quantity += 1;
            $cartItem->save();
        } else {
            $user->cartItems()->create([
                'product_sku_id' => $skuId,
                'quantity' => 1,
            ]);
        }

        // Eliminar del wishlist
        $wishlistItem->delete();

        return response()->json([
            'message' => 'Producto movido al carrito',
            'items' => $user->cartItems()->with('sku.images')->get(),
        ]);
    }
}