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

        $order = $user->saleOrders()->where('status', 'CREATE')->latest()->first();

        if (!$order) {
            return response()->json(['error' => 'No hay una orden activa para procesar'], 404);
        }

        // 1. Cargamos TODAS las relaciones necesarias para evitar valores null
        $cartItems = CartItem::where('user_id', $user->id)
            ->with(['sku.product.brand', 'sku.colorFlavor.type', 'sku.images']) // 👈 Agregado sku.images
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['error' => 'El carrito está vacío'], 400);
        }

        try {
            DB::transaction(function () use ($order, $cartItems, $user) {
                
                $orderDetails = [];

                foreach ($cartItems as $item) {
                    $sku = $item->sku;
                    $product = $sku->product;
                    $colorFlavor = $sku->colorFlavor;

                    // 2. BRAND: Extraemos solo el nombre (string), no el objeto completo
                    $brandName = 'Sin marca';
                    if (is_object($product->brand)) {
                        $brandName = $product->brand->name ?? 'Sin marca';
                    } elseif (is_string($product->brand)) {
                        $brandName = $product->brand;
                    }

                    // 3. COLOR/FLAVOR: Obtenemos el nombre del tipo o del flavor
                    $colorFlavorString = 'Estándar';
                    if ($colorFlavor) {
                        $colorFlavorString = $colorFlavor->type ? $colorFlavor->type->name : $colorFlavor->name;
                    }

                    // 4. PRECIO: Buscamos en sell_price, luego en price, y si no, 0.00. 
                    // Forzamos a float para evitar errores de tipo.
                    $finalPrice = (float) ($sku->sell_price ?? $sku->price ?? 0.00);

                    // 5. SKU CODE: Buscamos en 'sku', luego 'code', y si no, usamos el ID como string
                    $skuCode = (string) ($sku->sku ?? $sku->code ?? $sku->id);
                    // 👇 NUEVO: Obtener la primera imagen del SKU (ya viene ordenada por 'order')
                    $skuImage = $sku->images->first()?->path;
                    $orderDetails[] = [
                        'sale_order_id' => $order->id,
                        'user_id'       => $user->id,
                        'name'          => $sku->name ?? 'Producto sin nombre',
                        'brand'         => $brandName,          // ✅ Ahora es un string limpio
                        'image'         => $skuImage ?? null,
                        'quantity'      => (int) $item->quantity,
                        'sell_price'    => $finalPrice,         // ✅ Nunca será null
                        'color_flavor'  => $colorFlavorString,
                        'slug'          => $product->slug ?? 'sin-slug',
                        'sku'           => $skuCode,            // ✅ Nunca será null
                        'sku_id'        => (int) $sku->id,
                        'product_id'    => (int) $product->id,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];
                }

                // Insertamos todos los detalles de una vez
                SaleOrderDetail::insert($orderDetails);

                // Actualizamos el estado de la orden
                $order->update(['status' => 'PAID']);

                // Limpiamos el carrito del usuario
                CartItem::where('user_id', $user->id)->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Pedido procesado y pagado exitosamente',
                'order_id' => $order->id
            ]);

        } catch (\Exception $e) {
            // Si algo falla, la transacción se revierte automáticamente
            return response()->json([
                'error' => 'Error al procesar el pedido: ' . $e->getMessage()
            ], 500);
        }
    }
        /**
     * Obtener los detalles completos de una orden específica (Para vista de confirmación/historial)
     */
    /**
     * Listar todas las órdenes del usuario autenticado (Para "Mis compras")
     */
public function index()
{
    $user = Auth::user();

    if (!$user) {
        return response()->json(['error' => 'No autorizado'], 401);
    }

    $orders = SaleOrder::where('user_id', $user->id)
        ->with(['saleDetails', 'shipping', 'deliveryOrder'])
        ->orderBy('created_at', 'desc')
        ->get();

    $ordersData = $orders->map(function($order) {
        $items = $order->saleDetails->map(function($detail) {
            return [
                'id' => $detail->id,
                'name' => $detail->name,
                'brand' => $detail->brand,
                'image' => $detail->image, // Campo correcto de SaleOrderDetail
                'quantity' => (int) $detail->quantity, // Campo correcto
                'price' => (float) $detail->sell_price,
                'subtotal' => (float) ($detail->sell_price * $detail->quantity),
                'color_flavor' => $detail->color_flavor, // Campo correcto
                'sku' => $detail->sku,
                'slug' => $detail->slug,
            ];
        });
        $total=$order->shipping->price + $items->sum('subtotal');
        return [
            'id' => $order->id,
            'status' => $order->status,
            'status_label' => $order->convert(),
            'step' => (int) $order->paso(),
            'created_at' => $order->created_at->format('d/m/Y H:i'),
            'updated_at' => $order->updated_at->format('d/m/Y H:i'),
            'total' => (float) $order->total,
            'shipping' => $order->shipping ? [
                'id' => $order->shipping->id,
                'name' => $order->shipping->name,
                'price' => (float) $order->shipping->price,
                'state' => $order->shipping->state,
            ] : null,
            'address' => [
                'address' => $order->address,
                'reference' => $order->reference,
                'district' => $order->district,
                'city' => $order->city,
                'state' => $order->state,
                'country' => $order->country,
                'zip_code' => $order->zip_code,
            ],
            'name'=>$order->name,
            'lastname'=>$order->lastname,
            'name_delivery'=>$order->deliveryOrder->name, // Nombre del DeliveryOrder
            'lastname_delivery'=>$order->deliveryOrder->last_name, // Apellido del DeliveryOrder
            'items' => $items,
            'total_items' => $items->sum('quantity'),
            'subtotal' => $items->sum('subtotal'),
            'total'=> $total,
            'shipping_cost' => $order->shipping ? (float) $order->shipping->price : 0,
        ];
    });

    return response()->json([
        'success' => true,
        'data' => $ordersData
    ]);
}
}