<?php

namespace Database\Seeders;

use App\Enums\ServiceCategory;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['Konsultasi Skripsi dan Penelitian', 'konsultasi-skripsi-penelitian', ServiceCategory::Academic, 'Pendampingan terarah untuk perencanaan dan penyusunan penelitian.'],
            ['Paper dan Publikasi', 'paper-dan-publikasi', ServiceCategory::Academic, 'Proofreading, formatting, dan kesiapan naskah publikasi.'],
            ['Analisis Data Penelitian', 'analisis-data-penelitian', ServiceCategory::DataAnalysis, 'Analisis statistik dan interpretasi data yang transparan.'],
            ['Pengembangan Website', 'pengembangan-website', ServiceCategory::Web, 'Website profesional, responsif, aman, dan terdokumentasi.'],
            ['Pengembangan Aplikasi Mobile', 'pengembangan-aplikasi-mobile', ServiceCategory::Mobile, 'Aplikasi mobile sesuai alur bisnis dan kebutuhan pengguna.'],
            ['Pengembangan Aplikasi Desktop', 'pengembangan-aplikasi-desktop', ServiceCategory::Desktop, 'Aplikasi desktop untuk kebutuhan operasional terukur.'],
            ['Dashboard Bisnis', 'dashboard-bisnis', ServiceCategory::InformationSystem, 'Visualisasi indikator bisnis yang ringkas dan informatif.'],
            ['Sistem Informasi', 'sistem-informasi', ServiceCategory::InformationSystem, 'Sistem terintegrasi untuk mengelola proses dan data organisasi.'],
        ];

        foreach ($items as $index => [$name, $slug, $category, $short]) {
            Service::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'category' => $category, 'short_description' => $short, 'description' => $short.' Ruang lingkup ditetapkan melalui konsultasi dan persetujuan kebutuhan.', 'features' => ['Konsultasi kebutuhan', 'Proses transparan', 'Dokumentasi hasil'], 'technologies' => $category === ServiceCategory::Academic ? ['Metodologi penelitian'] : ['Laravel', 'MySQL', 'Tailwind CSS'], 'icon' => 'briefcase-business', 'is_active' => true, 'sort_order' => $index + 1]);
        }
    }
}
