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

    Route::apiResource('addresses', AddressController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::patch('addresses/{address}/default', [AddressController::class, 'setDefault']);
});

Route::post('/register', [RegisterController::class, 'register']);

Route::apiResource('categories', CategoryController::class);
Route::get('categories-flat', [CategoryController::class, 'flat']); // opcional