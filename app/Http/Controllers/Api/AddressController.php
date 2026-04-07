<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AddressController extends Controller
{
    /**
     * Obtener todas las direcciones del usuario autenticado.
     */
    public function index()
    {
        $addresses = Auth::user()->addresses()->orderBy('is_default', 'desc')->get();
        return response()->json($addresses);
    }

    /**
     * Crear una nueva dirección.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'reference' => 'nullable|string',
        ]);

        $user = Auth::user();

        // Si es la primera dirección del usuario, la marcamos como predeterminada
        $isDefault = $user->addresses()->count() === 0;

        $address = $user->addresses()->create([
            'name' => $validated['name'],
            'address' => $validated['address'],
            'reference' => $validated['reference'] ?? null,
            'is_default' => $isDefault,
            'verified' => false, // por defecto no verificada
        ]);

        return response()->json($address, 201);
    }

    /**
     * Actualizar una dirección existente.
     */
    public function update(Request $request, Address $address)
    {
        $this->authorize('update', $address); // opcional: puedes implementar Policy

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'reference' => 'nullable|string',
        ]);

        $address->update($validated);

        return response()->json($address);
    }

    /**
     * Eliminar una dirección.
     */
    public function destroy(Address $address)
    {
        $this->authorize('delete', $address);

        // Si la dirección a eliminar es la predeterminada, reasignamos la primera otra como predeterminada
        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $newDefault = Auth::user()->addresses()->first();
            if ($newDefault) {
                $newDefault->update(['is_default' => true]);
            }
        }

        return response()->json(['message' => 'Dirección eliminada']);
    }

    /**
     * Establecer una dirección como predeterminada.
     */
    public function setDefault(Address $address)
    {
        $this->authorize('update', $address);

        // Desmarcar cualquier otra dirección como predeterminada
        Auth::user()->addresses()->update(['is_default' => false]);

        // Marcar la nueva como predeterminada
        $address->update(['is_default' => true]);

        return response()->json(['message' => 'Dirección predeterminada actualizada']);
    }
}