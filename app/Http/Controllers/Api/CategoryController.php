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
    /**
     * Categorías públicas (para visitantes, solo activas)
     */
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

        return response()->json($this->transformImageUrls($categories));
    }

    /**
     * Categorías para administración (con permisos)
     */
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

        return response()->json($this->transformImageUrls($categories));
    }

    /**
     * Categorías planas (para selects)
     */
    public function flat()
    {
        $categories = Category::orderBy('level')->orderBy('order')->get();
        return response()->json($this->transformImageUrls($categories));
    }

    /**
     * Crear nueva categoría
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'order' => 'nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
            'image' => 'nullable|file|image|max:2048'
        ]);

        // Procesar imagen si se subió
        if ($request->hasFile('image')) {
            $validated['image'] = $this->uploadAndConvertToWebp($request->file('image'));
        } else {
            $validated['image'] = null;
        }

        $parent = $validated['parent_id'] ? Category::find($validated['parent_id']) : null;
        $level = $parent ? $parent->level + 1 : 0;

        $validated['slug'] = Str::slug($validated['name']);
        $validated['level'] = $level;

        if (!isset($validated['order'])) {
            $maxOrder = Category::where('parent_id', $validated['parent_id'])
                ->where('level', $level)
                ->max('order');
            $validated['order'] = is_null($maxOrder) ? 1 : $maxOrder + 1;
        }

        $category = Category::create($validated);

        return response()->json($this->formatCategory($category), 201);
    }

    /**
     * Mostrar una categoría específica
     */
    public function show(Category $category)
    {
        $category->load('parent', 'children');
        return response()->json($this->formatCategory($category));
    }

    /**
     * Actualizar categoría
     */
    public function update(Request $request, Category $category)
    {
        // Convertir parent_id vacío a null
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
                // Eliminar imagen anterior si existe (ruta relativa)
                if ($category->image) {
                    Storage::disk('public')->delete($category->image);
                }
                $validated['image'] = $this->uploadAndConvertToWebp($request->file('image'));
            } else {
                unset($validated['image']);
            }

            $originalParentId = $category->parent_id;

            if (isset($validated['name'])) {
                $validated['slug'] = Str::slug($validated['name']);
            }

            if (isset($validated['parent_id']) && $validated['parent_id'] != $originalParentId) {
                $parent = $validated['parent_id'] ? Category::find($validated['parent_id']) : null;
                $validated['level'] = $parent ? $parent->level + 1 : 0;
            }

            if (!isset($validated['order'])) {
                if (isset($validated['parent_id']) && $validated['parent_id'] != $originalParentId) {
                    $newParentId = $validated['parent_id'];
                    $newLevel = $validated['level'];
                    $maxOrder = Category::where('parent_id', $newParentId)
                        ->where('level', $newLevel)
                        ->max('order');
                    $validated['order'] = $maxOrder ? $maxOrder + 1 : 1;
                }
            }

            $category->update($validated);

            $this->reorderSiblings($originalParentId);
            $this->reorderSiblings($category->parent_id);

            DB::commit();

            return response()->json($this->formatCategory($category->fresh()));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al actualizar la categoría: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar categoría
     */
    public function destroy(Category $category)
    {
        if ($category->children()->count() > 0) {
            return response()->json(['error' => 'No se puede eliminar una categoría con subcategorías'], 422);
        }

        if ($category->image) {
            Storage::disk('public')->delete($category->image);
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
     * Reordenar hermanas de un mismo padre
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

    /**
     * Reordenar categorías (drag & drop)
     */
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

    /**
     * Subir y convertir imagen a WebP
     */
    private function uploadAndConvertToWebp($file, $folder = 'categories')
    {
        if (!$file) return null;

        $manager = ImageManager::gd();
        $image = $manager->read($file);

        $filename = Str::uuid() . '.webp';
        $path = $folder . '/' . $filename;

        $image->toWebp()->save(storage_path('app/public/' . $path));

        // Retornar la ruta relativa (ej. categories/uuid.webp)
        return $path;
    }

    /**
     * Convierte una ruta de imagen a URL absoluta usando Storage::url()
     */
private function getImageUrl($path)
{
    if (!$path) return null;
    // Forzar URL absoluta usando la configuración de APP_URL
    $baseUrl = rtrim(config('app.url'), '/');
    return $baseUrl . '/storage/' . ltrim($path, '/');
}

    /**
     * Formatea una categoría individual añadiendo image_url
     */
    private function formatCategory(Category $category)
    {
        $category->image_url = $this->getImageUrl($category->image);
        return $category;
    }

    /**
     * Transforma recursivamente una colección de categorías añadiendo image_url
     */
    private function transformImageUrls($categories)
    {
        return $categories->map(function ($category) {
            $category->image_url = $this->getImageUrl($category->image);
            if ($category->children) {
                $category->children = $this->transformImageUrls($category->children);
            }
            return $category;
        });
    }
}