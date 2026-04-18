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
        $categories = Category::roots()
            ->orderBy('order') // o ->orderByRaw('`order` ASC')
            ->with([
                'children' => function ($query) {
                    $query->orderBy('order');
                },
                'children.children' => function ($query) {
                    $query->orderBy('order');
                }
            ])
            ->get();

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

    // Si no se proporciona 'order' en la petición, lo calculamos
    if (!isset($validated['order'])) {
        $maxOrder = Category::where('parent_id', $validated['parent_id'])
            ->where('level', $level)
            ->max('order');
        $validated['order'] = $maxOrder ? $maxOrder + 1 : 1;
    }

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

    // Guardar estado original antes de modificar
    $originalParentId = $category->parent_id;
    $originalOrder = $category->order;

    // Generar slug si se cambia el nombre
    if (isset($validated['name'])) {
        $validated['slug'] = Str::slug($validated['name']);
    }

    // Actualizar nivel si se cambia el padre
    if (isset($validated['parent_id'])) {
        $parent = $validated['parent_id'] ? Category::find($validated['parent_id']) : null;
        $validated['level'] = $parent ? $parent->level + 1 : 0;
    }

    // Realizar la actualización
    $category->update($validated);

    // =====================================================
    // Reordenación de grupos después del update
    // =====================================================

    // Función auxiliar para reordenar hermanos de un parent_id dado
    $reorderSiblings = function ($parentId) {
        $siblings = Category::where('parent_id', $parentId)
            ->orderBy('order')
            ->get();
        $newOrder = 1;
        foreach ($siblings as $sibling) {
            if ($sibling->order != $newOrder) {
                $sibling->order = $newOrder;
                $sibling->saveQuietly(); // evitar recursión de eventos
            }
            $newOrder++;
        }
    };

    // Caso 1: Cambió el parent_id
    if (isset($validated['parent_id']) && $validated['parent_id'] != $originalParentId) {
        // Reordenar el grupo original (del que se fue)
        if ($originalParentId !== null) {
            $reorderSiblings($originalParentId);
        }
        // Reordenar el grupo nuevo (al que llegó)
        $reorderSiblings($validated['parent_id']);
    }
    // Caso 2: No cambió parent_id pero pudo cambiar el order o el orden se desincronizó
    else {
        // Reordenar el grupo actual para asegurar secuencia 1..N
        $reorderSiblings($category->parent_id);
    }

    return response()->json($category);
}

    public function destroy(Category $category)
    {
        // No permitir eliminar si tiene hijos (opcional, pero lo dejo)
        if ($category->children()->count() > 0) {
            return response()->json(['error' => 'Cannot delete category with children'], 422);
        }

        $parentId = $category->parent_id;
        
        // Eliminar la categoría
        $category->delete();

        // Reordenar las categorías hermanas restantes (mismo parent_id)
        $siblings = Category::where('parent_id', $parentId)
                            ->orderBy('order')
                            ->get();

        $newOrder = 1;
        foreach ($siblings as $sibling) {
            $sibling->order = $newOrder;
            $sibling->save();
            $newOrder++;
        }

        return response()->json(['message' => 'Deleted and reordered']);
    }
}