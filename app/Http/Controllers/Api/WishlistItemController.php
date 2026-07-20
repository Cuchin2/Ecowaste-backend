<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Models\ProductSku;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WishlistItemController extends Controller
{
    /**
     * Obtener todos los items de una wishlist específica.
     */
    public function index(Wishlist $wishlist)
    {
        // Verificar que la lista pertenece al usuario autenticado
        if ($wishlist->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $items = $wishlist->items()
            ->with([
                'sku.product.brand',
                'sku.images',
                'sku.colorFlavor',
                'sku.empaque'
            ])
            ->orderBy('order')
            ->get();

        return response()->json($items);
    }

    /**
     * Añadir un producto (SKU) a una wishlist.
     */
    public function store(Request $request, Wishlist $wishlist)
    {
        if ($wishlist->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'product_sku_id' => 'required|exists:product_skus,id',
            'quantity'       => 'sometimes|integer|min:1|max:999',
            'note'           => 'nullable|string|max:500',
        ]);

        // Si el SKU ya está en la lista, actualizamos la cantidad y nota
        $item = $wishlist->items()->updateOrCreate(
            ['product_sku_id' => $validated['product_sku_id']],
            [
                'quantity' => $validated['quantity'] ?? 1,
                'note'     => $validated['note'] ?? null,
            ]
        );

        // Cargar relaciones para la respuesta
        $item->load([
            'sku.product.brand',
            'sku.images',
            'sku.colorFlavor',
            'sku.empaque'
        ]);

        return response()->json($item, 201);
    }

    /**
     * Actualizar un item específico (cantidad o nota).
     */
    public function update(Request $request, Wishlist $wishlist, WishlistItem $item)
    {
        if ($wishlist->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // Verificar que el item pertenece a la wishlist
        if ($item->wishlist_id !== $wishlist->id) {
            return response()->json(['error' => 'El item no pertenece a esta lista'], 422);
        }

        $validated = $request->validate([
            'quantity' => 'sometimes|integer|min:1|max:999',
            'note'     => 'nullable|string|max:500',
        ]);

        $item->update($validated);

        $item->load([
            'sku.product.brand',
            'sku.images',
            'sku.colorFlavor',
            'sku.empaque'
        ]);

        return response()->json($item);
    }

    /**
     * Eliminar un item de la wishlist.
     */
    public function destroy(Wishlist $wishlist, WishlistItem $item)
    {
        if ($wishlist->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        if ($item->wishlist_id !== $wishlist->id) {
            return response()->json(['error' => 'El item no pertenece a esta lista'], 422);
        }

        $item->delete();

        return response()->json(['message' => 'Item eliminado correctamente']);
    }

    /**
     * Mover un item a otra wishlist.
     */
    public function move(Request $request, Wishlist $wishlist, WishlistItem $item)
    {
        if ($wishlist->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        if ($item->wishlist_id !== $wishlist->id) {
            return response()->json(['error' => 'El item no pertenece a esta lista'], 422);
        }

        $validated = $request->validate([
            'to_wishlist_id' => 'required|exists:wishlists,id',
        ]);

        $targetWishlist = Wishlist::findOrFail($validated['to_wishlist_id']);

        // Verificar que la lista destino también pertenece al usuario
        if ($targetWishlist->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado para mover a esta lista'], 403);
        }

        // Verificar que no se mueva a la misma lista
        if ($targetWishlist->id === $wishlist->id) {
            return response()->json(['error' => 'No puedes mover un item a la misma lista'], 422);
        }

        DB::transaction(function () use ($item, $targetWishlist) {
            // Buscar si el SKU ya existe en la lista destino
            $existing = $targetWishlist->items()
                ->where('product_sku_id', $item->product_sku_id)
                ->first();

            if ($existing) {
                // Sumar cantidades y eliminar el item original
                $existing->quantity += $item->quantity;
                $existing->save();
                $item->delete();
            } else {
                // Mover el item actualizando wishlist_id
                $item->wishlist_id = $targetWishlist->id;
                $item->save();
            }
        });

        return response()->json(['message' => 'Item movido correctamente']);
    }

    /**
     * Mover un item al carrito (y eliminarlo de la wishlist).
     */
    public function moveToCart(Wishlist $wishlist, WishlistItem $item)
    {
        if ($wishlist->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        if ($item->wishlist_id !== $wishlist->id) {
            return response()->json(['error' => 'El item no pertenece a esta lista'], 422);
        }

        $user = auth()->user();

        DB::transaction(function () use ($user, $item) {
            // Obtener o crear carrito del usuario
            $cart = Cart::firstOrCreate(
                ['user_id' => $user->id],
                ['session_id' => null]
            );

            // Buscar si ya existe el SKU en el carrito
            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('product_sku_id', $item->product_sku_id)
                ->first();

            if ($cartItem) {
                $cartItem->quantity += $item->quantity;
                $cartItem->save();
            } else {
                CartItem::create([
                    'cart_id'         => $cart->id,
                    'product_sku_id'  => $item->product_sku_id,
                    'quantity'        => $item->quantity,
                    'price_at_add'    => $item->sku->sell_price ?? 0,
                ]);
            }

            // Eliminar el item de la wishlist
            $item->delete();
        });

        return response()->json(['message' => 'Item movido al carrito correctamente']);
    }

    /**
     * Reordenar los items de una wishlist (drag & drop).
     */
    public function reorder(Request $request, Wishlist $wishlist)
    {
        if ($wishlist->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:wishlist_items,id',
            'items.*.order' => 'required|integer|min:0',
        ]);

        foreach ($validated['items'] as $itemData) {
            WishlistItem::where('id', $itemData['id'])
                ->where('wishlist_id', $wishlist->id)
                ->update(['order' => $itemData['order']]);
        }

        return response()->json(['message' => 'Orden actualizado correctamente']);
    }
}