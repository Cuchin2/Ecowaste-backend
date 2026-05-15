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
    private function uploadWebp($file, $folder = 'brands')
    {
        if (!$file) return null;

        $img = ImageManager::gd()->read($file);
        $relative = $folder . '/' . Str::uuid() . '.webp';
        $fullPath = storage_path('app/public/' . $relative);
        $dir = dirname($fullPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        // Sin escalado, sin calidad forzada (usa la predeterminada ~90)
        $img->toWebp()->save($fullPath);
        return $relative;
    }

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
        $brands = Brand::orderBy('order')->orderBy('name')->get();
        return response()->json($brands->map(fn($b) => $this->format($b)));
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
                    'unique:brands,code,' . ($brand->id ?? '')
                ],
            'description' => 'nullable|string',
            'image' => 'nullable|file|image|max:2048',
        ]);

        DB::beginTransaction();
        try {
            $data['order'] = Brand::max('order') + 1;
            if ($request->hasFile('image')) {
                $data['image'] = $this->uploadWebp($request->file('image'));
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
                    'unique:brands,code,' . ($brand->id ?? '')
                ],
            'description' => 'nullable|string',
            'image' => 'nullable|file|image|max:2048',
            'remove_image' => 'sometimes|boolean',
        ]);

        DB::beginTransaction();
        try {
            if ($request->boolean('remove_image') && $brand->image) {
                Storage::disk('public')->delete($brand->image);
                $brand->image = null;
                $brand->save();
            }

            if ($request->hasFile('image')) {
                if ($brand->image) Storage::disk('public')->delete($brand->image);
                $data['image'] = $this->uploadWebp($request->file('image'));
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
/*         if ($brand->products()->exists()) {
            return response()->json(['error' => 'Marca con productos asociados'], 422);
        } */

        DB::beginTransaction();
        try {
            if ($brand->image) Storage::disk('public')->delete($brand->image);
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
}