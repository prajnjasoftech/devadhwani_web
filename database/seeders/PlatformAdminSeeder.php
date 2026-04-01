<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            [
                'contact_number' => '9999999999',
            ],
            [
                'user_type' => 'platform_admin',
                'name' => 'Platform Administrator',
                'email' => 'admin@devadhwani.com',
                'password' => Hash::make('Admin@123'),
                'must_reset_password' => true,
                'is_active' => true,
            ]
        );

        $this->command->info('Platform admin created!');
        $this->command->info('Contact Number: 9999999999');
        $this->command->info('Password: Admin@123');
    }
}
