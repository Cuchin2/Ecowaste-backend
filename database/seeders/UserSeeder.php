<?php

namespace Database\Seeders;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Nubia Romero',
            'email' => 'nubesita02@gmail.com',
            'avatar' => 'storage/images/photo-perfil.jpg',
            'password'=> Hash::make('lalala123123'),
            'email_verified_at'=>now()
        ]);
        User::create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'avatar' => 'storage/images/photo-autor.png',
            'password'=> Hash::make('lalala123123'),
            'email_verified_at'=>now()
        ]);
    }
}
