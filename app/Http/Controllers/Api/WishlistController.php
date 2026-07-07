<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WishlistItem;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str; // 👈 importar Str

class WishlistController extends Controller
{
    /**
     * Obtener el wishlist del usuario autenticado.
     */
    public function index()
    {
        $items = auth()->user()->wishlistItems()
            ->with(['sku.images', 'sku.colorFlavor', 'sku.empaque', 'sku.product.brand'])
            ->get();
        return response()->json($items);
    }

    /**
     * Añadir un SKU al wishlist.
     */
    public function add(Request $request)
    {
        $request->validate([
            'sku_id'   => 'required|exists:product_skus,id',
            'quantity' => 'sometimes|integer|min:1',
        ]);

        $user = Auth::user();
        $cartToken = $request->header('X-Cart-Token');

        $data = [
            'product_sku_id' => $request->sku_id,
            'quantity'       => $request->quantity ?? 1,
        ];

        if ($user) {
            $data['user_id'] = $user->id;
            // Si hay token, eliminar posible duplicado con token
            if ($cartToken) {
                WishlistItem::where('cart_token', $cartToken)
                    ->where('product_sku_id', $request->sku_id)
                    ->delete();
            }
        } else {
            $data['cart_token'] = $cartToken ?? (string) Str::uuid();
        }

        // Evitar duplicados
        $existing = WishlistItem::where($data)->first();
        if ($existing) {
            $existing->quantity += $request->quantity ?? 1;
            $existing->save();
            return response()->json([
                'message' => 'Cantidad actualizada en wishlist',
                'item'    => $existing->load('sku'),
            ]);
        }

        $item = WishlistItem::create($data);

        return response()->json([
            'message' => 'Añadido a wishlist',
            'item'    => $item->load('sku'),
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
     */
    public function moveToCart(Request $request, $skuId)
    {
        $user = auth()->user();

        // 1. Obtener el item del wishlist
        $wishlistItem = $user->wishlistItems()->where('product_sku_id', $skuId)->firstOrFail();
        $quantity = $wishlistItem->quantity;

        // 2. Obtener o crear carrito del usuario
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        // 3. Agregar al carrito con la cantidad del wishlist
        $cartItem = $cart->items()->where('product_sku_id', $skuId)->first();
        if ($cartItem) {
            $cartItem->quantity += $quantity;
            $cartItem->save();
        } else {
            $cart->items()->create([
                'product_sku_id' => $skuId,
                'quantity'       => $quantity,
            ]);
        }

        // 4. Eliminar del wishlist
        $wishlistItem->delete();

        return response()->json([
            'message' => 'Producto movido al carrito',
            'cart'    => $cart->load('items.sku'),
        ]);
    }
    public function update(Request $request, $skuId)
{
    $request->validate([
        'quantity' => 'required|integer|min:1',
    ]);

    $item = auth()->user()->wishlistItems()->where('product_sku_id', $skuId)->firstOrFail();
    $item->quantity = $request->quantity;
    $item->save();

    return response()->json([
        'message' => 'Cantidad actualizada',
        'item' => $item->load('sku.images'),
    ]);
}
}