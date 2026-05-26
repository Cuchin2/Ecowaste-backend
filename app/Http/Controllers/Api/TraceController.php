<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trace;
use Illuminate\Http\Request;

class TraceController extends Controller
{
    /**
     * Listar todas las trazas (ordenadas por nombre).
     */
    public function index()
    {
        $traces = Trace::orderBy('name')->get();
        return response()->json($traces);
    }

    /**
     * Crear una nueva traza.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'type'        => 'required|string|max:100',
            'level'       => 'required|in:contains,may_contain,free',
        ]);

        try {
            $trace = Trace::create($validated);
            return response()->json($trace, 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al crear: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar una traza específica.
     */
    public function show(Trace $trace)
    {
        return response()->json($trace);
    }

    /**
     * Actualizar una traza existente.
     */
    public function update(Request $request, Trace $trace)
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type'        => 'sometimes|string|max:100',
            'level'       => 'sometimes|in:contains,may_contain,free',
        ]);

        try {
            $trace->update($validated);
            return response()->json($trace->fresh());
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una traza.
     */
    public function destroy(Trace $trace)
    {
        // Opcional: verificar si tiene productos asociados (cuando relaciones)
        // if ($trace->products()->exists()) { ... }

        try {
            $trace->delete();
            return response()->json(['message' => 'Traza eliminada correctamente']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener los tipos de alergeno únicos (para usar en un select).
     */
    public function tipos()
    {
        $tipos = Trace::select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type');

        return response()->json($tipos);
    }
}