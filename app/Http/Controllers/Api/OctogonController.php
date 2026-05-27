<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Octogon;
use App\Traits\UploadsImages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OctogonController extends Controller
{
    use UploadsImages;

    private function imageUrl(?string $path): ?string
    {
        return $path ? Storage::url($path) : null;
    }

    private function format(Octogon $octogon): Octogon
    {
        $octogon->image_url = $this->imageUrl($octogon->image);
        return $octogon;
    }

    public function index()
    {
        $octogons = Octogon::orderBy('order')->orderBy('name')->get();
        return response()->json($octogons->map(fn($o) => $this->format($o)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'image'       => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
        ]);

        try {
            $data['order'] = Octogon::max('order') + 1;
            if ($request->hasFile('image')) {
                $data['image'] = $this->uploadImage($request->file('image'), 'octogons');
            }
            $octogon = Octogon::create($data);
            return response()->json($this->format($octogon), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear: ' . $e->getMessage()], 500);
        }
    }

    public function show(Octogon $octogon)
    {
        return response()->json($this->format($octogon));
    }

    public function update(Request $request, Octogon $octogon)
    {
        $data = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'description'  => 'nullable|string',
            'image'        => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
            'remove_image' => 'sometimes|boolean',
        ]);

        try {
            if ($request->boolean('remove_image') && $octogon->image) {
                $this->deleteImage($octogon->image);
                $octogon->image = null;
                $octogon->save();
            }

            if ($request->hasFile('image')) {
                if ($octogon->image) $this->deleteImage($octogon->image);
                $data['image'] = $this->uploadImage($request->file('image'), 'octogons');
            } else {
                unset($data['image']);
            }

            $octogon->update($data);
            return response()->json($this->format($octogon->fresh()));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Octogon $octogon)
    {
        try {
            if ($octogon->image) $this->deleteImage($octogon->image);
            $octogon->delete();
            return response()->json(['message' => 'Octógono eliminado correctamente']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar: ' . $e->getMessage()], 500);
        }
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'octogons' => 'required|array',
            'octogons.*.id' => 'exists:octogons,id',
        ]);

        try {
            foreach ($request->octogons as $i => $item) {
                Octogon::where('id', $item['id'])->update(['order' => $i + 1]);
            }
            return response()->json(['message' => 'Orden actualizado']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al reordenar: ' . $e->getMessage()], 500);
        }
    }
}