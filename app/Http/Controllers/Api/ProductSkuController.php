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
public function getByIds(Request $request)
{
    $idsString = $request->query('ids');
    if (empty($idsString)) {
        return response()->json([]);
    }

    $ids = explode(',', $idsString);
    $ids = array_filter($ids, 'is_numeric'); // seguridad
    if (empty($ids)) {
        return response()->json([]);
    }

    $skus = ProductSku::whereIn('id', $ids)
        ->with([
            'images',
            'colorFlavor',
            'empaque',
            'product.brand'
        ])
        ->get();

    return response()->json($skus);
}
public function show(Product $product)
{
    $product->load([
        'category',
        'brand',
        'tags',
        'empaques',
        'octogons',
        'colorFlavors',
        'sizes',
        'skus' => function ($query) {
            $query->where('semaphore', true);
        },
        'skus.images'
    ]);

    // Ordenar SKU
    $orderedColorIds = $product->colorFlavors->pluck('id')->toArray();
    $positionMap = array_flip($orderedColorIds);

    $sortedSkus = $product->skus->sortBy(function ($sku) use ($positionMap) {
        return [
            $positionMap[$sku->color_flavor_id] ?? PHP_INT_MAX,
            $sku->size_id
        ];
    })->values();

    $product->setRelation('skus', $sortedSkus);

    // Cargar relaciones anidadas del pivote
    $product->colorFlavors->each(function ($colorFlavor) {
        $colorFlavor->pivot->load(['ingredients', 'aptitudes', 'traces']);
    });

    // ✅ Devuelve el producto sin formatear
    return response()->json($product);
}
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
        'semaphore'   => 'sometimes|boolean', // 👈 nuevo
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