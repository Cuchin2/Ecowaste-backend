<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipping;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ShippingController extends Controller
{
    /**
     * Listar shippings (opcionalmente filtrado por state)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Shipping::query();

        if ($request->has('state')) {
            $query->where('state', $request->state);
        }

        $shippings = $query->orderBy('order')->get();

        return response()->json([
            'success' => true,
            'data' => $shippings
        ]);
    }

    /**
     * Crear nuevo shipping
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order' => 'nullable|integer',
            'price' => 'nullable|numeric|min:0',
            'url' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'state' => 'required|in:district,nacional,internacional',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        // Si no se proporciona order, asignar el siguiente valor
        if (!isset($validated['order'])) {
            $maxOrder = Shipping::where('state', $validated['state'])->max('order');
            $validated['order'] = ($maxOrder ?? 0) + 1;
        }

        $shipping = Shipping::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Shipping creado exitosamente',
            'data' => $shipping
        ], 201);
    }

    /**
     * Mostrar shipping específico
     */
    public function show(Shipping $shipping): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $shipping
        ]);
    }

    /**
     * Actualizar shipping
     */
    public function update(Request $request, Shipping $shipping): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'order' => 'nullable|integer',
            'price' => 'nullable|numeric|min:0',
            'url' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'state' => 'sometimes|required|in:district,nacional,internacional',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $shipping->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Shipping actualizado exitosamente',
            'data' => $shipping
        ]);
    }

    /**
     * Eliminar shipping
     */
    public function destroy(Shipping $shipping): JsonResponse
    {
        $shipping->delete();

        return response()->json([
            'success' => true,
            'message' => 'Shipping eliminado exitosamente'
        ]);
    }

    /**
     * Reordenar shippings
     * Recibe un array de IDs en el orden deseado
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:shippings,id',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['ids'] as $index => $id) {
                Shipping::where('id', $id)->update(['order' => $index + 1]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Shippings reordenados exitosamente'
        ]);
    }
}