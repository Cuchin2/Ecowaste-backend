<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ColorFlavor;
use App\Models\Size;
use App\Traits\UploadsImages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\ColorFlavorProduct;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    use UploadsImages; // Trait para uploadImage y deleteImage

    private function imageUrl(?string $path): ?string
    {
        return $path ? Storage::url($path) : null;
    }

    private function format(Product $product): Product
    {
        $product->image_url = $this->imageUrl($product->image);
        $product->img_nutrition_url = $this->imageUrl($product->img_nutrition);
        return $product;
    }

    public function index(Request $request)
    {
        $products = Product::with(['category', 'brand'])
            ->orderBy('order')
            ->orderBy('name')
            ->paginate($request->get('per_page', 15));

        // Transformar cada producto para añadir las URLs
        $products->getCollection()->transform(fn($p) => $this->format($p));
        return response()->json($products);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'state'          => 'required|in:erase,public,programer,cancel',
            'category_id'    => 'nullable|exists:categories,id',
            'brand_id'       => 'nullable|exists:brands,id',
            'sell_price'     => 'nullable|numeric|min:0',
            'image'          => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
            'img_nutrition'  => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
        ]);

        try {
            $data['order'] = Product::max('order') + 1;
            if ($request->hasFile('image')) {
                $data['image'] = $this->uploadImage($request->file('image'), 'products');
            }
            if ($request->hasFile('img_nutrition')) {
                $data['img_nutrition'] = $this->uploadImage($request->file('img_nutrition'), 'products');
            }
            $product = Product::create($data);
            return response()->json($this->format($product), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear: ' . $e->getMessage()], 500);
        }
    }

