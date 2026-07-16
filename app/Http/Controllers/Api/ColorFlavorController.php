<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ColorFlavor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ColorFlavorController extends Controller
{
    public function index()
    {
        $items = ColorFlavor::orderBy('type')->orderBy('name')->get();
        return response()->json($items);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'hex'  => [
                'required',
                'string',
                'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'
            ],
            'code' => [
                'required',
                'string',
                'size:2',
                'regex:/^[A-Z0-9]{2}$/',
                Rule::unique('color_flavor', 'code')
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
            return response()->json(['error' => 'Error al crear: ' . $e->getMessage()], 500);
        }
    }

    public function show(ColorFlavor $colorFlavor)
    {
        return response()->json($colorFlavor);
    }

    public function update(Request $request, ColorFlavor $colorFlavor)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'hex'  => [
                'sometimes',
                'string',
                'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'
            ],
            'code' => [
                'sometimes',
                'string',
                'size:2',
                'regex:/^[A-Z0-9]{2}$/',
                Rule::unique('color_flavor', 'code')->ignore($colorFlavor->id)
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
            return response()->json(['error' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(ColorFlavor $colorFlavor)
    {
        DB::beginTransaction();
        try {
            $colorFlavor->delete();
            DB::commit();
            return response()->json(['message' => 'Eliminado correctamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al eliminar: ' . $e->getMessage()], 500);
        }
    }
    public function reorder(Request $request)
    {
        DB::beginTransaction();
        try {
            foreach ($request->colors as $item) {
                ColorFlavor::where('id', $item['id'])->update(['order' => $item['order']]);
            }
            DB::commit();

            $colors = ColorFlavor::ordered()->get();
            return response()->json($colors);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al reordenar los colores/sabores: ' . $e->getMessage()
            ], 500);
        }
    }
}