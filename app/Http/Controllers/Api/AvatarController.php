<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AvatarController extends Controller
{
    /**
     * Actualizar el avatar del usuario autenticado.
     */
    public function update(Request $request)
    {
        $request->validate([
            'avatar' => 'required|string',
        ]);

        $user = $request->user();
        $avatarInput = $request->input('avatar');

        // Procesar el avatar (igual que en RegisterController)
        $newAvatarPath = $this->processAvatar($avatarInput, $user);

        // Actualizar el usuario
        $user->avatar = $newAvatarPath;
        $user->save();

        // Devolver la URL completa del avatar (para que el frontend la use sin preocuparse de rutas)
        return response()->json([
            'message' => 'Avatar actualizado correctamente',
            'avatar'  => $newAvatarPath,
        ]);
    }

    /**
     * Procesa el avatar: si es base64, lo convierte a WebP y guarda; si es URL, lo retorna tal cual.
     * Si el usuario ya tenía un avatar que era un archivo local, lo elimina.
     */
    private function processAvatar(?string $avatar, $user = null): ?string
    {
        if (!$avatar) {
            return null;
        }

        // Si es base64, guardar como WebP
        if (preg_match('/^data:image\/(\w+);base64,/', $avatar)) {
            // Eliminar avatar anterior si era un archivo local (no una URL externa)
            if ($user && $user->avatar && !$this->isExternalUrl($user->avatar)) {
                $oldPath = str_replace('storage/', '', $user->avatar);
                Storage::disk('public')->delete($oldPath);
            }

            return $this->saveBase64ImageAsWebp($avatar);
        }

        // En cualquier otro caso, asumimos que es una URL o ruta predefinida y la devolvemos tal cual
        return $avatar;
    }

    /**
     * Determina si una cadena es una URL externa (absoluta).
     */
    private function isExternalUrl(string $path): bool
    {
        return filter_var($path, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Guarda una imagen en base64 como WebP en el disco público.
     *
     * @return string Ruta pública relativa (ej. 'storage/avatars/uuid.webp')
     */
    private function saveBase64ImageAsWebp(string $base64): string
    {
        $imageData = substr($base64, strpos($base64, ',') + 1);
        $imageData = base64_decode($imageData);

        $image = @imagecreatefromstring($imageData);
        if (!$image) {
            throw new \Exception('La imagen no es válida o el formato no está soportado.');
        }

        $fileName = 'avatars/' . Str::uuid() . '.webp';
        $path = Storage::disk('public')->path($fileName);

        imagewebp($image, $path, 85);
        imagedestroy($image);

        return 'storage/' . $fileName;
    }

    /**
     * Convierte una ruta relativa o absoluta en una URL completa accesible desde el frontend.
     */
    private function getAvatarUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        // Si ya es una URL completa, devolverla
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        // Si es una ruta que comienza con 'storage/', construir URL completa con APP_URL
        if (str_starts_with($path, 'storage/')) {
            return config('app.url') . '/' . $path;
        }

        // Para rutas que empiezan con '/assets' (avatares predefinidos), también devolver URL completa
        // Esto evita problemas de rutas relativas en el frontend cuando la app está en una subcarpeta.
        return config('app.url') . '/' . ltrim($path, '/');
    }
}