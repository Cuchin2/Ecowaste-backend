<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SaleOrder;
use App\Models\SaleOrderDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminSaleOrderController extends Controller
{
    /**
     * Listar TODAS las ventas (Panel de Administración)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        // 👇 SIN restricción de user_id para que el admin vea TODAS las órdenes
        $query = SaleOrder::with(['saleDetails', 'shipping', 'deliveryOrder'])
            ->orderBy('created_at', 'desc');

        // Filtro por estado (Ignoramos si viene vacío, 'null' o 'ALL')
        if ($request->filled('status') && $request->status !== 'ALL' && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // Búsqueda por ID, nombre o apellido
        if ($request->filled('search') && strlen(trim($request->search)) > 0) {
            $search = trim($request->search);
            $query->where(function($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        // Paginación
        $perPage = (int) ($request->per_page ?? 15);
        $orders = $query->paginate($perPage);

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
                
                'customer' => [
                    'name' => trim(($order->name ?? '') . ' ' . ($order->last_name ?? ''))
                ],

                'shipping' => $order->shipping ? [
                    'id' => $order->shipping->id,
                    'name' => $order->shipping->name,
                    'price' => $shippingCost,
                    'state' => $order->shipping->state,
                ] : null,
                
                'items' => $items,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $ordersData,
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ]
        ]);
    }

    /**
     * Ver el detalle completo de CUALQUIER orden (Admin)
     */
    public function show($orderId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        // 👇 SIN restricción de user_id
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
     * Cambiar el estado de CUALQUIER orden (Admin)
     */
    public function updateStatus(Request $request, $orderId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $validator = Validator::make($request->all(), [
            'status' => [
                'required',
                'string',
                Rule::in(['CREATE', 'PAID', 'PROCESSING', 'TRACKING', 'DONE', 'CANCEL'])
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // 👇 SIN restricción de user_id para que el admin pueda actualizar cualquier orden
        $order = SaleOrder::find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada'
            ], 404);
        }

        $allowedTransitions = [
            'CREATE' => ['PAID', 'CANCEL'],
            'PAID' => ['PROCESSING', 'CANCEL'],
            'PROCESSING' => ['TRACKING', 'CANCEL'],
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
}