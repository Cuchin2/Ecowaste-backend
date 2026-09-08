<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Models\SaleOrder;
use App\Models\DeliveryOrder;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function create(CheckoutRequest $request)
    {
        // DB::transaction garantiza que si falla la creación de DeliveryOrder, 
        // se revierta la creación de SaleOrder (integridad de datos).
        $orderId = DB::transaction(function () use ($request) {
            
            // 1. Crear/Actualizar la Orden de Venta
            $saleOrder = SaleOrder::updateOrCreate(
                [
                    'status' => 'CREATE', 
                    'user_id' => $request->user_id ?? auth()->id() // Fallback al usuario autenticado si no viene en payload
                ],
                [
                    'name' => $request->name,
                    'last_name' => $request->last_name,
                    'business' => $request->business,
                    'document_type' => $request->document_type,
                    'dni' => $request->dni,
                    'phone' => $request->phone,
                    'email' => $request->email,
                    'country' => $request->country,
                    'address' => $request->address,
                    'reference' => $request->reference,
                    'city' => $request->city,
                    'state' => $request->state,
                    'district' => $request->district,
                    'zip_code' => $request->zip_code,
                    'total' => $request->total,
                    'currency' => session('location') == 'PE' ? 'PEN' : 'USD',
                    'delivery' => $request->otra === 'true' ? 1 : 0,
                ]
            );

            // 2. Crear/Actualizar la Orden de Entrega
            // Gracias al CheckoutRequest, si $request->otra === 'true', 
            // estamos 100% seguros de que name2, address2, etc., existen y son válidos.
            if ($request->otra === 'true') {
                DeliveryOrder::updateOrCreate(
                    ['order_id' => $saleOrder->id],
                    [
                        'name' => $request->name2,
                        'last_name' => $request->last_name2,
                        'country' => $request->country2,
                        'address' => $request->address2,
                        'reference' => $request->reference2,
                        'city' => $request->city2,
                        'state' => $request->state2,
                        'district' => $request->district2,
                    ]
                );
            } else {
                DeliveryOrder::updateOrCreate(
                    ['order_id' => $saleOrder->id],
                    [
                        'name' => $request->name,
                        'last_name' => $request->last_name,
                        'country' => $request->country,
                        'address' => $request->address,
                        'reference' => $request->reference,
                        'city' => $request->city,
                        'state' => $request->state,
                        'district' => $request->district,
                    ]
                );
            }

            return $saleOrder->id;
        });

        // 3. Respuesta exitosa para React
        return response()->json([
            'success' => true,
            'order_id' => $orderId,
            'redirect_url' => route('web.shop.checkout.shipping', ['id' => $orderId])
        ], 200);
    }
}