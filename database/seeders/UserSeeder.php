<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Administrator Demo', 'email' => 'admin@example.com', 'phone' => '+628111000001', 'role' => UserRole::Admin],
            ['name' => 'Staff Akademik', 'email' => 'staff@example.com', 'phone' => '+628111000002', 'role' => UserRole::Staff],
            ['name' => 'Staff Developer', 'email' => 'staff.dev@example.com', 'phone' => '+628111000003', 'role' => UserRole::Staff],
            ['name' => 'Customer Demo', 'email' => 'customer@example.com', 'phone' => '+628111000004', 'role' => UserRole::Customer],
            ['name' => 'Alya Pratama', 'email' => 'alya@example.com', 'phone' => '+628111000005', 'role' => UserRole::Customer],
            ['name' => 'Bagas Setiawan', 'email' => 'bagas@example.com', 'phone' => '+628111000006', 'role' => UserRole::Customer],
            ['name' => 'Citra Lestari', 'email' => 'citra@example.com', 'phone' => '+628111000007', 'role' => UserRole::Customer],
            ['name' => 'Dimas Mahendra', 'email' => 'dimas@example.com', 'phone' => '+628111000008', 'role' => UserRole::Customer],
        ];

        foreach ($users as $data) {
            User::query()->updateOrCreate(['email' => $data['email']], array_merge($data, [
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'is_active' => true,
            ]));
        }
    }
}
