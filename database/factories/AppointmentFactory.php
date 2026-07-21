<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    public function definition(): array { return ['project_id' => Project::factory(), 'customer_id' => User::factory()->customer(), 'staff_id' => User::factory()->staff(), 'title' => 'Konsultasi Progres Proyek', 'appointment_date' => now()->addDays(2), 'meeting_link' => 'https://meet.google.com/example', 'notes' => 'Siapkan daftar pertanyaan.', 'status' => AppointmentStatus::Scheduled]; }
}
