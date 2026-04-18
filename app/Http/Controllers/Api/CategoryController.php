<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    // Obtener todas las categorías anidadas (para frontend)
    public function index()
    {
        $categories = Category::roots()
            ->orderBy('order')
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
            'order' => 'nullable|integer|min:1'
        ]);

        $parent = $validated['parent_id'] ? Category::find($validated['parent_id']) : null;
        $level = $parent ? $parent->level + 1 : 0;

        $validated['slug'] = Str::slug($validated['name']);
        $validated['level'] = $level;

        // Si no se proporciona 'order', calcular el siguiente disponible
        if (!isset($validated['order'])) {
            $maxOrder = Category::where('parent_id', $validated['parent_id'])
                ->where('level', $level)
                ->max('order');
            $validated['order'] = is_null($maxOrder) ? 1 : $maxOrder + 1;
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
            'order' => 'nullable|integer|min:1'
        ]);

        DB::beginTransaction();

        try {
            $originalParentId = $category->parent_id;
            $originalOrder = $category->order; // se usará más adelante si es necesario

            // Generar slug si se cambia el nombre
            if (isset($validated['name'])) {
                $validated['slug'] = Str::slug($validated['name']);
            }

            // Actualizar nivel si se cambia el padre
            if (isset($validated['parent_id']) && $validated['parent_id'] != $originalParentId) {
                $parent = $validated['parent_id'] ? Category::find($validated['parent_id']) : null;
                $validated['level'] = $parent ? $parent->level + 1 : 0;
            }

            // Si se ha enviado un 'order' específico, lo respetamos.
            // Si no se envía, mantenemos el actual (no recalcular automáticamente a menos que cambie el padre)
            if (!isset($validated['order'])) {
                // Si cambió el padre, la categoría se moverá al final del nuevo grupo
                if (isset($validated['parent_id']) && $validated['parent_id'] != $originalParentId) {
                    $newParentId = $validated['parent_id'];
                    $newLevel = $validated['level'];
                    $maxOrder = Category::where('parent_id', $newParentId)
                        ->where('level', $newLevel)
                        ->max('order');
                    $validated['order'] = is_null($maxOrder) ? 1 : $maxOrder + 1;
                }
                // Si no cambió padre y no se envió order, mantenemos el mismo order
            }

            // Realizar la actualización
            $category->update($validated);

            // Reordenar los grupos afectados
            $this->reorderSiblings($originalParentId); // grupo antiguo (si aplica)

            $newParentId = $category->parent_id; // después del update
            $this->reorderSiblings($newParentId); // grupo nuevo

            DB::commit();

            return response()->json($category->fresh());
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al actualizar la categoría: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Category $category)
    {
        // No permitir eliminar si tiene hijos
        if ($category->children()->count() > 0) {
            return response()->json(['error' => 'No se puede eliminar una categoría con subcategorías'], 422);
        }

        DB::beginTransaction();

        try {
            $parentId = $category->parent_id;
            $category->delete();

            // Reordenar las hermanas restantes
            $this->reorderSiblings($parentId);

            DB::commit();

            return response()->json(['message' => 'Categoría eliminada y reordenada correctamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al eliminar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reordena secuencialmente (1,2,3...) todas las categorías con el mismo parent_id.
     *
     * @param int|null $parentId
     * @return void
     */
    private function reorderSiblings($parentId)
    {
        $siblings = Category::where('parent_id', $parentId)
            ->orderBy('order')
            ->get();

        $newOrder = 1;
        foreach ($siblings as $sibling) {
            if ($sibling->order != $newOrder) {
                $sibling->order = $newOrder;
                $sibling->save();
            }
            $newOrder++;
        }
    }
    public function reorder(Request $request)
        {
            $request->validate([
                'categories' => 'required|array',
                'categories.*.id' => 'required|exists:categories,id',
                'categories.*.order' => 'required|integer'
            ]);

            DB::transaction(function () use ($request) {
                foreach ($request->categories as $categoryData) {
                    Category::where('id', $categoryData['id'])
                        ->update(['order' => $categoryData['order']]);
                }
            });

            return response()->json(['message' => 'Orden actualizado correctamente']);
        }
}