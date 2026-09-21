<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::create([
            'name' => 'IT Support Admin',
            'email' => 'admin@support.com',
            'password' => Hash::make('password'),
            'role' => 'it_support',
        ]);

        User::create([
            'name' => 'Jane Staff',
            'email' => 'jane@staff.com',
            'password' => Hash::make('password'),
            'role' => 'staff',
        ]);
        
        User::create([
            'name' => 'John Staff',
            'email' => 'john@staff.com',
            'password' => Hash::make('password'),
            'role' => 'staff',
        ]);
    }
}
