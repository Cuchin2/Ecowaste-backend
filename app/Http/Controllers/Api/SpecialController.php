<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Special;
use App\Traits\UploadsImages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SpecialController extends Controller
{
    use UploadsImages; // ⬅️ usamos el trait

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
            'image'       => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
        ]);

        try {
            $data['order'] = Special::max('order') + 1;
            if ($request->hasFile('image')) {
                $data['image'] = $this->uploadImage($request->file('image'), 'specials');
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
            'name'         => 'sometimes|string|max:255',
            'description'  => 'nullable|string',
            'image'        => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
            'remove_image' => 'sometimes|boolean',
        ]);

        try {
            if ($request->boolean('remove_image') && $special->image) {
                $this->deleteImage($special->image);
                $special->image = null;
                $special->save();
            }

            if ($request->hasFile('image')) {
                if ($special->image) $this->deleteImage($special->image);
                $data['image'] = $this->uploadImage($request->file('image'), 'specials');
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
            if ($special->image) $this->deleteImage($special->image);
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