<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
public function index(Request $request)
{
    $cart = $this->getCart($request);
    // Cargar items con sus SKU y las imágenes de cada SKU
    $cart->load(['items.sku.images']); // 👈 así cargas las imágenes

    return response()->json([
        'cart' => $cart,
    ]);
}

    public function addItem(Request $request)
    {
        $request->validate([
            'sku_id' => 'required|exists:product_skus,id',
            'quantity' => 'required|integer|min:1',
            'price_at_add' => 'nullable|numeric|min:0',
        ]);

        $cart = $this->getCart($request);
        $skuId = $request->sku_id;
        $quantity = $request->quantity;

        $item = $cart->items()->where('product_sku_id', $skuId)->first();
        if ($item) {
            $item->quantity += $quantity;
            $item->save();
        } else {
            $cart->items()->create([
                'product_sku_id' => $skuId,
                'quantity' => $quantity,
                'price_at_add' => $request->price_at_add,
            ]);
        }

        return response()->json(['message' => 'Producto añadido al carrito', 'cart' => $cart->load('items.sku')]);
    }

    public function updateItem(Request $request, $itemId)
    {
        $request->validate(['quantity' => 'required|integer|min:0']);
        $cart = $this->getCart($request);
        $item = $cart->items()->findOrFail($itemId);

        if ($request->quantity == 0) {
            $item->delete();
        } else {
            $item->quantity = $request->quantity;
            $item->save();
        }

        return response()->json(['message' => 'Item actualizado', 'cart' => $cart->load('items.sku')]);
    }

    public function removeItem(Request $request, $itemId)
    {
        $cart = $this->getCart($request);
        $item = $cart->items()->findOrFail($itemId);
        $item->delete();
        return response()->json(['message' => 'Item eliminado', 'cart' => $cart->load('items.sku')]);
    }

    public function clear(Request $request)
    {
        $cart = $this->getCart($request);
        $cart->items()->delete();
        return response()->json(['message' => 'Carrito vaciado', 'cart' => $cart->load('items.sku')]);
    }

    private function getCart(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            return Cart::firstOrCreate(['user_id' => $user->id]);
        }
        $sessionId = $request->session()->getId();
        return Cart::firstOrCreate(['session_id' => $sessionId]);
    }
}