<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    /**
     * Obtener el carrito del usuario autenticado.
     */
    public function index()
    {
        $items = auth()->user()->cartItems()->with('sku.images')->get();
        return response()->json(['items' => $items]);
    }

    /**
     * Añadir un item al carrito (o actualizar cantidad si ya existe).
     */
    public function addItem(Request $request)
    {
        $request->validate([
            'sku_id' => 'required|exists:product_skus,id',
            'quantity' => 'required|integer|min:1',
            'price_at_add' => 'nullable|numeric|min:0',
        ]);

        $user = auth()->user();
        $item = $user->cartItems()->where('product_sku_id', $request->sku_id)->first();

        if ($item) {
            $item->quantity += $request->quantity;
            $item->save();
        } else {
            $user->cartItems()->create([
                'product_sku_id' => $request->sku_id,
                'quantity' => $request->quantity,
                'price_at_add' => $request->price_at_add,
            ]);
        }

        return response()->json([
            'message' => 'Item añadido/actualizado en el carrito',
            'items' => $user->cartItems()->with('sku.images')->get(),
        ]);
    }

    /**
     * Actualizar la cantidad de un item específico.
     */
    public function updateItem(Request $request, $itemId)
    {
        $request->validate(['quantity' => 'required|integer|min:0']);
        $item = auth()->user()->cartItems()->findOrFail($itemId);

        if ($request->quantity == 0) {
            $item->delete();
        } else {
            $item->quantity = $request->quantity;
            $item->save();
        }

        return response()->json([
            'message' => 'Item actualizado',
            'items' => auth()->user()->cartItems()->with('sku.images')->get(),
        ]);
    }

    /**
     * Eliminar un item del carrito.
     */
    public function removeItem($itemId)
    {
        $item = auth()->user()->cartItems()->findOrFail($itemId);
        $item->delete();

        return response()->json([
            'message' => 'Item eliminado',
            'items' => auth()->user()->cartItems()->with('sku.images')->get(),
        ]);
    }

    /**
     * Vaciar todo el carrito del usuario.
     */
    public function clear()
    {
        auth()->user()->cartItems()->delete();
        return response()->json(['message' => 'Carrito vaciado']);
    }

    /**
     * Sincronizar el carrito local (desde el frontend) con el backend.
     * Se usa al hacer login para fusionar el carrito de invitado.
     */
    public function sync(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.sku_id' => 'required|exists:product_skus,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $user = auth()->user();
        foreach ($request->items as $localItem) {
            $item = $user->cartItems()->where('product_sku_id', $localItem['sku_id'])->first();
            if ($item) {
                $item->quantity += $localItem['quantity'];
                $item->save();
            } else {
                $user->cartItems()->create([
                    'product_sku_id' => $localItem['sku_id'],
                    'quantity' => $localItem['quantity'],
                ]);
            }
        }

        return response()->json([
            'message' => 'Carrito sincronizado',
            'items' => $user->cartItems()->with('sku.images')->get(),
        ]);
    }
}