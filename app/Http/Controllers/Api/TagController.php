<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index()
    {
        $tags = Tag::orderBy('name')->get();
        return response()->json($tags);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:tags,name',
            'description' => 'nullable|string',
            // slug no se valida porque se genera automáticamente, pero si se envía, debe ser único
            'slug'        => 'nullable|string|max:255|unique:tags,slug',
        ]);

        try {
            $tag = Tag::create($validated);
            return response()->json($tag, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear: ' . $e->getMessage()], 500);
        }
    }

    public function show(Tag $tag)
    {
        return response()->json($tag);
    }

    public function update(Request $request, Tag $tag)
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255|unique:tags,name,' . $tag->id,
            'description' => 'nullable|string',
            'slug'        => 'nullable|string|max:255|unique:tags,slug,' . $tag->id,
        ]);

        try {
            $tag->update($validated);
            return response()->json($tag->fresh());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Tag $tag)
    {
        try {
            // Opcional: si usas relaciones polimórficas, verificar si está en uso antes de eliminar
            // if ($tag->taggables()->exists()) { ... }
            $tag->delete();
            return response()->json(['message' => 'Etiqueta eliminada correctamente']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar: ' . $e->getMessage()], 500);
        }
    }
}