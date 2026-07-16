<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    public function index()
    {
        $ingredients = Ingredient::orderBy('name')->get();
        return response()->json($ingredients);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:ingredients,name',
            'description' => 'nullable|string',
            'slug'        => 'nullable|string|max:255|unique:ingredients,slug',
        ]);

        try {
            $ingredient = Ingredient::create($validated);
            return response()->json($ingredient, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear: ' . $e->getMessage()], 500);
        }
    }

    public function show(Ingredient $ingredient)
    {
        return response()->json($ingredient);
    }

    public function update(Request $request, Ingredient $ingredient)
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255|unique:ingredients,name,'. $ingredient->id,
            'description' => 'nullable|string',
            'slug'        => 'nullable|string|max:255|unique:ingredients,slug,' . $ingredient->id,
        ]);

        try {
            $ingredient->update($validated);
            return response()->json($ingredient->fresh());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Ingredient $ingredient)
    {
        try {
            $ingredient->delete();
            return response()->json(['message' => 'Ingrediente eliminado correctamente']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar: ' . $e->getMessage()], 500);
        }
    }
            public function reorder(Request $request)
        {
            $order = $request->input('order');
            foreach ($order as $index => $id) {
                Ingredient::where('id', $id)->update(['order' => $index]);
            }

            return response()->json(['message' => 'Orden actualizado correctamente']);
        }
}