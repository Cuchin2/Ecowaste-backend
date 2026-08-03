<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ColorFlavorType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ColorFlavorTypeController extends Controller
{
    public function index()
    {
        return ColorFlavorType::ordered()->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|unique:color_flavor_types',
            'order' => 'nullable|integer',
        ]);
        return ColorFlavorType::create($data);
    }

    public function update(Request $request, $id)
    {
        $type = ColorFlavorType::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|unique:color_flavor_types,name,'.$id,
            'order' => 'nullable|integer',
        ]);
        $type->update($data);
        return $type;
    }

    public function destroy($id)
    {
        $type = ColorFlavorType::findOrFail($id);
        // Verificar si tiene colores/sabores asociados
        if ($type->colorFlavors()->count() > 0) {
            return response()->json(['error' => 'No se puede eliminar porque tiene colores/sabores asociados.'], 422);
        }
        $type->delete();
        return response()->noContent();
    }

    public function reorder(Request $request)
    {
        $payload = $request->validate([
            '*.id' => 'required|exists:color_flavor_types,id',
            '*.order' => 'required|integer',
        ]);
        foreach ($payload as $item) {
            ColorFlavorType::where('id', $item['id'])->update(['order' => $item['order']]);
        }
        return response()->json(['message' => 'Reordenado correctamente']);
    }
}