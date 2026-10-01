<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SaleOrder;
use App\Models\SaleOrderDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminSaleOrderController extends Controller
{
    /**
     * Listar todas las ventas (para el dashboard)
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
                'image' => $detail->image,
                'quantity' => (int) $detail->quantity,
                'price' => (float) $detail->sell_price,
                'subtotal' => (float) ($detail->sell_price * $detail->quantity),
                'color_flavor' => $detail->color_flavor,
                'sku' => $detail->sku,
            ];
        });

        // ✅ 1. CÁLCULO SEGURO: Si no hay envío, el costo es 0
        $shippingCost = $order->shipping ? (float) $order->shipping->price : 0;
        $subtotal = $items->sum('subtotal');
        $total = $subtotal + $shippingCost;

        return [
            'id' => $order->id,
            'status' => $order->status,
            'status_label' => $order->convert(),
            'step' => (int) $order->paso(),
            'created_at' => $order->created_at->format('d/m/Y H:i'),
            'updated_at' => $order->updated_at->format('d/m/Y H:i'),
            'total' => (float) $total,
            
            // ✅ 2. Datos planos que espera tu componente ShopDropdow
            'name' => $order->name ?? '',
            'lastname' => $order->last_name ?? '',
            'name_delivery' => $order->deliveryOrder ? $order->deliveryOrder->name : ($order->name ?? ''),
            'lastname_delivery' => $order->deliveryOrder ? $order->deliveryOrder->last_name : ($order->last_name ?? ''),

            'shipping' => $order->shipping ? [
                'id' => $order->shipping->id,
                'name' => $order->shipping->name,
                'price' => $shippingCost,
                'state' => $order->shipping->state,
            ] : null,
            
            'address' => [
                'address' => $order->address ?? '',
                'reference' => $order->reference,
                'district' => $order->district ?? '',
                'city' => $order->city ?? '',
                'state' => $order->state ?? '',
                'country' => $order->country ?? '',
            ],
            
            'items' => $items,
        ];
    });

    return response()->json([
        'success' => true,
        'data' => $ordersData
    ]);
}

    /**
     * Ver el detalle completo de una orden
     */
    public function show($orderId)
    {
        $order = SaleOrder::with([
            'saleDetails',
            'shipping',
            'deliveryOrder',
            'user' => function($q) {
                $q->select('id', 'name', 'email');
            }
        ])->find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada'
            ], 404);
        }

        $items = $order->saleDetails->map(function($detail) {
            return [
                'id' => $detail->id,
                'name' => $detail->name,
                'brand' => $detail->brand,
                'image' => $detail->image,
                'quantity' => (int) $detail->quantity,
                'price' => (float) $detail->sell_price,
                'subtotal' => (float) ($detail->sell_price * $detail->quantity),
                'color_flavor' => $detail->color_flavor,
                'sku' => $detail->sku,
                'slug' => $detail->slug,
            ];
        });

        $subtotal = $items->sum('subtotal');
        $shippingCost = $order->shipping ? (float) $order->shipping->price : 0;
        $total = $subtotal + $shippingCost;

        return response()->json([
            'success' => true,
            'data' => [
                'order' => [
                    'id' => $order->id,
                    'status' => $order->status,
                    'status_label' => $order->convert(),
                    'step' => (int) $order->paso(),
                    'created_at' => $order->created_at->format('d/m/Y H:i'),
                    'updated_at' => $order->updated_at->format('d/m/Y H:i'),
                ],
                'customer' => [
                    'id' => $order->user->id ?? null,
                    'name' => trim($order->name . ' ' . $order->last_name),
                    'email' => $order->email,
                    'phone' => $order->phone,
                    'document_type' => $order->document_type,
                    'dni' => $order->dni,
                    'business' => $order->business,
                ],
                'billing_address' => [
                    'address' => $order->address,
                    'reference' => $order->reference,
                    'district' => $order->district,
                    'district_id' => $order->district_id,
                    'city' => $order->city,
                    'city_id' => $order->city_id,
                    'state' => $order->state,
                    'state_id' => $order->state_id,
                    'country' => $order->country,
                    'country_code' => $order->country_code,
                    'zip_code' => $order->zip_code,
                ],
                'shipping_address' => $order->deliveryOrder ? [
                    'name' => trim($order->deliveryOrder->name . ' ' . $order->deliveryOrder->last_name),
                    'address' => $order->deliveryOrder->address,
                    'reference' => $order->deliveryOrder->reference,
                    'district' => $order->deliveryOrder->district,
                    'district_id' => $order->deliveryOrder->district_id,
                    'city' => $order->deliveryOrder->city,
                    'city_id' => $order->deliveryOrder->city_id,
                    'state' => $order->deliveryOrder->state,
                    'state_id' => $order->deliveryOrder->state_id,
                    'country' => $order->deliveryOrder->country,
                    'country_code' => $order->deliveryOrder->country_code,
                ] : null,
                'shipping' => $order->shipping ? [
                    'id' => $order->shipping->id,
                    'name' => $order->shipping->name,
                    'title' => $order->shipping->title,
                    'price' => $shippingCost,
                    'state' => $order->shipping->state,
                ] : null,
                'items' => $items,
                'summary' => [
                    'subtotal' => $subtotal,
                    'shipping_cost' => $shippingCost,
                    'total' => $total,
                    'total_items' => $items->sum('quantity'),
                    'currency' => $order->currency ?? 'PEN',
                ]
            ]
        ]);
    }

    /**
     * Cambiar el estado de una orden
     */
    public function updateStatus(Request $request, $orderId)
    {
        // Validar datos
        $validator = Validator::make($request->all(), [
            'status' => [
                'required',
                'string',
                Rule::in(['CREATE', 'PAID', 'PROCESSING', 'TRACKING', 'DONE', 'CANCEL']) // 👈 Agregado PROCESSING
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $order = SaleOrder::find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada'
            ], 404);
        }

        // Validar transiciones de estado permitidas
        $allowedTransitions = [
            'CREATE' => ['PAID', 'CANCEL'],
            'PAID' => ['PROCESSING', 'CANCEL'], // 👈 Ahora PAID puede ir a PROCESSING
            'PROCESSING' => ['TRACKING', 'CANCEL'], // 👈 NUEVO: PROCESSING puede ir a TRACKING o CANCEL
            'TRACKING' => ['DONE', 'CANCEL'],
            'DONE' => [],
            'CANCEL' => [],
        ];

        $currentStatus = $order->status;
        $newStatus = $request->status;

        if (!in_array($newStatus, $allowedTransitions[$currentStatus] ?? [])) {
            return response()->json([
                'success' => false,
                'message' => "No se puede cambiar el estado de {$currentStatus} a {$newStatus}"
            ], 422);
        }

        // Actualizar el estado
        $order->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado exitosamente',
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
                'status_label' => $order->convert(),
                'step' => (int) $order->paso(),
                'updated_at' => $order->updated_at->format('d/m/Y H:i'),
            ]
        ]);
    }

    /**
     * Obtener estadísticas de ventas (opcional, para dashboard)
     */
    public function statistics(Request $request)
    {
        // Validar rango de fechas
        $validator = Validator::make($request->all(), [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = SaleOrder::where('status', '!=', 'CANCEL');

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $totalOrders = $query->count();
        $totalRevenue = $query->sum('total');
        
        $ordersByStatus = SaleOrder::selectRaw('status, COUNT(*) as count')
            ->when($request->filled('date_from'), function($q) use ($request) {
                $q->whereDate('created_at', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function($q) use ($request) {
                $q->whereDate('created_at', '<=', $request->date_to);
            })
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'success' => true,
            'data' => [
                'total_orders' => $totalOrders,
                'total_revenue' => (float) $totalRevenue,
                'average_order_value' => $totalOrders > 0 ? (float) ($totalRevenue / $totalOrders) : 0,
                'orders_by_status' => $ordersByStatus,
            ]
        ]);
    }
}