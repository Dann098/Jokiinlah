<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Seeder;

class ActivityLogSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        ActivityLog::query()->firstOrCreate(
            ['action' => 'demo.foundation_seeded', 'description' => 'Fondasi domain Tahap 2 telah diisi dengan data demonstrasi.'],
            ['user_id' => $admin->id, 'metadata' => ['is_demo' => true], 'ip_address' => '127.0.0.1', 'user_agent' => 'DatabaseSeeder'],
        );
    }
}
