<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductSku;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Traits\UploadsImages;

class ProductSkuImageController extends Controller
{
    use UploadsImages;

    /**
     * Listar imágenes de un SKU
     */
    public function index(ProductSku $sku)
    {
        return response()->json($sku->images()->get());
    }

    /**
     * Subir una o múltiples imágenes para un SKU
     */
    public function store(Request $request, ProductSku $sku)
    {
        $request->validate([
            'images' => 'required|array',
            'images.*' => 'image|max:2048',
            'alts' => 'nullable|array',
            'alts.*' => 'string|nullable',
        ]);

        $uploaded = [];
        $currentOrder = $sku->images()->max('pivot_order') ?? 0;

        foreach ($request->file('images') as $index => $file) {
            // Usar el trait para subir la imagen
            $path = $this->uploadImage($file, 'skus/' . $sku->id);

            $image = Image::create([
                'path' => $path,
                'alt' => $request->alts[$index] ?? null,
            ]);

            $sku->images()->attach($image, [
                'order' => $currentOrder + $index + 1,
            ]);

            $uploaded[] = $image;
        }

        return response()->json([
            'message' => 'Imágenes subidas correctamente',
            'images' => $uploaded,
        ], 201);
    }

    /**
     * Reordenar imágenes de un SKU
     */
    public function updateOrder(Request $request, ProductSku $sku)
    {
        $request->validate([
            'order' => 'required|array',
            'order.*' => 'exists:images,id',
        ]);

        foreach ($request->order as $index => $imageId) {
            $sku->images()->updateExistingPivot($imageId, ['order' => $index]);
        }

        return response()->json(['message' => 'Orden actualizado correctamente']);
    }

    /**
     * Eliminar una imagen específica de un SKU
     */
    public function destroy(ProductSku $sku, Image $image)
    {
        // Verificar que la imagen pertenece al SKU
        if (!$sku->images()->where('image_id', $image->id)->exists()) {
            return response()->json(['error' => 'La imagen no pertenece a este SKU'], 404);
        }

        // Eliminar archivo físico usando el trait
        $this->deleteImage($image->path);

        // Eliminar relación pivote
        $sku->images()->detach($image->id);

        // Si la imagen no está asociada a ningún otro SKU, eliminarla de la tabla images
        if ($image->productSkus()->count() === 0) {
            $image->delete();
        }

        return response()->json(['message' => 'Imagen eliminada correctamente']);
    }
}