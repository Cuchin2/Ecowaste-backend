<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PruebaController extends Controller
{
    public function index()
    {
        try {
            // 1. Crear rol 'admin' si no existe
            $roleAdmin = Role::firstOrCreate(['name' => 'admin']);

            // 2. Crear permiso 'Ver todas las categorías' si no existe
            $permission = Permission::firstOrCreate(['name' => 'Ver todas las categorías']);

            // 3. Asignar el permiso al rol (evitar duplicados)
            if (!$roleAdmin->hasPermissionTo($permission)) {
                $roleAdmin->givePermissionTo($permission);
            }

            // 4. Asignar el rol 'admin' a los usuarios con ID 1 y 2 (si existen)
            $users = User::whereIn('id', [1, 2])->get();
            foreach ($users as $user) {
                if (!$user->hasRole('admin')) {
                    $user->assignRole('admin');
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Rol, permiso y asignaciones realizadas correctamente.',
                'role' => $roleAdmin->name,
                'permission' => $permission->name,
                'users_updated' => $users->pluck('id')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}