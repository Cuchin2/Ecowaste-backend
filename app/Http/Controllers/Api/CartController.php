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
    $cart->load('items.sku.images');

    return response()->json([
        'cart' => $cart,
        'cart_token' => $cart->cart_token, // 👈 enviar al frontend
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

        // Buscar por token en header
        $cartToken = $request->header('X-Cart-Token');
        if ($cartToken) {
            $cart = Cart::where('cart_token', $cartToken)->first();
            if ($cart) {
                return $cart;
            }
        }

        // Crear nuevo carrito (el modelo generará cart_token automáticamente)
        return Cart::create();
    }
}