<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use App\Models\Image;
use Illuminate\Support\Facades\File;
class ImageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
            $images = Image::orderBy('order')->get();
            return response()->json($images);
    }

    /**
     * Store a newly created resource in storage.
     */
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'image' => 'required|image|mimes:jpeg,jpg,png,jpg,gif,webp,svg',
    ]);

    // Procesar imagen y convertir a .webp
    $manager = new ImageManager(new Driver());
    $uploadedFile = $request->file('image');
    $filename = uniqid() . '.webp';
    $path = public_path('/storage/images/' . $filename);

    $image = $manager->read($uploadedFile->getRealPath());
    $image->toWebp(80)->save($path);

    // Calcular orden automático
    $maxOrder = Image::max('order');
    $nextOrder = $maxOrder ? $maxOrder + 1 : 1;

    // Guardar en base de datos
    $imageModel = Image::create([
        'name' => $validated['name'],
        'description' => $validated['description'] ?? '',
        'url' => 'images/' . $filename,
        'order' => $nextOrder,
    ]);

    return response()->json($imageModel, 201);
}


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
public function update(Request $request, $id)
{
    $imageModel = Image::findOrFail($id);

    // Validación
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'image' => 'nullable|image|mimes:jpeg,jpg,png,jpg,gif,webp,svg',
    ]);

    $imageModel->name = $validated['name'];
    $imageModel->description = $validated['description'] ?? $imageModel->description;

    if ($request->hasFile('image')) {
        // Borrar la imagen anterior si existe
        if ($imageModel->url && File::exists(public_path('storage/' . $imageModel->url))) {
            File::delete(public_path('storage/' . $imageModel->url));
        }

        // Procesar nueva imagen como .webp
        $manager = new ImageManager(new Driver());
        $uploadedFile = $request->file('image');
        $filename = uniqid() . '.webp';
        $path = public_path('/storage/images/' . $filename);

        $image = $manager->read($uploadedFile->getRealPath());
        $image->toWebp(80)->save($path);

        $imageModel->url = 'images/' . $filename;
    }

    $imageModel->save();

    return response()->json($imageModel);
}

    /**
     * Remove the specified resource from storage.
     */
public function destroy(string $id)
{
    $image = Image::findOrFail($id);

    // Eliminar archivo físico
    if ($image->url && File::exists(public_path('storage/' . $image->url))) {
        File::delete(public_path('storage/' . $image->url));
    }

    // Eliminar registro de la base de datos
    $image->delete();

    return response()->json(['message' => 'Imagen eliminada correctamente.']);
}
public function updateOrder(Request $request)
{
    $data = $request->all();

    foreach ($data as $item) {
        Image::where('id', $item['id'])->update(['order' => $item['order']]);
    }

    return response()->json(['message' => 'Orden actualizado correctamente']);
}

}
