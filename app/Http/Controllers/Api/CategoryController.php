<?php

// app/Http/Controllers/Api/CategoryController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    // Obtener todas las categorías anidadas (para frontend)
    public function index()
    {
        $categories = Category::roots()->with('children.children')->get();
        return response()->json($categories);
    }

    // Obtener categorías planas (para selects, etc.)
    public function flat()
    {
        $categories = Category::orderBy('level')->orderBy('order')->get();
        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'order' => 'nullable|integer'
        ]);

        $parent = $validated['parent_id'] ? Category::find($validated['parent_id']) : null;
        $level = $parent ? $parent->level + 1 : 0;

        $validated['slug'] = Str::slug($validated['name']);
        $validated['level'] = $level;

        $category = Category::create($validated);
        return response()->json($category, 201);
    }

    public function show(Category $category)
    {
        return response()->json($category->load('parent', 'children'));
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'order' => 'nullable|integer'
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        if (isset($validated['parent_id'])) {
            $parent = $validated['parent_id'] ? Category::find($validated['parent_id']) : null;
            $validated['level'] = $parent ? $parent->level + 1 : 0;
        }

        $category->update($validated);
        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        // Opcional: verificar si tiene hijos para no eliminarlo (o usar cascade)
        if ($category->children()->count() > 0) {
            return response()->json(['error' => 'Cannot delete category with children'], 422);
        }
        $category->delete();
        return response()->json(['message' => 'Deleted']);
    }
}