public function show(Product $product)
{
    $product->load([
        'category', 'brand', 'tags', 'empaques', 'octogons',
        'colorFlavors', 'sizes', 'skus'
    ]);

    // Obtener los IDs de los colores en el orden que ya tiene la relación (gracias a orderBy('pivot_order'))
    $orderedColorIds = $product->colorFlavors->pluck('id')->toArray();

    // Mapa de posición para orden rápido
    $positionMap = array_flip($orderedColorIds);

    // Ordenar los SKU según el orden de colores y luego por size_id
    $sortedSkus = $product->skus->sortBy(function ($sku) use ($positionMap) {
        return [
            $positionMap[$sku->color_flavor_id] ?? PHP_INT_MAX,
            $sku->size_id
        ];
    })->values();

    // Reemplazar la relación skus con la colección ordenada
    $product->setRelation('skus', $sortedSkus);

    // Cargar relaciones anidadas del pivote
    $product->colorFlavors->each(function ($colorFlavor) {
        $colorFlavor->pivot->load(['ingredients', 'aptitudes', 'traces']);
    });

    return response()->json($this->format($product));
}

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name'                => 'sometimes|string|max:255',
            'description'         => 'nullable|string',
            'state'               => 'sometimes|in:erase,public,programer,cancel',
            'category_id'         => 'nullable|exists:categories,id',
            'brand_id'            => 'nullable|exists:brands,id',
            'sell_price'          => 'nullable|numeric|min:0',
            'image'               => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
            'img_nutrition'       => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
            'remove_image'        => 'sometimes|boolean',
            'remove_img_nutrition' => 'sometimes|boolean',
            'tag_ids'             => 'sometimes|string',
            'empaque_ids'         => 'sometimes|string', 
            'octogon_ids'        => 'sometimes|string',
            'size_ids' => 'sometimes|string',
            // 👇 Validación para color_flavor_ids
            'color_flavor_ids'    => 'sometimes|string',
        ]);

        try {
            // Manejo de imagen principal
            if ($request->boolean('remove_image') && $product->image) {
                $this->deleteImage($product->image);
                $product->image = null;
                $product->save();
            }
            if ($request->hasFile('image')) {
                if ($product->image) $this->deleteImage($product->image);
                $data['image'] = $this->uploadImage($request->file('image'), 'products');
            } else {
                unset($data['image']);
            }

            // Manejo de imagen nutricional
            if ($request->boolean('remove_img_nutrition') && $product->img_nutrition) {
                $this->deleteImage($product->img_nutrition);
                $product->img_nutrition = null;
                $product->save();
            }
            if ($request->hasFile('img_nutrition')) {
                if ($product->img_nutrition) $this->deleteImage($product->img_nutrition);
                $data['img_nutrition'] = $this->uploadImage($request->file('img_nutrition'), 'products');
            } else {
                unset($data['img_nutrition']);
            }

            $product->update($data);

        // Sincronizar etiquetas
        if ($request->filled('tag_ids')) {
            $tagIds = json_decode($request->input('tag_ids'), true);
            if (is_array($tagIds)) {
                $product->tags()->sync($tagIds);
            }
        }

        // Sincronizar empaques
        if ($request->filled('empaque_ids')) {
            $empaqueIds = json_decode($request->input('empaque_ids'), true);
            if (is_array($empaqueIds)) {
                $product->empaques()->sync($empaqueIds);
            }
        }

        // Sincronizar tamaños
        if ($request->filled('size_ids')) {
            $sizeIds = json_decode($request->input('size_ids'), true);
            if (is_array($sizeIds)) {
                $product->sizes()->sync($sizeIds);
            }
        }

        // Sincronizar octógonos
        if ($request->filled('octogon_ids')) {
            $octogonIds = json_decode($request->input('octogon_ids'), true);
            if (is_array($octogonIds)) {
                $product->octogons()->sync($octogonIds);
            }
        }

        // Sincronizar colores/sabores CON ORDEN
        if ($request->filled('color_flavor_ids')) {
            $colorFlavorIds = json_decode($request->input('color_flavor_ids'), true);
            if (is_array($colorFlavorIds)) {
                $orderedIds = [];
                foreach ($colorFlavorIds as $index => $id) {
                    $orderedIds[$id] = ['order' => $index];
                }
                $product->colorFlavors()->sync($orderedIds);
            }
        }
        //
        // 🔥 GENERAR SKU automáticamente después de sincronizar colores y tamaños
                $product->load(['colorFlavors', 'sizes']);
                $this->syncSkus($product);
        // Procesar  las variantes (ingredientes, octógonos, trazas)
        if ($request->has('variants')) {
            $variantsData = json_decode($request->input('variants'), true);
            if (!is_array($variantsData)) {
                // Si no es un array válido, puedes lanzar una excepción o simplemente ignorarlo
                throw new \Exception('Formato de variantes inválido');
            }
            // Obtener todos los pivotes actuales del producto
            $pivots = ColorFlavorProduct::where('product_id', $product->id)->get()->keyBy('color_flavor_id');

            foreach ($variantsData as $variantData) {
                $colorFlavorId = $variantData['color_flavor_id'] ?? null;
                if (!$colorFlavorId || !isset($pivots[$colorFlavorId])) {
                    continue; // Saltar si no existe el pivot
                }
                $pivot = $pivots[$colorFlavorId];

                // Sincronizar ingredientes
                if (isset($variantData['ingredient_ids']) && is_array($variantData['ingredient_ids'])) {
                    $pivot->ingredients()->sync($variantData['ingredient_ids']);
                }
                // Sincronizar octógonos
                if (isset($variantData['aptitude_ids']) && is_array($variantData['aptitude_ids'])) {
                    $pivot->aptitudes()->sync($variantData['aptitude_ids']);
                }
                // Sincronizar trazas
                if (isset($variantData['trace_ids']) && is_array($variantData['trace_ids'])) {
                    $pivot->traces()->sync($variantData['trace_ids']);
                }
            }
        }
                //
                    return response()->json($this->format($product->fresh()));
                } catch (\Exception $e) {
                    return response()->json(['error' => 'Error al actualizar: ' . $e->getMessage()], 500);
                }
            }

        public function destroy(Product $product)
        {
            try {
                // Eliminar imágenes físicas
                if ($product->image) $this->deleteImage($product->image);
                if ($product->img_nutrition) $this->deleteImage($product->img_nutrition);

                // Eliminar relaciones polimórficas (taggables)
                $product->tags()->detach();

                // Empaques: cascade ya las elimina, pero por claridad:
                $product->empaques()->detach();
                // Sellos: cascade ya las elimina, pero por claridad:
                $product->octogons()->detach();
                // Tamaños: cascade ya laselimina, pero por Claridad:
                $product->sizes()->detach();
                // Colores: cascade ya las elimina, pero por claridad:
                $product->colorFlavors()->detach(); // 👈 Añadir esta línea
                // Eliminar producto
                $product->delete();

                return response()->json(['message' => 'Producto eliminado correctamente']);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Error al eliminar: ' . $e->getMessage()], 500);
            }
        }

    public function reorder(Request $request)
    {
        $request->validate([
            'source_id' => 'required|exists:products,id',
            'target_id' => 'required|exists:products,id',
        ]);

        try {
            $source = Product::find($request->source_id);
            $target = Product::find($request->target_id);

            if (!$source || !$target) {
                return response()->json(['error' => 'Productos no encontrados'], 404);
            }

            // Intercambiar los valores de 'order'
            $tempOrder = $source->order;
            $source->order = $target->order;
            $target->order = $tempOrder;

            $source->save();
            $target->save();

            return response()->json(['message' => 'Orden intercambiado correctamente']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al intercambiar orden: ' . $e->getMessage()], 500);
        }
    }

private function syncSkus(Product $product): void
{
    $product->load(['brand', 'colorFlavors', 'sizes', 'empaques']);

    $colorFlavorIds = $product->colorFlavors->pluck('id')->toArray();
    $sizeIds = $product->sizes->pluck('id')->toArray();
    
    // Obtener el primer empaque (si existe)
    $firstEmpaque = $product->empaques->first();
    $empaqueId = $firstEmpaque?->id;
    $empaqueCode = $firstEmpaque?->code ?? '';

    if (empty($colorFlavorIds) || empty($sizeIds)) {
        $product->skus()->delete();
        return;
    }

    $brandCode = $product->brand->code ?? '';
    $productCode = $product->code ?? '';

    // Combinaciones esperadas
    $combinations = [];
    foreach ($colorFlavorIds as $cfId) {
        foreach ($sizeIds as $sId) {
            $combinations[] = [
                'color_flavor_id' => $cfId,
                'size_id' => $sId,
                'empaque_id' => $empaqueId, // 👈 se asigna el mismo para todos
                'key' => $cfId . '_' . $sId,
            ];
        }
    }

    // SKU existentes indexados por clave
    $existingSkus = $product->skus->keyBy(function ($sku) {
        return $sku->color_flavor_id . '_' . $sku->size_id;
    });

    // ACTUALIZAR SKU existentes (nombre)
    foreach ($existingSkus as $key => $sku) {
        $colorFlavor = $product->colorFlavors->firstWhere('id', $sku->color_flavor_id);
        $size = $product->sizes->firstWhere('id', $sku->size_id);
        if ($colorFlavor && $size) {
            $newName = $this->generateSkuName($product, $colorFlavor, $size);
            if ($sku->name !== $newName) {
                $sku->name = $newName;
                $sku->save();
            }
        }
        // Opcional: actualizar empaque_id si cambió (no lo hacemos para mantener consistencia)
    }

    // CREAR nuevos SKU
    $toCreate = [];
    foreach ($combinations as $combo) {
        $key = $combo['key'];
        if (!isset($existingSkus[$key])) {
            $colorFlavor = ColorFlavor::find($combo['color_flavor_id']);
            $size = Size::find($combo['size_id']);
            $colorCode = $colorFlavor?->code ?? 'COL_' . $combo['color_flavor_id'];
            $sizeCode = $size?->code ?? 'SIZ_' . $combo['size_id'];

            // Generar código con el empaque correspondiente
            $code = $this->generateSkuCode($product, $colorCode, $sizeCode, $empaqueCode);
            $name = $this->generateSkuName($product, $colorFlavor, $size);

            $toCreate[] = [
                'product_id' => $product->id,
                'color_flavor_id' => $combo['color_flavor_id'],
                'size_id' => $combo['size_id'],
                'empaque_id' => $combo['empaque_id'], // 👈 se guarda el ID
                'code' => $code,
                'name' => $name,
                'sell_price' => 0,
                'stock' => 0,
                'offer' => false,
            ];
        }
    }

    if (!empty($toCreate)) {
        $product->skus()->createMany($toCreate);
    }

    // ELIMINAR SKU obsoletos
    $keysToKeep = collect($combinations)->pluck('key')->toArray();
    foreach ($product->skus as $sku) {
        $key = $sku->color_flavor_id . '_' . $sku->size_id;
        if (!in_array($key, $keysToKeep)) {
            $sku->delete();
        }
    }
}
private function generateSkuCode(Product $product, string $colorCode, string $sizeCode, string $empaqueCode): string
{
    $brandCode = $product->brand->code ?? '';
    $productCode = $product->code ?? '';
    // Ejemplo: "EW0001" + "RD" + "B" + "XL" + "_9" = "EW0001RDBXL_9"
    return $brandCode . $productCode . $colorCode . $empaqueCode . $sizeCode;
}
private function generateSkuName(Product $product, ColorFlavor $colorFlavor, Size $size): string
{
    return $product->name . ' ' . $size->name . ' ' . $colorFlavor->name;
}
}