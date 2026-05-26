<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Aptitude;
use App\Traits\UploadsImages; // si tienes el trait
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager; // si usas conversion

class AptitudeController extends Controller
{
    use UploadsImages; // opcional, podemos usar funciones propias

    private function deleteImage($path)
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function imageUrl(?string $path): ?string
    {
        return $path ? Storage::url($path) : null;
    }

    private function format(Aptitude $aptitude): Aptitude
    {
        $aptitude->image_banner_url = $this->imageUrl($aptitude->image_banner);
        $aptitude->image_tag_url = $this->imageUrl($aptitude->image_tag);
        return $aptitude;
    }

    public function index()
    {
        $aptitudes = Aptitude::orderBy('order')->orderBy('name')->get();
        return response()->json($aptitudes->map(fn($a) => $this->format($a)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'image_banner'   => 'nullable|file|image|max:2048',
            'image_tag'      => 'nullable|file|image|max:2048',
            'type'           => 'required|string|max:100',
        ]);

        try {
            $data['order'] = Aptitude::max('order') + 1;
            if ($request->hasFile('image_banner')) {
                $data['image_banner'] = $this->uploadImage($request->file('image_banner'), 'aptitudes');
            }
            if ($request->hasFile('image_tag')) {
                $data['image_tag'] = $this->uploadImage($request->file('image_tag'), 'aptitudes');
            }
            $aptitude = Aptitude::create($data);
            return response()->json($this->format($aptitude), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear: ' . $e->getMessage()], 500);
        }
    }

    public function show(Aptitude $aptitude)
    {
        return response()->json($this->format($aptitude));
    }

    public function update(Request $request, Aptitude $aptitude)
    {
        $data = $request->validate([
            'name'                => 'sometimes|string|max:255',
            'description'         => 'nullable|string',
            'image_banner'        => 'nullable|file|image|max:2048',
            'image_tag'           => 'nullable|file|image|max:2048',
            'remove_image_banner' => 'sometimes|boolean',
            'remove_image_tag'    => 'sometimes|boolean',
            'type'                => 'sometimes|string|max:100',
        ]);

        try {
            if ($request->boolean('remove_image_banner') && $aptitude->image_banner) {
                $this->deleteImage($aptitude->image_banner);
                $aptitude->image_banner = null;
                $aptitude->save();
            }
            if ($request->boolean('remove_image_tag') && $aptitude->image_tag) {
                $this->deleteImage($aptitude->image_tag);
                $aptitude->image_tag = null;
                $aptitude->save();
            }

            if ($request->hasFile('image_banner')) {
                if ($aptitude->image_banner) $this->deleteImage($aptitude->image_banner);
                $data['image_banner'] = $this->uploadImage($request->file('image_banner'), 'aptitudes');
            } else {
                unset($data['image_banner']);
            }

            if ($request->hasFile('image_tag')) {
                if ($aptitude->image_tag) $this->deleteImage($aptitude->image_tag);
                $data['image_tag'] = $this->uploadImage($request->file('image_tag'), 'aptitudes');
            } else {
                unset($data['image_tag']);
            }

            $aptitude->update($data);
            return response()->json($this->format($aptitude->fresh()));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Aptitude $aptitude)
    {
        try {
            if ($aptitude->image_banner) $this->deleteImage($aptitude->image_banner);
            if ($aptitude->image_tag) $this->deleteImage($aptitude->image_tag);
            $aptitude->delete();
            return response()->json(['message' => 'Aptitud eliminada correctamente']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar: ' . $e->getMessage()], 500);
        }
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'aptitudes' => 'required|array',
            'aptitudes.*.id' => 'exists:aptitudes,id',
        ]);

        try {
            foreach ($request->aptitudes as $i => $item) {
                Aptitude::where('id', $item['id'])->update(['order' => $i + 1]);
            }
            return response()->json(['message' => 'Orden actualizado']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al reordenar: ' . $e->getMessage()], 500);
        }
    }
}