<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class BrandController extends Controller
{
    /**
     * Subir y convertir imagen a WebP (ruta relativa).
     */
    private function uploadAndConvertToWebp($file, $folder = 'brands')
    {
        if (!$file) {
            return null;
        }

        $manager = ImageManager::gd();
        $image = $manager->read($file);

        $filename = Str::uuid() . '.webp';
        $relativePath = $folder . '/' . $filename;
        $fullPath = storage_path('app/public/' . $relativePath);

        // Crear directorio si no existe
        $directory = dirname($fullPath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $image->toWebp()->save($fullPath);

        return $relativePath; // Ej: 'brands/uuid.webp'
    }

    /**
     * Convierte una ruta relativa a URL absoluta.
     */
    private function getImageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        return Storage::url($path);
    }

    /**
     * Agrega el campo image_url a un objeto Brand.
     */
    private function formatBrand(Brand $brand): Brand
    {
        $brand->image_url = $this->getImageUrl($brand->image);
        return $brand;
    }

    /**
     * Transforma una colección de marcas añadiendo image_url.
     */
    private function transformBrands($brands)
    {
        return $brands->map(function ($brand) {
            return $this->formatBrand($brand);
        });
    }

    /**
     * Listar todas las marcas (ordenadas por order y nombre).
     */
    public function index()
    {
        $brands = Brand::orderBy('order')->orderBy('name')->get();
        return response()->json($this->transformBrands($brands));
    }

    /**
     * Crear una nueva marca.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:brands,code',
            'description' => 'nullable|string',
            'image' => 'nullable|file|image|max:2048',
        ]);

        DB::beginTransaction();
        try {
            // Asignar order automático (último + 1)
            $maxOrder = Brand::max('order');
            $validated['order'] = $maxOrder ? $maxOrder + 1 : 1;

            if ($request->hasFile('image')) {
                $validated['image'] = $this->uploadAndConvertToWebp($request->file('image'));
            }

            $brand = Brand::create($validated);
            DB::commit();

            return response()->json($this->formatBrand($brand), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al crear marca: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mostrar una marca específica.
     */
    public function show(Brand $brand)
    {
        return response()->json($this->formatBrand($brand));
    }

    /**
     * Actualizar una marca.
     */
    public function update(Request $request, Brand $brand)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:brands,code,' . $brand->id,
            'description' => 'nullable|string',
            'image' => 'nullable|file|image|max:2048',
            'remove_image' => 'sometimes|boolean',
        ]);

        DB::beginTransaction();
        try {
            // 1. Eliminar imagen si se envía remove_image = true
            if ($request->boolean('remove_image') && $brand->image) {
                Storage::disk('public')->delete($brand->image);
                $brand->image = null;
                $brand->save(); // guardar cambio inmediato
            }

            // 2. Subir nueva imagen (reemplaza la actual)
            if ($request->hasFile('image')) {
                // Eliminar la anterior si existe (evita duplicados)
                if ($brand->image) {
                    Storage::disk('public')->delete($brand->image);
                }
                $validated['image'] = $this->uploadAndConvertToWebp($request->file('image'));
            } else {
                unset($validated['image']);
            }

            $brand->update($validated);
            DB::commit();

            return response()->json($this->formatBrand($brand));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al actualizar marca: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar una marca.
     */
    public function destroy(Brand $brand)
    {
        // Verificar si tiene productos asociados (opcional)
        if ($brand->products()->exists()) {
            return response()->json([
                'error' => 'No se puede eliminar la marca porque tiene productos asociados.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Eliminar la imagen física si existe
            if ($brand->image) {
                Storage::disk('public')->delete($brand->image);
            }

            $brand->delete();
            DB::commit();

            return response()->json(['message' => 'Marca eliminada correctamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al eliminar marca: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reordenar marcas (drag & drop).
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'brands' => 'required|array',
            'brands.*.id' => 'exists:brands,id',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->brands as $index => $item) {
                Brand::where('id', $item['id'])->update(['order' => $index + 1]);
            }
        });

        return response()->json(['message' => 'Orden actualizado correctamente']);
    }
}