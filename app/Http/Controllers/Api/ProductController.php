<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\ColorFlavor;
use App\Models\Size;
use App\Models\Empaque;
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
            ->search($request->search) 
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
            'category', 'brand', 'tags', 'empaques',
            'colorFlavors', 'sizes', 'skus','skus.images'
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
            $colorFlavor->pivot->load(['ingredients', 'aptitudes', 'traces','octogons']);
        });

        return response()->json($this->format($product));
    }

    public function update(Request $request, Product $product)
    {
        return response()->json([
            'message' => 'Datos recibidos',
            'data' => $request->all()
        ], 200);
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
            $tagData = json_decode($request->input('tag_ids'), true);
            if (is_array($tagData)) {
                $synced = [];
                foreach ($tagData as $item) {
                    $synced[$item['id']] = ['order' => $item['order']];
                }
                $product->tags()->sync($synced);
            }
        }

        // Sincronizar empaques
        if ($request->filled('empaque_ids')) {
            $empaqueIds = json_decode($request->input('empaque_ids'), true);
            if (is_array($empaqueIds)) {
                $synced = [];
                foreach ($empaqueIds as $item) {
                    $synced[$item['id']] = ['order' => $item['order']];
                }
                $product->empaques()->sync($synced);
            }
        }

        // Sincronizar tamaños
        if ($request->filled('size_ids')) {
            $sizeIds = json_decode($request->input('size_ids'), true);
            if (is_array($sizeIds)) {
                $synced = [];
                foreach ($sizeIds as $item) {
                    $synced[$item['id']] = ['order' => $item['order']];
                }
                $product->sizes()->sync($synced);
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
        // Procesar variantes (ingredientes, aptitudes, trazas, octógonos)
        if ($request->has('variants')) {
            $variantsData = json_decode($request->input('variants'), true);
            if (!is_array($variantsData)) {
                throw new \Exception('Formato de variantes inválido');
            }

            $pivots = ColorFlavorProduct::where('product_id', $product->id)->get()->keyBy('color_flavor_id');

            foreach ($variantsData as $variantData) {
                $colorFlavorId = $variantData['color_flavor_id'] ?? null;
                if (!$colorFlavorId || !isset($pivots[$colorFlavorId])) {
                    continue;
                }
                $pivot = $pivots[$colorFlavorId];

                // Sincronizar con orden
                $relations = ['ingredients', 'aptitudes', 'traces', 'octogons'];
                foreach ($relations as $rel) {
                    $key = $rel . '_ids'; // ingredient_ids, aptitude_ids, etc.
                    if (isset($variantData[$key]) && is_array($variantData[$key])) {
                        $this->syncPivotRelation($pivot, $rel, $variantData[$key]);
                    }
                }
            }
        }
        //
            return response()->json($this->format($product->fresh()));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }
    private function syncPivotRelation($pivot, string $relation, ?array $data): void
    {
        if (empty($data)) {
            $pivot->$relation()->sync([]);
            return;
        }

        $synced = [];
        foreach ($data as $item) {
            if (isset($item['id'], $item['order'])) {
                $synced[$item['id']] = ['order' => (int) $item['order']];
            }
        }
        $pivot->$relation()->sync($synced);
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
    $empaqueIds = $product->empaques->pluck('id')->toArray();

    // Si no hay colores o tamaños, eliminar todos los SKU
    if (empty($colorFlavorIds) || empty($sizeIds)) {
        $product->skus()->delete();
        return;
    }

    // Si no hay empaques, generamos SKU sin empaque (empaque_id = null)
    if (empty($empaqueIds)) {
        $empaqueIds = [null];
    }

    $brandCode = $product->brand->code ?? '';
    $productCode = $product->code ?? '';

    // Obtener todos los objetos de empaque (para código y nombre)
    $empaquesMap = $product->empaques->keyBy('id');

    // Generar todas las combinaciones: color × size × empaque
    $combinations = [];
    foreach ($colorFlavorIds as $cfId) {
        foreach ($sizeIds as $sId) {
            foreach ($empaqueIds as $eId) {
                $key = $cfId . '_' . $sId . '_' . ($eId ?? 'null');
                $combinations[] = [
                    'color_flavor_id' => $cfId,
                    'size_id' => $sId,
                    'empaque_id' => $eId,
                    'key' => $key,
                ];
            }
        }
    }

    // Obtener SKU existentes indexados por clave compuesta (incluyendo empaque)
    $existingSkus = $product->skus->keyBy(function ($sku) {
        return $sku->color_flavor_id . '_' . $sku->size_id . '_' . ($sku->empaque_id ?? 'null');
    });

    $toCreate = [];
    foreach ($combinations as $combo) {
        $key = $combo['key'];
        if (!isset($existingSkus[$key])) {
            $colorFlavor = ColorFlavor::find($combo['color_flavor_id']);
            $size = Size::find($combo['size_id']);
            $empaque = $combo['empaque_id'] ? $empaquesMap[$combo['empaque_id']] ?? null : null;

            $colorCode = $colorFlavor?->code ?? 'COL_' . $combo['color_flavor_id'];
            $sizeCode = $size?->code ?? 'SIZ_' . $combo['size_id'];
            $empaqueCode = $empaque?->code ?? '';

            $code = $this->generateSkuCode($product, $colorCode, $sizeCode, $empaqueCode);
            $name = $this->generateSkuName($product, $colorFlavor, $size, $empaque);

            $toCreate[] = [
                'product_id' => $product->id,
                'color_flavor_id' => $combo['color_flavor_id'],
                'size_id' => $combo['size_id'],
                'empaque_id' => $combo['empaque_id'],
                'code' => $code,
                'name' => $name,
                'sell_price' => 0,
                'stock' => 0,
                'offer' => false,
            ];
        }
    }

    // Eliminar SKU obsoletos (los que ya no están en las combinaciones actuales)
    $keysToKeep = collect($combinations)->pluck('key')->toArray();
    foreach ($product->skus as $sku) {
        $key = $sku->color_flavor_id . '_' . $sku->size_id . '_' . ($sku->empaque_id ?? 'null');
        if (!in_array($key, $keysToKeep)) {
            $sku->delete();
        }
    }

    // Crear los nuevos SKU
    if (!empty($toCreate)) {
        $product->skus()->createMany($toCreate);
    }
}
private function generateSkuCode(Product $product, string $colorCode, string $sizeCode, string $empaqueCode): string
{
    $brandCode = $product->brand->code ?? '';
    $productCode = $product->code ?? '';
    // Ejemplo: "EW0001" + "RD" + "B" + "XL" + "_9" = "EW0001RDBXL_9"
    return $brandCode . $productCode . $colorCode . $empaqueCode . $sizeCode;
}
private function generateSkuName(Product $product, ColorFlavor $colorFlavor, Size $size,?Empaque $empaque = null): string
{
    return $product->name . ' ' . $size->name . ' ' . $colorFlavor->name . ' en ' . $empaque->name;
}

/* Por Categoría para tienda SHOP */
public function shop(Request $request)
{
    $query = Product::query()
        ->with(['category', 'brand', 'skus'])
        ->where('state', 'public') // solo productos públicos
        ->orderBy('order')
        ->orderBy('name');

    // 🔍 Filtro por categoría (slug) - incluye subcategorías hasta nivel 2
    if ($request->has('categoria')) {
        $categorySlug = $request->input('categoria');
        $category = Category::where('slug', $categorySlug)->first();
        if ($category) {
            $categoryIds = $this->getCategoryIdsWithDescendants($category);
            $query->whereIn('category_id', $categoryIds);
        } else {
            // Si la categoría no existe, devolver vacío
            return response()->json([
                'data' => [],
                'total' => 0,
                'message' => 'Categoría no encontrada',
            ], 404);
        }
    }

    // 🔎 Búsqueda por texto (nombre del producto o marca)
    if ($request->has('search')) {
        $search = $request->input('search');
        $query->where(function ($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
              ->orWhereHas('brand', function ($q2) use ($search) {
                  $q2->where('name', 'LIKE', "%{$search}%");
              });
        });
    }

    // 📊 Ordenamiento
    $order = $request->input('order', '');
    switch ($order) {
        case 'asc':
            $query->orderBy('name', 'asc');
            break;
        case 'desc':
            $query->orderBy('name', 'desc');
            break;
        case 'price':
            $query->orderBy('sell_price', 'asc');
            break;
        default:
            // ya ordenado por 'order', 'name' al inicio
            break;
    }

    // 🚀 Devuelve TODOS los productos (sin paginación)
    $products = $query->get();
    $products->transform(fn($p) => $this->format($p));

    return response()->json([
        'data' => $products,
        'total' => $products->count(),
    ]);
}
/**
 * Obtener IDs de categoría y todos sus descendientes (niveles 1 y 2)
 */
private function getCategoryIdsWithDescendants(Category $category): array
{
    $ids = [$category->id];
    foreach ($category->children as $child) {
        $ids[] = $child->id;
        foreach ($child->children as $grandchild) {
            $ids[] = $grandchild->id;
        }
    }
    return $ids;
}
}