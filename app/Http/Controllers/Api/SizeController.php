<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Size;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SizeController extends Controller
{
    /**
     * Listar todas las tallas (sin orden específico, puedes ordenar por nombre)
     */
    public function index()
    {
        $sizes = Size::orderBy('name')->get();
        return response()->json($sizes);
    }

    /**
     * Crear una nueva talla
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'tipo_unidad' => 'required|string|max:20',
            'codigo'      => [
                'required',
                'string',
                'size:2',
                'regex:/^[A-Z0-9]{2}$/',
                'unique:sizes,codigo'
            ],
        ]);

        DB::beginTransaction();
        try {
            $size = Size::create($validated);
            DB::commit();
            return response()->json($size, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al crear la talla: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar una talla específica
     */
    public function show(Size $size)
    {
        return response()->json($size);
    }

    /**
     * Actualizar una talla existente
     */
    public function update(Request $request, Size $size)
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'tipo_unidad' => 'sometimes|string|max:20',
            'codigo'      => [
                'sometimes',
                'string',
                'size:2',
                'regex:/^[A-Z0-9]{2}$/',
                'unique:sizes,codigo,' . $size->id
            ],
        ]);

        DB::beginTransaction();
        try {
            $size->update($validated);
            DB::commit();
            return response()->json($size->fresh());
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al actualizar la talla: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una talla (opcionalmente puedes verificar si tiene productos asociados)
     */
    public function destroy(Size $size)
    {
        // Opcional: if ($size->products()->exists()) { ... }

        DB::beginTransaction();
        try {
            $size->delete();
            DB::commit();
            return response()->json(['message' => 'Talla eliminada correctamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al eliminar la talla: ' . $e->getMessage()
            ], 500);
        }
    }
}