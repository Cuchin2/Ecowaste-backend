<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Traits\UploadsImages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        $product->load(['category', 'brand']);
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
            return response()->json($this->format($product->fresh()));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Product $product)
    {
        try {
            if ($product->image) $this->deleteImage($product->image);
            if ($product->img_nutrition) $this->deleteImage($product->img_nutrition);
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
}