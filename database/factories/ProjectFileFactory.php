<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProjectFileFactory extends Factory
{
    public function definition(): array
    {
        $document = (string) Str::uuid();
        $stored = (string) Str::uuid();

        return ['project_id' => Project::factory(), 'uploaded_by' => User::factory(), 'document_uuid' => $document, 'version' => 1, 'category' => 'dokumen_awal', 'original_name' => 'dokumen-proyek.pdf', 'stored_name' => $stored, 'disk' => 'local', 'file_path' => 'private/projects/dummy/'.$stored.'.pdf', 'file_type' => 'application/pdf', 'file_size' => 102400, 'checksum' => hash('sha256', $stored), 'description' => 'Metadata file dummy; berkas fisik tidak disertakan.', 'retention_until' => now()->addDays(180)];
    }
}
