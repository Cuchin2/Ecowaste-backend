<?php

namespace App\Http\Controllers\Api;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class PruebaController extends Controller
{
    public function index(){
        $roleAdmin = Role::create(['name' => 'admin']);

        $permission = Permission::create(['name' => 'Ver todas las categorías']);
        $roleAdmin->givePermissionTo($permission);
        $user = User::find(1);
        $user->assignRole('admin');
        $user = User::find(2);
        $user->assignRole('admin');
    }
}
