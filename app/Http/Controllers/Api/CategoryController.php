<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    public function publicIndex()
    {
        $categories = Category::roots()
            ->where('is_active', true)
            ->orderBy('order')
            ->with([
                'children' => function ($q) {
                    $q->where('is_active', true)->orderBy('order');
                },
                'children.children' => function ($q) {
                    $q->where('is_active', true)->orderBy('order');
                }
            ])->get();

        return response()->json($categories);
    }
    public function index()
    {
    $isAdmin = false;
    if (auth()->guard('sanctum')->check()) {
        $user = auth()->guard('sanctum')->user();
        $isAdmin = $user->hasRole('admin');
    }

    $query = Category::roots()->orderBy('order');

    if (!$isAdmin) {
        $query->where('is_active', true);
    }

    $categories = $query->with([
        'children' => function ($q) use ($isAdmin) {
            $q->orderBy('order');
            if (!$isAdmin) $q->where('is_active', true);
        },
        'children.children' => function ($q) use ($isAdmin) {
            $q->orderBy('order');
            if (!$isAdmin) $q->where('is_active', true);
        }
    ])->get();

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
            'order' => 'nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',  // ← agregar
            'image' => 'nullable|file|image|max:2048'
        ]);
        // Procesar imagen si se subió
        if ($request->hasFile('image')) {
            $validated['image'] = $this->uploadAndConvertToWebp($request->file('image'));
        } else {
            $validated['image'] = null; // o mantener el valor existente si quieres
        }
        /////
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
        // Convertir parent_id vacío a null antes de validar
        if ($request->has('parent_id') && $request->input('parent_id') === '') {
            $request->merge(['parent_id' => null]);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'order' => 'nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
            'image' => 'nullable|file|image|max:2048'
        ]);

        DB::beginTransaction();
        try {
            // Manejar imagen nueva
            if ($request->hasFile('image')) {
                // Eliminar imagen anterior si existe
                if ($category->image) {
                    $relativePath = str_replace('/storage/', '', $category->image);
                    $relativePath = ltrim($relativePath, '/');
                    Storage::disk('public')->delete($relativePath);
                }
                $validated['image'] = $this->uploadAndConvertToWebp($request->file('image'));
            } else {
                unset($validated['image']);
            }

            $originalParentId = $category->parent_id;

            // Generar slug si se cambia el nombre
            if (isset($validated['name'])) {
                $validated['slug'] = Str::slug($validated['name']);
            }

            // Actualizar nivel si se cambia el padre
            if (isset($validated['parent_id']) && $validated['parent_id'] != $originalParentId) {
                $parent = $validated['parent_id'] ? Category::find($validated['parent_id']) : null;
                $validated['level'] = $parent ? $parent->level + 1 : 0;
            }

            // Asignar order si cambió el padre y no se envió explícitamente
            if (!isset($validated['order'])) {
                if (isset($validated['parent_id']) && $validated['parent_id'] != $originalParentId) {
                    $newParentId = $validated['parent_id'];
                    $newLevel = $validated['level']; // ya calculado
                    $maxOrder = Category::where('parent_id', $newParentId)->where('level', $newLevel)->max('order');
                    $validated['order'] = $maxOrder ? $maxOrder + 1 : 1;
                }
            }

            $category->update($validated);

            // Reordenar grupos afectados
            $this->reorderSiblings($originalParentId);
            $this->reorderSiblings($category->parent_id);

            DB::commit();
            return response()->json($category->fresh());
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al actualizar la categoría: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Category $category)
    {
        if ($category->children()->count() > 0) {
            return response()->json(['error' => 'No se puede eliminar una categoría con subcategorías'], 422);
        }

        if ($category->image) {
            $relativePath = str_replace('/storage/', '', $category->image);
            $relativePath = ltrim($relativePath, '/');
            Storage::disk('public')->delete($relativePath);
        }

        DB::beginTransaction();

        try {
            $parentId = $category->parent_id;
            $category->delete();
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

    private function uploadAndConvertToWebp($file, $folder = 'categories')
    {
        if (!$file) return null;

        $manager = ImageManager::gd(); // o ImageManager::imagick()
        $image = $manager->read($file);

        // Generar nombre único
        $filename = Str::uuid() . '.webp';
        $path = $folder . '/' . $filename;

        // Guardar en storage/app/public/categories/xxx.webp
        $image->toWebp()->save(storage_path('app/public/' . $path));

        // Retornar la ruta accesible desde el frontend
        return Storage::url($path);
    }
}