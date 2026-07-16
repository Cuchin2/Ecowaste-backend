<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empaque;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class EmpaqueController extends Controller
{
    /**
     * Listar todos los empaques (ordenados por nombre).
     */
    public function index()
    {
        $empaques = Empaque::ordered()->get();
        return response()->json($empaques);
    }

    /**
     * Crear un nuevo empaque.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'size:1',
                'regex:/^[A-Z0-9]{1}$/',
                'unique:empaques,code'
            ],
            'tipo' => 'required|boolean',
        ]);

        try {
            $empaque = Empaque::create($validated);
            return response()->json($empaque, 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al crear: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un empaque específico.
     */
    public function show(Empaque $empaque)
    {
        return response()->json($empaque);
    }

    /**
     * Actualizar un empaque existente.
     */
    public function update(Request $request, Empaque $empaque)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => [
                'sometimes',
                'string',
                'size:1',
                'regex:/^[A-Z0-9]{1}$/',
                'unique:empaques,code,' . $empaque->id
            ],
            'tipo' => 'sometimes|boolean',
        ]);

        try {
            $empaque->update($validated);
            return response()->json($empaque->fresh());
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un empaque.
     */
    public function destroy(Empaque $empaque)
    {
        // Opcional: verificar si tiene productos asociados
        // if ($empaque->products()->exists()) { ... }

        try {
            $empaque->delete();
            return response()->json(['message' => 'Empaque eliminado correctamente']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }
    public function reorder(Request $request)
    {
        DB::beginTransaction();
        try {
            foreach ($request->items as $item) {
                Empaque::where('id', $item['id'])->update(['order' => $item['order']]);
            }
            DB::commit();

            $colors = Empaque::ordered()->get();
            return response()->json($colors);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al reordenar los empaques: ' . $e->getMessage()
            ], 500);
        }
    }
}