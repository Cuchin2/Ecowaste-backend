<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            "email" => ["required", "email"],
            "password" => ["required"]
        ]);

        $user = User::where("email", $credentials["email"])->first();

        if (!$user || !Hash::check($credentials["password"], $user->password)) {
            return response()->json([
                "message" => "Credenciales incorrectas"
            ], 401);
        }

        $user->tokens()->delete();

        $token = $user->createToken("react-token")->plainTextToken;

        return response()->json([
            "token" => $token,
            "user" => $user
        ]);
    }

    public function user(Request $request)
    {
        return $request->user();
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            "message" => "Logout correcto"
        ]);
    }
    public function changePassword(Request $request)
{
    $request->validate([
        'current_password' => 'required',
        'new_password' => 'required|confirmed|min:8'
    ]);

    $user = $request->user();

    if (!Hash::check($request->current_password, $user->password)) {
        return response()->json([
            'message' => 'La contraseña actual no es correcta'
        ], 401);
    }

    $user->password = Hash::make($request->new_password);
    $user->save();

    return response()->json([
        'message' => 'Contraseña actualizada correctamente'
    ]);
}
}
