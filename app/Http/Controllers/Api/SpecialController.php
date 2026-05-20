<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Special;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class SpecialController extends Controller
{
    private function uploadWebp($file, $folder = 'specials')
    {
        if (!$file) return null;

        $img = ImageManager::gd()->read($file);
        $relative = $folder . '/' . Str::uuid() . '.webp';
        $fullPath = storage_path('app/public/' . $relative);
        $dir = dirname($fullPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $img->toWebp()->save($fullPath);
        return $relative;
    }

    private function imageUrl(?string $path): ?string
    {
        return $path ? Storage::url($path) : null;
    }

    private function format(Special $special): Special
    {
        $special->image_url = $this->imageUrl($special->image);
        return $special;
    }

    public function index()
    {
        $specials = Special::orderBy('order')->orderBy('name')->get();
        return response()->json($specials->map(fn($s) => $this->format($s)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'image'       => 'nullable|file|image|max:2048',
        ]);

        try {
            $data['order'] = Special::max('order') + 1;
            if ($request->hasFile('image')) {
                $data['image'] = $this->uploadWebp($request->file('image'));
            }
            $special = Special::create($data);
            return response()->json($this->format($special), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear: ' . $e->getMessage()], 500);
        }
    }

    public function show(Special $special)
    {
        return response()->json($this->format($special));
    }

    public function update(Request $request, Special $special)
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image'       => 'nullable|file|image|max:2048',
            'remove_image' => 'sometimes|boolean',
        ]);

        try {
            if ($request->boolean('remove_image') && $special->image) {
                Storage::disk('public')->delete($special->image);
                $special->image = null;
                $special->save();
            }

            if ($request->hasFile('image')) {
                if ($special->image) Storage::disk('public')->delete($special->image);
                $data['image'] = $this->uploadWebp($request->file('image'));
            } else {
                unset($data['image']);
            }

            $special->update($data);
            return response()->json($this->format($special->fresh()));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Special $special)
    {
        try {
            if ($special->image) Storage::disk('public')->delete($special->image);
            $special->delete();
            return response()->json(['message' => 'Elemento especial eliminado']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar: ' . $e->getMessage()], 500);
        }
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'specials' => 'required|array',
            'specials.*.id' => 'exists:specials,id',
        ]);

        try {
            foreach ($request->specials as $i => $item) {
                Special::where('id', $item['id'])->update(['order' => $i + 1]);
            }
            return response()->json(['message' => 'Orden actualizado']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al reordenar: ' . $e->getMessage()], 500);
        }
    }
}