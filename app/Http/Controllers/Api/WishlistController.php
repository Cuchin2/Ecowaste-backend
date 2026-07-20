<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WishlistController extends Controller
{
    /**
     * Obtener todas las listas del usuario autenticado.
     */
    public function index(Request $request)
    {
        $lists = Wishlist::where('user_id', auth()->id())
            ->withCount('items')
            ->orderBy('order')
            ->get();

        return response()->json($lists);
    }

    /**
     * Crear una nueva lista de deseos.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string|max:500',
            'is_public'    => 'boolean',
            'is_default'   => 'boolean',
        ]);

        // Si se marca como default, desmarcar las demás
        if (isset($validated['is_default']) && $validated['is_default'] === true) {
            Wishlist::where('user_id', auth()->id())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $wishlist = Wishlist::create([
            'user_id'     => auth()->id(),
            'name'        => $validated['name'],
            'slug'        => Str::slug($validated['name']) . '-' . Str::random(6), // evitar colisiones
            'description' => $validated['description'] ?? null,
            'is_public'   => $validated['is_public'] ?? false,
            'is_default'  => $validated['is_default'] ?? false,
            'order'       => Wishlist::where('user_id', auth()->id())->max('order') + 1,
        ]);

        $wishlist->loadCount('items');

        return response()->json($wishlist, 201);
    }

    /**
     * Mostrar una lista específica (con sus items).
     */
    public function show(Wishlist $wishlist)
    {
        // Solo permitir si la lista pertenece al usuario autenticado
        if ($wishlist->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $wishlist->load(['items.sku.product.brand', 'items.sku.images', 'items.sku.colorFlavor', 'items.sku.empaque']);
        $wishlist->loadCount('items');

        return response()->json($wishlist);
    }

    /**
     * Actualizar una lista existente.
     */
    public function update(Request $request, Wishlist $wishlist)
    {
        if ($wishlist->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'description'  => 'nullable|string|max:500',
            'is_public'    => 'boolean',
            'is_default'   => 'boolean',
        ]);

        // Si se marca como default, desmarcar las demás
        if (isset($validated['is_default']) && $validated['is_default'] === true) {
            Wishlist::where('user_id', auth()->id())
                ->where('id', '!=', $wishlist->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        // Si cambia el nombre, regenerar slug
        if (isset($validated['name']) && $validated['name'] !== $wishlist->name) {
            $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(6);
        }

        $wishlist->update($validated);

        $wishlist->loadCount('items');

        return response()->json($wishlist);
    }

    /**
     * Eliminar una lista (y sus items en cascada).
     */
    public function destroy(Wishlist $wishlist)
    {
        if ($wishlist->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // Si la lista eliminada era la predeterminada, asignar otra como default
        if ($wishlist->is_default) {
            $newDefault = Wishlist::where('user_id', auth()->id())
                ->where('id', '!=', $wishlist->id)
                ->orderBy('order')
                ->first();

            if ($newDefault) {
                $newDefault->update(['is_default' => true]);
            }
        }

        $wishlist->delete();

        return response()->json(['message' => 'Lista eliminada correctamente']);
    }

    /**
     * Establecer una lista como predeterminada.
     */
    public function setDefault(Wishlist $wishlist)
    {
        if ($wishlist->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // Desmarcar todas las demás
        Wishlist::where('user_id', auth()->id())
            ->where('is_default', true)
            ->update(['is_default' => false]);

        // Marcar esta como default
        $wishlist->update(['is_default' => true]);

        return response()->json(['message' => 'Lista marcada como predeterminada']);
    }

    /**
     * Reordenar listas (drag & drop).
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|exists:wishlists,id',
            'order.*.order' => 'required|integer|min:0',
        ]);

        $user = auth()->id();

        foreach ($validated['order'] as $item) {
            Wishlist::where('id', $item['id'])
                ->where('user_id', $user)
                ->update(['order' => $item['order']]);
        }

        return response()->json(['message' => 'Orden actualizado correctamente']);
    }

    public function moveAllToCart(Wishlist $wishlist)
    {
        if ($wishlist->user_id !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $user = auth()->user();

        DB::transaction(function () use ($user, $wishlist) {
            // Cargar los items de la lista con sus SKU
            $items = $wishlist->items()->with('sku')->get();

            foreach ($items as $item) {
                // Buscar si el SKU ya está en el carrito del usuario
                $cartItem = CartItem::where('user_id', $user->id)
                    ->where('product_sku_id', $item->product_sku_id)
                    ->first();

                if ($cartItem) {
                    $cartItem->quantity += $item->quantity;
                    $cartItem->save();
                } else {
                    CartItem::create([
                        'user_id'         => $user->id,
                        'product_sku_id'  => $item->product_sku_id,
                        'quantity'        => $item->quantity,
                    ]);
                }
            }

            // Eliminar todos los items de la wishlist
            $wishlist->items()->delete();
        });

        return response()->json(['message' => 'Todos los items movidos al carrito correctamente']);
    }
}
