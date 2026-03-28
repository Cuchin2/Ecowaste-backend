<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        // Manejar el avatar: si es base64, convertirlo a webp y guardar; si es URL, dejarlo igual; si es null, poner por defecto
        $avatarPath = $this->handleAvatar($data['avatar'] ?? null);
        // Si después de handleAvatar sigue siendo null, asignar el avatar por defecto
        if ($avatarPath === null) {
            $avatarPath = 'storage/assets/img/avatars/photo-avatar-vacio.webp';
        }

        // Crear el usuario
        $user = User::create([
            'name' => $data['name'],
            'lastname' => $data['lastname'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'avatar' => $avatarPath,
        ]);

        return response()->json([
            'message' => 'Usuario registrado exitosamente',
            'user' => $user,
        ], 201);
    }

    /**
     * Maneja la imagen de avatar:
     * - Si es null → retorna null
     * - Si es una URL (imagen predeterminada) → retorna la URL tal cual
     * - Si es base64 → la convierte a webp, guarda en storage y retorna la ruta pública
     */
    private function handleAvatar(?string $avatar): ?string
    {
        if (!$avatar) {
            return null;
        }

        // Si es base64 (empieza con data:image/...), la convertimos a webp y guardamos
        if (preg_match('/^data:image\/(\w+);base64,/', $avatar)) {
            return $this->saveBase64ImageAsWebp($avatar);
        }

        // En cualquier otro caso, asumimos que es una ruta (URL absoluta, relativa, etc.) y la devolvemos directamente
        return $avatar;
    }

    /**
     * Guarda una imagen en base64 como WebP en el disco público y retorna la ruta.
     */
    private function saveBase64ImageAsWebp(string $base64): string
    {
        // Decodificar el base64
        $imageData = substr($base64, strpos($base64, ',') + 1);
        $imageData = base64_decode($imageData);

        // Crear imagen desde string
        $image = @imagecreatefromstring($imageData);
        if (!$image) {
            throw new \Exception('La imagen proporcionada no es válida o el formato no es soportado.');
        }

        // Generar nombre único con extensión webp
        $fileName = 'avatars/' . Str::uuid() . '.webp';
        $path = Storage::disk('public')->path($fileName);

        // Guardar como webp con calidad 85
        imagewebp($image, $path, 85);
        imagedestroy($image);

        // Retornar la ruta pública (accesible vía /storage/...)
        return 'storage/' . $fileName;
    }
}