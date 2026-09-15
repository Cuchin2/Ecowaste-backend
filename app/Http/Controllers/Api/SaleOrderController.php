<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

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
}