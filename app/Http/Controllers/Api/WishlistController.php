<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WishlistItem;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $items = $this->getWishlistItems($request);
        $items->load('sku.images'); // 👈 así cargas las imágenes

        return response()->json($items);
    }

    public function add(Request $request)
    {
        $request->validate(['sku_id' => 'required|exists:product_skus,id']);
        $user = Auth::user();
        $sessionId = $request->session()->getId();

        $data = ['product_sku_id' => $request->sku_id];
        if ($user) $data['user_id'] = $user->id;
        else $data['session_id'] = $sessionId;

        $existing = WishlistItem::where($data)->first();
        if ($existing) {
            return response()->json(['message' => 'Ya está en tu wishlist'], 422);
        }

        $item = WishlistItem::create($data);
        return response()->json(['message' => 'Añadido a wishlist', 'item' => $item->load('sku')], 201);
    }

    public function remove(Request $request, $skuId)
    {
        $user = Auth::user();
        $sessionId = $request->session()->getId();

        $query = WishlistItem::where('product_sku_id', $skuId);
        if ($user) $query->where('user_id', $user->id);
        else $query->where('session_id', $sessionId);

        $item = $query->firstOrFail();
        $item->delete();
        return response()->json(['message' => 'Eliminado de wishlist']);
    }

    public function moveToCart(Request $request, $skuId)
    {
        $user = Auth::user();
        $sessionId = $request->session()->getId();

        $wishlistQuery = WishlistItem::where('product_sku_id', $skuId);
        if ($user) $wishlistQuery->where('user_id', $user->id);
        else $wishlistQuery->where('session_id', $sessionId);

        $wishlistItem = $wishlistQuery->firstOrFail();

        $cart = Cart::getActiveCart($user, $sessionId);
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

        $wishlistItem->delete();

        return response()->json([
            'message' => 'Producto movido al carrito',
            'cart' => $cart->load('items.sku'),
        ]);
    }

    private function getWishlistItems(Request $request)
    {
        $user = Auth::user();
        $sessionId = $request->session()->getId();

        $query = WishlistItem::with('sku');
        if ($user) $query->where('user_id', $user->id);
        else $query->where('session_id', $sessionId);

        return $query->get();
    }
}