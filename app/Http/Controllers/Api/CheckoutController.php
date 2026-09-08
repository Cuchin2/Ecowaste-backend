<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest; // 👈 Asegúrate de que este Form Request exista
use App\Models\SaleOrder;               // 👈 Importación necesaria
use App\Models\DeliveryOrder;           // 👈 Importación necesaria
use Illuminate\Support\Facades\DB;      // 👈 Para la transacción

class CheckoutController extends Controller
{
    public function create(CheckoutRequest $request)
    {
        // 👇 Envolver en transacción para garantizar integridad de datos
        $result = DB::transaction(function () use ($request) {
            
            // 1. Crear o Actualizar la Orden de Venta
            $saleOrder = SaleOrder::updateOrCreate(
                [
                    'status' => 'CREATE', 
                    'user_id' => $request->user_id // Asegúrate que el frontend o el auth lo provean
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
                    // ⚠️ Nota: session() en APIs puede ser inestable. 
                    // Mejor si el frontend envía 'currency' o 'location' en el payload.
                    'currency' => session('location') == 'PE' ? 'PEN' : 'USD', 
                    'delivery' => $request->otra == 'true' ? 1 : 0,
                ]
            );

            // 2. Crear o Actualizar la Orden de Entrega
            if ($request->otra == 'true') {
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
                // Si es la misma dirección, duplicamos los datos de facturación en entrega
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

        // 3. Respuesta JSON para el frontend de React
        return response()->json([
            'success' => true,
            'order_id' => $result,
            'redirect_url' => route('web.shop.checkout.shipping', ['id' => $result])
        ], 200); // Código 200 OK
    }
}