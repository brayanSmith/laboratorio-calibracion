<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed the platform administrator user.
     */
    public function run(): void
    {
        $admin = User::firstOrNew(['email' => 'admin@admin.com']);

        $admin->forceFill([
            'name' => 'Administrador',
            'password' => 'password123',
            'is_platform_admin' => true,
            'email_verified_at' => now(),
        ])->save();
    }
}
