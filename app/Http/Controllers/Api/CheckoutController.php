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
        $orderId = DB::transaction(function () use ($request) {
            
            $saleOrder = SaleOrder::updateOrCreate(
                [
                    'status' => 'CREATE', 
                    'user_id' => $request->user_id ?? auth()->id()
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
                'country_code' => $request->countryCode, // 👈 GUARDAR ID/CÓDIGO
                'address' => $request->address,
                'reference' => $request->reference,
                'city' => $request->city,
                'city_id' => $request->cityId,           // 👈 GUARDAR ID
                'state' => $request->state,
                'state_id' => $request->stateId,         // 👈 GUARDAR ID
                'district' => $request->district,
                'district_id' => $request->districtId,   // 👈 GUARDAR ID
                'zip_code' => $request->zip_code,
                'total' => $request->total,
                'currency' => session('location') == 'PE' ? 'PEN' : 'USD',
                'delivery' => $request->otra === 'true' ? 1 : 0,
                ]
            );

            if ($request->otra === 'true') {
                DeliveryOrder::updateOrCreate(
                    ['order_id' => $saleOrder->id],
                    [
                        'name' => $request->name2,
                        'last_name' => $request->last_name2,
                        'country' => $request->country2,
                        'country_code' => $request->countryCode2, // 👈
                        'address' => $request->address2,
                        'reference' => $request->reference2,
                        'city' => $request->city2,
                        'city_id' => $request->cityId2,           // 👈
                        'state' => $request->state2,
                        'state_id' => $request->stateId2,         // 👈
                        'district' => $request->district2,
                        'district_id' => $request->districtId2,   // 👈
                    ]
                );
            } else {
                DeliveryOrder::updateOrCreate(
                    ['order_id' => $saleOrder->id],
                    [
                    'name' => $request->name,
                    'last_name' => $request->last_name,
                    'country' => $request->country,
                    'country_code' => $request->countryCode,
                    'address' => $request->address,
                    'reference' => $request->reference,
                    'city' => $request->city,
                    'city_id' => $request->cityId,
                    'state' => $request->state,
                    'state_id' => $request->stateId,
                    'district' => $request->district,
                    'district_id' => $request->districtId,
                    ]
                );
            }

            return $saleOrder->id;
        });

        // 👇 Respuesta limpia: solo datos, sin URLs de redirección
        return response()->json([
            'success' => true,
            'order_id' => $orderId,
            'message' => 'Orden creada exitosamente',
        ], 200);
    }
public function current()
{
    $order = SaleOrder::where('user_id', auth()->id())
        ->where('status', 'CREATE')
        ->with('deliveryOrder')
        ->latest()
        ->first();

    if (!$order) {
        return response()->json(['has_draft' => false, 'data' => null]);
    }

    return response()->json([
        'has_draft' => true,
        'data' => [
            'formData' => [
                'name' => $order->name,
                'last_name' => $order->last_name,
                'business' => $order->business ?? '',
                'document_type' => $order->document_type,
                'dni' => $order->dni,
                'phone' => $order->phone,
                'email' => $order->email,
                'address' => $order->address,
                'reference' => $order->reference ?? '',
                'showShippingAddress' => (bool) $order->delivery,
                'name2' => $order->deliveryOrder->name ?? '',
                'last_name2' => $order->deliveryOrder->last_name ?? '',
                'address2' => $order->deliveryOrder->address ?? '',
                'reference2' => $order->deliveryOrder->reference ?? '',
            ],
            'billingLocation' => [
                'countryCode' => $order->country_code, // 👈 DEVOLVER CÓDIGO/ID
                'country' => $order->country,
                'stateId' => $order->state_id,         // 👈 DEVOLVER ID
                'state' => $order->state,
                'cityId' => $order->city_id,           // 👈 DEVOLVER ID
                'city' => $order->city,
                'districtId' => $order->district_id,   // 👈 DEVOLVER ID
                'district' => $order->district,
                'postalCode' => $order->zip_code ?? '',
            ],
            'shippingLocation' => $order->deliveryOrder ? [
                'countryCode' => $order->deliveryOrder->country_code,
                'country' => $order->deliveryOrder->country,
                'stateId' => $order->deliveryOrder->state_id,
                'state' => $order->deliveryOrder->state,
                'cityId' => $order->deliveryOrder->city_id,
                'city' => $order->deliveryOrder->city,
                'districtId' => $order->deliveryOrder->district_id,
                'district' => $order->deliveryOrder->district,
                'postalCode' => '',
            ] : null,
        ]
    ]);
}
}