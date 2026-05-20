<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

trait UploadsImages
{
    protected function uploadImage(?UploadedFile $file, string $folder = 'images'): ?string
    {
          if (!$file) return null;

        $extension = $file->getClientOriginalExtension();

        // SVG se guarda directamente sin conversión
        if ($extension === 'svg') {
            $filename = Str::uuid() . '.svg';
            $relativePath = $folder . '/' . $filename;
            $fullPath = storage_path('app/public/' . $relativePath);
            $dir = dirname($fullPath);
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            copy($file->getRealPath(), $fullPath);
            return $relativePath;
        }

        // Imágenes rasterizadas → convertir a WebP
        $img = ImageManager::gd()->read($file);
        $filename = Str::uuid() . '.webp';
        $relativePath = $folder . '/' . $filename;
        $fullPath = storage_path('app/public/' . $relativePath);
        $dir = dirname($fullPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $img->toWebp(75)->save($fullPath); // calidad ajustable
        return $relativePath;
    }

    protected function deleteImage(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}