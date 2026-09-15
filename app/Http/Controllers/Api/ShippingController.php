<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipping;
use App\Traits\UploadsImages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ShippingController extends Controller
{
    use UploadsImages; // ⬅️ usamos el trait

    /**
     * Obtener la URL completa de la imagen
     */
    private function imageUrl(?string $path): ?string
    {
        return $path ? Storage::url($path) : null;
    }

    /**
     * Formatear el modelo para incluir la URL completa de la imagen
     */
    private function format(Shipping $shipping): Shipping
    {
        // 'url' en la BD ya tiene algo como: "shippings/abc123.jpg"
        // getStorageUrl en el frontend se encargará de agregar el dominio base.
        $shipping->image_url = $shipping->url; 
        return $shipping;
    }

    /**
     * Listar shippings (filtrado opcional por state)
     */
    public function index(Request $request)
    {
        $query = Shipping::query();

        // Filtrar por estado si se proporciona (district, nacional, internacional)
        if ($request->has('state')) {
            $query->where('state', $request->state);
        }

        $shippings = $query->orderBy('order')->get();
        
        return response()->json($shippings->map(fn($s) => $this->format($s)));
    }

    /**
     * Crear nuevo shipping
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'order'       => 'nullable|integer',
            'price'       => 'nullable|numeric|min:0',
            'url'         => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:2048', // El campo 'url' recibe el archivo de imagen
            'title'       => 'nullable|string|max:255',
            'state'       => 'required|in:district,nacional,internacional',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
        ]);

        try {
            // Si no se envía un orden, calcular el siguiente para ese estado específico
            if (!isset($data['order'])) {
                $maxOrder = Shipping::where('state', $data['state'])->max('order');
                $data['order'] = ($maxOrder ?? 0) + 1;
            }

            // Subir imagen si existe
            if ($request->hasFile('url')) {
                $data['url'] = $this->uploadImage($request->file('url'), 'shippings');
            }

            $shipping = Shipping::create($data);
            
            return response()->json($this->format($shipping), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mostrar shipping específico
     */
    public function show(Shipping $shipping)
    {
        return response()->json($this->format($shipping));
    }

    /**
     * Actualizar shipping
     */
    public function update(Request $request, Shipping $shipping)
    {
        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'order'       => 'nullable|integer',
            'price'       => 'nullable|numeric|min:0',
            'url'         => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
            'title'       => 'nullable|string|max:255',
            'state'       => 'sometimes|required|in:district,nacional,internacional',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
            'remove_url'  => 'sometimes|boolean', // Bandera para eliminar la imagen actual
        ]);

        try {
            // 1. Manejo de eliminación de imagen
            if ($request->boolean('remove_url') && $shipping->url) {
                $this->deleteImage($shipping->url);
                $shipping->url = null;
                $shipping->save();
            }

            // 2. Manejo de nueva imagen
            if ($request->hasFile('url')) {
                if ($shipping->url) {
                    $this->deleteImage($shipping->url);
                }
                $data['url'] = $this->uploadImage($request->file('url'), 'shippings');
            } else {
                // Si no hay nuevo archivo, evitar que Laravel intente actualizar el campo 'url' con null o datos inválidos
                unset($data['url']);
            }

            $shipping->update($data);
            
            return response()->json($this->format($shipping->fresh()));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar shipping
     */
    public function destroy(Shipping $shipping)
    {
        try {
            if ($shipping->url) {
                $this->deleteImage($shipping->url);
            }
            $shipping->delete();
            
            return response()->json(['message' => 'Método de envío eliminado correctamente']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reordenar shippings
     * Recibe un array de objetos con el ID
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'shippings' => 'required|array',
            'shippings.*.id' => 'exists:shippings,id',
        ]);

        try {
            DB::transaction(function () use ($request) {
                foreach ($request->shippings as $index => $item) {
                    Shipping::where('id', $item['id'])->update(['order' => $index + 1]);
                }
            });
            
            return response()->json(['message' => 'Orden actualizado correctamente']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al reordenar: ' . $e->getMessage()], 500);
        }
    }
        /**
     * Asignar un método de envío a la orden de venta activa del usuario logeado
     */
    public function assignToOrder(Request $request)
    {
        // 1. Validar que el ID del envío exista
        $validated = $request->validate([
            'shipping_id' => 'required|exists:shippings,id',
        ]);

        try {
            // 2. Obtener el usuario autenticado
            $user = auth()->user();

            if (!$user) {
                return response()->json(['error' => 'No autorizado. Debes iniciar sesión.'], 401);
            }

            // 3. Buscar la orden de venta activa del usuario.
            // IMPORTANTE: Ajusta 'status' => 'pending' según la lógica real de tu proyecto 
            // (ej: 'cart', 'pending', 'draft', etc.)
            $saleOrder = $user->saleOrders()->where('status', 'pending')->first();

            if (!$saleOrder) {
                return response()->json([
                    'error' => 'No se encontró una orden de venta activa para este usuario.'
                ], 404);
            }

            // 4. Actualizar la orden con el método de envío seleccionado
            $saleOrder->update([
                'shipping_id' => $validated['shipping_id']
            ]);

            // 5. (Opcional pero recomendado) Recargar la relación para devolver los datos actualizados
            // Asegúrate de tener la relación 'shipping' definida en el modelo SaleOrder
            $saleOrder->load('shipping');

            /* 
             * NOTA: Si tu lógica de negocio requiere recalcular el total de la orden 
             * al cambiar el envío (sumando el precio del shipping), hazlo aquí.
             * Ej: $saleOrder->recalculateTotals(); $saleOrder->save();
             */

            return response()->json([
                'success' => true,
                'message' => 'Método de envío asignado correctamente',
                'data' => $saleOrder
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al asignar el envío: ' . $e->getMessage()
            ], 500);
        }
    }
}
