<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductSku;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductSkuController extends Controller
{
    /**
     * Actualizar un SKU específico de un producto.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product       $product
     * @param  \App\Models\ProductSku           $sku
     * @return \Illuminate\Http\JsonResponse
     */
public function update(Request $request, $productId, ProductSku $sku)
{
    // Buscar el producto por ID
    $product = Product::findOrFail($productId);
    
    // Verificar que el SKU pertenece a este producto
    if ($sku->product_id !== $product->id) {
        return response()->json(['error' => 'El SKU no pertenece a este producto'], 404);
    }

    // Validar
    $validated = $request->validate([
        'name'        => 'sometimes|string|max:255',
        'sell_price'  => 'sometimes|numeric|min:0',
        'stock'       => 'sometimes|integer|min:0',
        'offer'       => 'sometimes|boolean',
        'offer_price' => 'nullable|numeric|min:0', // 👈 agregar
    ]);

    $sku->update($validated);
    return response()->json(['message' => 'SKU actualizado correctamente', 'sku' => $sku->fresh()]);
}

    /**
     * Eliminar un SKU específico (opcional, si se necesita).
     */
    public function destroy(Product $product, ProductSku $sku)
    {
        if ($sku->product_id !== $product->id) {
            return response()->json(['error' => 'El SKU no pertenece a este producto'], 404);
        }

        try {
            $sku->delete();
            return response()->json(['message' => 'SKU eliminado correctamente']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al eliminar SKU: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * (Opcional) Crear un SKU individualmente (si se necesita)
     * Normalmente los SKU se generan automáticamente con syncSkus.
     */
    public function store(Request $request, Product $product)
    {
        // Solo por si quieres permitir creación manual, pero no es necesario.
        // Podrías implementarlo si el frontend lo requiere.
    }
}