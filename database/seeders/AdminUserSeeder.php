<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminEmail = config('app.admin_email', env('ADMIN_EMAIL', 'paul@filter.agency'));

        User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Paul Halfpenny',
                'role' => Role::Admin,
                'is_active' => true,
                'weekly_capacity_hours' => 37.50,
            ]
        );
    }
}
