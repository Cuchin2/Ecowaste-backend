<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Profile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'paternal_lastname' => 'required|string|max:255',
            'maternal_lastname' => 'required|string|max:255',
            'document_type' => ['required', Rule::in(['DNI', 'CE'])],
            'document_number' => 'required|string|max:20|unique:profiles,document_number,' . Auth::id() . ',user_id',
            'phone' => 'nullable|string|max:20',
        ]);

        $user = Auth::user();

        $profile = $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            $request->only([
                'first_name',
                'paternal_lastname',
                'maternal_lastname',
                'document_type',
                'document_number',
                'phone',
            ])
        );

        return response()->json([
            'message' => 'Perfil actualizado correctamente',
            'profile' => $profile,
        ]);
    }
}
