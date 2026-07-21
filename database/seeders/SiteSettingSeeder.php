<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = ['brand_name' => 'Jokiinlah', 'brand_tagline' => 'Pendampingan Akademik & Digital', 'academic_integrity_notice' => 'Dilarang menggunakan layanan untuk plagiarisme, fabrikasi data, pemalsuan penelitian, pengerjaan ujian, atau pelanggaran integritas akademik.'];
        foreach ($settings as $key => $value) {
            SiteSetting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'type' => 'string', 'group' => 'general', 'is_public' => true]);
        }
    }
}
