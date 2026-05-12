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
     * Subir y convertir imagen a WebP.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $folder
     * @return string|null
     */
    private function uploadAndConvertToWebp($file, $folder = 'brands')
    {
        if (!$file) {
            return null;
        }

        $manager = ImageManager::gd(); // o ->imagick() si tienes instalado
        $image = $manager->read($file);

        $filename = Str::uuid() . '.webp';
        $path = $folder . '/' . $filename;

        // Guardar en storage/app/public/brands/xxx.webp
        $image->toWebp()->save(storage_path('app/public/' . $path));

        return Storage::url($path);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $brands = Brand::query()
            ->orderBy('order')
            ->orderBy('name')
            ->paginate($request->get('per_page', 15));

        return response()->json($brands);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:brands,code',
            'description' => 'nullable|string',
            'order' => 'nullable|integer|min:0',
            'image' => 'nullable|file|image|max:2048', // 2MB
        ]);

        DB::beginTransaction();
        try {
            // Subir imagen si se envió
            if ($request->hasFile('image')) {
                $validated['image'] = $this->uploadAndConvertToWebp($request->file('image'));
            }

            $brand = Brand::create($validated);
            DB::commit();

            return response()->json($brand, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al crear marca: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Brand $brand)
    {
        return response()->json($brand);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Brand $brand)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:brands,code,' . $brand->id,
            'description' => 'nullable|string',
            'order' => 'nullable|integer|min:0',
            'image' => 'nullable|file|image|max:2048',
        ]);

        DB::beginTransaction();
        try {
            // Subir nueva imagen y eliminar la anterior
            if ($request->hasFile('image')) {
                // Eliminar imagen antigua si existe
                if ($brand->image) {
                    $oldPath = str_replace('/storage/', 'public/', $brand->image);
                    Storage::delete($oldPath);
                }
                $validated['image'] = $this->uploadAndConvertToWebp($request->file('image'));
            } else {
                // No actualizar el campo image si no se envía
                unset($validated['image']);
            }

            $brand->update($validated);
            DB::commit();

            return response()->json($brand);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al actualizar marca: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Brand $brand)
    {
        // Verificar si hay productos asociados (opcional)
        if ($brand->products()->exists()) {
            return response()->json([
                'error' => 'No se puede eliminar la marca porque tiene productos asociados.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Eliminar imagen física
            if ($brand->image) {
                $oldPath = str_replace('/storage/', 'public/', $brand->image);
                Storage::delete($oldPath);
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
     * Opcional: Reordenar marcas (envías un array de ids en el orden deseado).
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