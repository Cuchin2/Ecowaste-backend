<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Diet;
use App\Traits\UploadsImages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DietController extends Controller
{
    use UploadsImages; // ⬅️ usamos el trait

    private function imageUrl(?string $path): ?string
    {
        return $path ? Storage::url($path) : null;
    }

    private function format(Diet $diet): Diet
    {
        $diet->image_url = $this->imageUrl($diet->image);
        return $diet;
    }

    public function index()
    {
        $diets = Diet::orderBy('order')->orderBy('name')->get();
        return response()->json($diets->map(fn($d) => $this->format($d)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'image'       => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
        ]);

        try {
            $data['order'] = Diet::max('order') + 1;
            if ($request->hasFile('image')) {
                $data['image'] = $this->uploadImage($request->file('image'), 'diets');
            }
            $diet = Diet::create($data);
            return response()->json($this->format($diet), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear: ' . $e->getMessage()], 500);
        }
    }

    public function show(Diet $diet)
    {
        return response()->json($this->format($diet));
    }

    public function update(Request $request, Diet $diet)
    {
        $data = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'description'  => 'nullable|string',
            'image'        => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
            'remove_image' => 'sometimes|boolean',
        ]);

        try {
            if ($request->boolean('remove_image') && $diet->image) {
                $this->deleteImage($diet->image);
                $diet->image = null;
                $diet->save();
            }

            if ($request->hasFile('image')) {
                if ($diet->image) $this->deleteImage($diet->image);
                $data['image'] = $this->uploadImage($request->file('image'), 'diets');
            } else {
                unset($data['image']);
            }

            $diet->update($data);
            return response()->json($this->format($diet->fresh()));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Diet $diet)
    {
        try {
            if ($diet->image) $this->deleteImage($diet->image);
            $diet->delete();
            return response()->json(['message' => 'Dieta eliminada correctamente']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar: ' . $e->getMessage()], 500);
        }
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'diets' => 'required|array',
            'diets.*.id' => 'exists:diets,id',
        ]);

        try {
            foreach ($request->diets as $i => $item) {
                Diet::where('id', $item['id'])->update(['order' => $i + 1]);
            }
            return response()->json(['message' => 'Orden actualizado']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al reordenar: ' . $e->getMessage()], 500);
        }
    }
}