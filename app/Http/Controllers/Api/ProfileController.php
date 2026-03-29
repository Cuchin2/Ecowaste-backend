<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'first_name' => 'required|string|max:255',
            'paternal_lastname' => 'required|string|max:255',
            'maternal_lastname' => 'required|string|max:255',
            'document_type' => ['required', Rule::in(['DNI', 'CE'])],
            'document_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('profiles', 'document_number')
                    ->ignore($user->profile?->id)  // ✅ permite actualizar el mismo registro
            ],
            'phone' => 'nullable|string|max:20',
        ]);

        // Actualizar el nombre del usuario (solo se guarda en `name`)
        $user->name = $request->first_name;
        $user->save();

        // Crear o actualizar el perfil
        $profile = $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            $request->only([
                'paternal_lastname',
                'maternal_lastname',
                'document_type',
                'document_number',
                'phone',
            ])
        );

        return response()->json([
            'message' => 'Datos actualizados correctamente',
            'user' => $user->fresh(),       // ← importante para que el frontend actualice el store
            'profile' => $profile,
        ]);
    }

    public function show(Request $request)
    {
        $user = $request->user();
        $profile = $user->profile; // relación hasOne
        return response()->json([
            'profile' => $profile,
            'user' => $user,       // ← para que el frontend tenga el nombre
        ]);
    }
}
