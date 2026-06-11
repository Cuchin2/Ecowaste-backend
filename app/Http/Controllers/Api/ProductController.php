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
        // Cargar relaciones directas del producto
        $product->load([
            'category',
            'brand',
            'tags',
            'empaques',
            'octogons',
            'colorFlavors',
            'sizes',
        ]);

        // Cargar las relaciones del pivote para cada color/sabor
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
            'tag_ids'             => 'sometimes|array',
            'tag_ids.*'           => 'exists:tags,id',
            'empaque_ids'         => 'sometimes|array',          // 👈 nuevo
            'empaque_ids.*'       => 'exists:empaques,id',      // 👈 nuevo
            'octogon_ids'        => 'sometimes|array',
            'octogon_ids.*'      => 'exists:octogons,id',
            'size_ids' => 'sometimes|array',
            'size_ids.*' => 'exists:sizes,id',
            // 👇 Validación para color_flavor_ids
            'color_flavor_ids'    => 'sometimes|array',
            'color_flavor_ids.*'  => 'exists:color_flavor,id',
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
            if ($request->has('tag_ids')) {
                $product->tags()->sync($request->input('tag_ids'));
            }

            // Sincronizar empaques (muchos a muchos)
            if ($request->has('empaque_ids')) {
                $product->empaques()->sync($request->input('empaque_ids'));
            }
            // Sincronizar sellos (muchos a muchos)
            if ($request->has('octogon_ids')) {
                $product->octogons()->sync($request->input('octogon_ids'));
            }
            // Sincronizar Tamaños 
            if ($request->has('size_ids')) {
                $product->sizes()->sync($request->input('size_ids'));
            }
            // 👇 Sincronizar color-flavors CON ORDEN
            if ($request->has('color_flavor_ids')) {
                $orderedIds = [];
                foreach ($request->input('color_flavor_ids') as $index => $colorFlavorId) {
                    $orderedIds[$colorFlavorId] = ['order' => $index];
                }
                $product->colorFlavors()->sync($orderedIds);
            }
        //
        // 🔥 GENERAR SKU automáticamente después de sincronizar colores y tamaños
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
    // Obtener todos los IDs de color_flavor y size asociados al producto
    $colorFlavorIds = $product->colorFlavors->pluck('id')->toArray();
    $sizeIds = $product->sizes->pluck('id')->toArray();

    // Si no hay colores ni tamaños, eliminar todos los SKU y salir
    if (empty($colorFlavorIds) || empty($sizeIds)) {
        $product->skus()->delete();
        return;
    }

    // Construir el código base: brand_code + product_code
    $brandCode = $product->brand->code ?? '';
    $productCode = $product->code ?? '';

    // Obtener todas las combinaciones posibles (producto cartesiano)
    $combinations = [];
    foreach ($colorFlavorIds as $cfId) {
        foreach ($sizeIds as $sId) {
            $combinations[] = [
                'color_flavor_id' => $cfId,
                'size_id' => $sId,
            ];
        }
    }

    // Obtener los SKU existentes (para evitar eliminarlos si ya existen)
    $existingSkus = $product->skus()->get()->keyBy(function ($sku) {
        return $sku->color_flavor_id . '_' . $sku->size_id;
    });

    $newCombinations = [];
    foreach ($combinations as $combo) {
        $key = $combo['color_flavor_id'] . '_' . $combo['size_id'];
        if (!isset($existingSkus[$key])) {
            // Necesitamos obtener los códigos individuales
            $colorFlavor = ColorFlavor::find($combo['color_flavor_id']);
            $size = Size::find($combo['size_id']);
            $code = $this->generateSkuCode($brandCode, $productCode, $colorFlavor->code, $size->code);
            $newCombinations[] = [
                'product_id' => $product->id,
                'color_flavor_id' => $combo['color_flavor_id'],
                'size_id' => $combo['size_id'],
                'code' => $code,
                'sell_price' => 0,          // valores por defecto, luego se editan
                'stock' => 0,
                'offer' => false,
            ];
        }
    }

    // Eliminar SKU que ya no corresponden a ninguna combinación
    $keysToKeep = array_keys($combinations);
    $product->skus()->whereNotIn(DB::raw('CONCAT(color_flavor_id, "_", size_id)'), $keysToKeep)->delete();

    // Crear los nuevos SKU
    if (!empty($newCombinations)) {
        $product->skus()->createMany($newCombinations);
    }
}
private function generateSkuCode(string $brandCode, string $productCode, string $colorCode, string $sizeCode): string
{
    return $brandCode . $productCode . $colorCode . $sizeCode;
}
}