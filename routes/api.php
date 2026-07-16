<?php

use App\Http\Controllers\Api\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ImageController;
use App\Models\User;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\AvatarController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\PruebaController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\SizeController;
use App\Http\Controllers\Api\ColorFlavorController;
use App\Http\Controllers\Api\EmpaqueController;
use App\Http\Controllers\Api\SpecialController;
use App\Http\Controllers\Api\IngredientController;
use App\Http\Controllers\Api\AptitudeController;
use App\Http\Controllers\Api\DietController;
use App\Http\Controllers\Api\TraceController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\OctogonController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductSkuController;
use App\Http\Controllers\Api\ProductSkuImageController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\WishlistController;
use App\Models\ProductSku;
/**
 * RUTA PROTEGIDA POR SANCTUM
 */
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

/**
 * SOCIALITE: GOOGLE LOGIN
 * Estas rutas deben ir en `web.php`, pero si insistes en dejarlas en `api.php`, debes forzar la sesión.
 */
Route::middleware(['web'])->group(function () {

    Route::get('/auth/google/redirect', function () {
        return Socialite::driver('google')->redirect();
    });

    Route::get('/auth/google/callback', function () {
        // ❗ Usa stateless() si aún recibes InvalidStateException solo en desarrollo
        $googleUser = Socialite::driver('google')->stateless()->user();

        $user = User::updateOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'name' => $googleUser->getName(),
                'avatar' => $googleUser->getAvatar(),
                'email_verified_at' => now(),
            ]
        );

        Auth::login($user, remember: true);

        return redirect(config('app.frontend_url') . '/auth/google/success');
    });

});
Route::get('/images', [ImageController::class, 'index']);
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/images', [ImageController::class, 'store']);
    Route::post('/images/order', [ImageController::class, 'updateOrder']);
    Route::post('/images/{id}', [ImageController::class, 'update']);
    Route::delete('/images/{id}', [ImageController::class, 'destroy']);
    // Aquí puedes agregar más rutas protegidas
    // Route::get('/images', [ImageController::class, 'index']);
    // Route::delete('/images/{id}', [ImageController::class, 'destroy']);
});

Route::get('/hola', function () {
    return response()->json([
        'mensaje' => 'hola prueba de backend'
    ]);
});



Route::post('/login', [AuthController::class,'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class,'logout']);
    Route::put('/avatar', [AvatarController::class, 'update']);
    Route::get('/user', [AuthController::class,'user']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::get('/profile', [ProfileController::class, 'show']);
    // Direcciones
    Route::apiResource('addresses', AddressController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::patch('addresses/{address}/default', [AddressController::class, 'setDefault']);
    // Categorías
    Route::patch('/categories/reorder', [CategoryController::class, 'reorder']);
    Route::apiResource('categories', CategoryController::class);
    // Marcas
    Route::patch('brands/reorder', [BrandController::class, 'reorder']);
    Route::apiResource('brands', BrandController::class);
    // Tallas
    Route::get('sizes/tipos-unidad', [SizeController::class, 'tiposUnidad']);
    Route::patch('/sizes/reorder', [SizeController::class, 'reorder']);  
    Route::apiResource('sizes', SizeController::class);
    // Color / Sabor
    Route::patch('/color-flavor/reorder', [ColorFlavorController::class, 'reorder']);
    Route::apiResource('color-flavor', ColorFlavorController::class);
    // Empaques
    Route::patch('/empaques/reorder', [EmpaqueController::class, 'reorder']);
    Route::apiResource('empaques', EmpaqueController::class);
    // Especial
    Route::patch('specials/reorder', [SpecialController::class, 'reorder']);
    Route::apiResource('specials', SpecialController::class);
    // Ingredientes
    Route::patch('/ingredients/reorder', [IngredientController::class, 'reorder']);
    Route::apiResource('ingredients', IngredientController::class)->except('index');
    // Aptitudes
    Route::patch('aptitudes/reorder', [AptitudeController::class, 'reorder']);
    Route::apiResource('aptitudes', AptitudeController::class)->except('index');
    // Dieta
    Route::patch('diets/reorder', [DietController::class, 'reorder']);
    Route::apiResource('diets', DietController::class);
    // Trazas
    Route::get('traces/tipos', [TraceController::class, 'tipos']);
    Route::apiResource('traces', TraceController::class)->except('index');
    // Tags
    Route::apiResource('tags', TagController::class);
    // Octogonos o Sellos
    Route::patch('octogons/reorder', [OctogonController::class, 'reorder']);
    Route::apiResource('octogons', OctogonController::class);
    // Productos
    Route::patch('products/reorder', [ProductController::class, 'reorder']);
    Route::apiResource('products', ProductController::class);
    // Sku
    Route::prefix('products')->group(function () {
        Route::put('/{product}/skus/{sku}', [ProductSkuController::class, 'update']);
        Route::delete('/{product}/skus/{sku}', [ProductSkuController::class, 'destroy']);
    });
    Route::prefix('products/skus')->group(function () {
        Route::get('{sku}/images', [ProductSkuImageController::class, 'index']);
        Route::post('{sku}/images', [ProductSkuImageController::class, 'store']);
        Route::put('{sku}/images/reorder', [ProductSkuImageController::class, 'updateOrder']);
        Route::delete('{sku}/images/{image}', [ProductSkuImageController::class, 'destroy']);
    });
    // Carrito
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/items', [CartController::class, 'addItem']);
        Route::patch('/items/{itemId}', [CartController::class, 'updateItem']);
        Route::delete('/items/{itemId}', [CartController::class, 'removeItem']);
        Route::delete('/clear', [CartController::class, 'clear']);
        Route::post('/sync', [CartController::class, 'sync']); // sincronizar carrito local
    });

    // Wishlist
    Route::prefix('wishlist')->group(function () {
        Route::get('/', [WishlistController::class, 'index']);
        Route::post('/', [WishlistController::class, 'add']);
        Route::delete('/{skuId}', [WishlistController::class, 'remove']);
        Route::post('/{skuId}/move-to-cart', [WishlistController::class, 'moveToCart']);
        Route::patch('/{skuId}', [WishlistController::class, 'update']);
    });
});
// Rutas públicas
Route::get('/products-shop/{product}', [ProductSkuController::class, 'show']);
Route::get('/shop', [ProductController::class, 'shop']);
Route::post('/register', [RegisterController::class, 'register']);
Route::get('/categoriespublic', [CategoryController::class, 'publicIndex']);
Route::get('categories-flat', [CategoryController::class, 'flat']); // opcional
Route::get('traces', [TraceController::class, 'index']);
Route::get('aptitudes', [AptitudeController::class, 'index']);
Route::get('ingredients', [IngredientController::class, 'index']);
Route::get('/product-skus', [ProductSkuController::class, 'getByIds']);
/* Route::get('/pruebas/backend', [PruebaController::class, 'index']); */
/* Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    
}); */
