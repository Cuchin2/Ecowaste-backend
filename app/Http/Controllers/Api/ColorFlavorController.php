<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ColorFlavorController extends Controller
{
      /**
     * Listar todos los colores/sabores.
     */
    public function index()
    {
        $items = ColorFlavor::orderBy('type')->orderBy('name')->get();
        return response()->json($items);
    }

    /**
     * Crear un nuevo color/sabor.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'hex'  => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'code' => [
                'required',
                'string',
                'size:2',
                'regex:/^[A-Z0-9]{2}$/',
                'unique:color_flavor,code'
            ],
            'type' => 'required|in:color,sabor',
        ]);

        DB::beginTransaction();
        try {
            $item = ColorFlavor::create($validated);
            DB::commit();
            return response()->json($item, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al crear: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un color/sabor específico.
     */
    public function show(ColorFlavor $colorFlavor)
    {
        return response()->json($colorFlavor);
    }

    /**
     * Actualizar un color/sabor existente.
     */
    public function update(Request $request, ColorFlavor $colorFlavor)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'hex'  => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'code' => [
                'sometimes',
                'string',
                'size:2',
                'regex:/^[A-Z0-9]{2}$/',
                'unique:color_flavor,code,' . $colorFlavor->id
            ],
            'type' => 'sometimes|in:color,sabor',
        ]);

        DB::beginTransaction();
        try {
            $colorFlavor->update($validated);
            DB::commit();
            return response()->json($colorFlavor->fresh());
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un color/sabor.
     */
    public function destroy(ColorFlavor $colorFlavor)
    {
        // Opcional: verificar si tiene productos asociados (si existe la relación)
        // if ($colorFlavor->products()->exists()) { ... }

        DB::beginTransaction();
        try {
            $colorFlavor->delete();
            DB::commit();
            return response()->json(['message' => 'Eliminado correctamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }
}
