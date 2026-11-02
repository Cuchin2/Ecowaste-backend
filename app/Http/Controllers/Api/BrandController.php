<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Traits\UploadsImages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class BrandController extends Controller
{
    use UploadsImages; // Usamos el trait para subir y eliminar imágenes

    private function imageUrl(?string $path): ?string
    {
        return $path ? Storage::url($path) : null;
    }

    private function format(Brand $brand): Brand
    {
        $brand->image_url = $this->imageUrl($brand->image);
        return $brand;
    }

    public function index()
    {
        // 👈 1. 'select': Solo trae las columnas necesarias (ahorra memoria y transferencia de red).
        // 👈 2. 'where': Si es una API pública, solo trae las activas (ahorra procesamiento).
        // 👈 3. Si son muchas, considera cambiar ->get() por ->paginate(20).
        
        $brands = Brand::select('id', 'name', 'slug', 'code', 'image', 'is_active', 'order')
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        return response()->json($brands); // El accessor 'image_url' se agrega automáticamente
    }
    public function publicActive()
    {
        $brands = Brand::active() // 👈 Usa el scope: ->where('is_active', true)
            ->select('id', 'name', 'slug', 'image') // 👈 Solo lo estrictamente necesario para la UI pública
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        // El accessor 'image_url' se agregará automáticamente al convertir a JSON
        return response()->json($brands);
    }
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'size:2',
                'regex:/^[A-Z0-9]{2}$/',
                'unique:brands,code' // creación
            ],
            'description' => 'nullable|string',
            'image' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
        ]);

        DB::beginTransaction();
        try {
            $data['order'] = Brand::max('order') + 1;
            if ($request->hasFile('image')) {
                $data['image'] = $this->uploadImage($request->file('image'), 'brands');
            }
            $brand = Brand::create($data);
            DB::commit();
            return response()->json($this->format($brand), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al crear: ' . $e->getMessage()], 500);
        }
    }

    public function show(Brand $brand)
    {
        return response()->json($this->format($brand));
    }

    public function update(Request $request, Brand $brand)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => [
                'required',
                'string',
                'size:2',
                'regex:/^[A-Z0-9]{2}$/',
                'unique:brands,code,' . $brand->id
            ],
            'description' => 'nullable|string',
            'image' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
            'remove_image' => 'sometimes|boolean',
        ]);

        DB::beginTransaction();
        try {
            if ($request->boolean('remove_image') && $brand->image) {
                $this->deleteImage($brand->image);
                $brand->image = null;
                $brand->save();
            }

            if ($request->hasFile('image')) {
                if ($brand->image) $this->deleteImage($brand->image);
                $data['image'] = $this->uploadImage($request->file('image'), 'brands');
            } else {
                unset($data['image']);
            }

            $brand->update($data);
            DB::commit();
            return response()->json($this->format($brand->fresh()));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Brand $brand)
    {
        DB::beginTransaction();
        try {
            if ($brand->image) $this->deleteImage($brand->image);
            $brand->delete();
            DB::commit();
            return response()->json(['message' => 'Marca eliminada']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al eliminar: ' . $e->getMessage()], 500);
        }
    }

    public function reorder(Request $request)
    {
        $request->validate(['brands' => 'required|array', 'brands.*.id' => 'exists:brands,id']);
        DB::transaction(function () use ($request) {
            foreach ($request->brands as $i => $item) {
                Brand::where('id', $item['id'])->update(['order' => $i + 1]);
            }
        });
        return response()->json(['message' => 'Orden actualizado']);
    }
    /**
 * Actualiza solo el estado activo/inactivo de una marca
 * Optimizado para checkboxes en tablas (respuesta rápida y ligera)
 */
    public function updateActive(Request $request, Brand $brand)
    {
        $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $brand->update([
            'is_active' => $request->boolean('is_active')
        ]);

        return response()->json([
            'message' => 'Estado actualizado',
            'is_active' => $brand->is_active
        ]);
    }
}