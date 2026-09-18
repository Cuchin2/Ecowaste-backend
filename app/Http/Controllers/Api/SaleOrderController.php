<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\SaleOrder;
use App\Models\SaleOrderDetail;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
class SaleOrderController extends Controller
{
    /**
     * Obtener la orden activa (estado CREATE) del usuario autenticado
     */
    public function current(): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        // Buscar la orden más reciente con estado 'CREATE'
        $order = $user->saleOrders()
            ->where('status', 'CREATE')
            ->with('shipping') // 👈 Carga la relación del envío asignado
            ->latest()
            ->first();

        if (!$order) {
            return response()->json([
                'data' => null,
                'message' => 'No hay una orden activa'
            ]);
        }

        return response()->json([
            'data' => $order
        ]);
    }
    public function finalizeOrder(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        // 1. Buscar la orden CREATE del usuario
        $order = $user->saleOrders()->where('status', 'CREATE')->latest()->first();

        if (!$order) {
            return response()->json(['error' => 'No hay una orden activa para procesar'], 404);
        }

        // 2. Obtener los items del carrito del usuario con sus relaciones
        $cartItems = CartItem::where('user_id', $user->id)
            ->with(['sku.product', 'sku.colorFlavor.type'])
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['error' => 'El carrito está vacío'], 400);
        }

        try {
            // 3. 🛡️ TRANSACCIÓN DE BASE DE DATOS (Todo o Nada)
            DB::transaction(function () use ($order, $cartItems, $user) {
                
                $orderDetails = [];

                // 4. Crear el SNAPSHOT de cada producto
                foreach ($cartItems as $item) {
                    $sku = $item->sku;
                    $product = $sku->product;
                    $colorFlavor = $sku->colorFlavor;

                    // Construir el string de color_flavor (Ej: "Talla - Rojo" o solo el nombre del tipo)
                    $colorFlavorString = $colorFlavor && $colorFlavor->type 
                        ? $colorFlavor->type->name 
                        : ($colorFlavor ? $colorFlavor->name : null);

                    $orderDetails[] = [
                        'sale_order_id' => $order->id,
                        'user_id'       => $user->id,
                        'name'          => $product->name, // O $sku->name si prefieres
                        'brand'         => $product->brand ?? null,
                        'image'         => $product->image ?? null, // O lógica para imagen del SKU
                        'quantity'      => $item->quantity,
                        'sell_price'    => $sku->price, // Precio congelado en el tiempo
                        'color_flavor'  => $colorFlavorString,
                        'slug'          => $product->slug,
                        'sku'           => $sku->sku_code, // Asumiendo que tu campo se llama así
                        'sku_id'        => $sku->id,
                        'product_id'    => $product->id,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];
                }

                // Insertar todos los detalles de golpe (más eficiente que un loop de creates)
                SaleOrderDetail::insert($orderDetails);

                // 5. Actualizar el estado de la orden a PAID
                $order->update(['status' => 'PAID']);

                // 6. 🧹 Limpiar el carrito del usuario (ya se convirtió en orden)
                CartItem::where('user_id', $user->id)->delete();
            });

            // 7. Disparar eventos de correo aquí (ej: event(new OrderPaid($order));)
            // Por ahora, solo retornamos éxito.

            return response()->json([
                'success' => true,
                'message' => 'Pedido procesado y pagado exitosamente',
                'order_id' => $order->id
            ]);

        } catch (\Exception $e) {
            // Si algo falla, la transacción se revierte automáticamente (Rollback)
            return response()->json([
                'error' => 'Error al procesar el pedido: ' . $e->getMessage()
            ], 500);
        }
    }
}