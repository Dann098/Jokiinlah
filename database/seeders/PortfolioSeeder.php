<?php

namespace Database\Seeders;

use App\Models\Portfolio;
use Illuminate\Database\Seeder;

class PortfolioSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['Dashboard Monitoring Penjualan', 'dashboard-monitoring-penjualan', 'Sistem Informasi'],
            ['Portal Akademik Terintegrasi', 'portal-akademik-terintegrasi', 'Website'],
            ['Aplikasi Inventaris Mobile', 'aplikasi-inventaris-mobile', 'Mobile'],
            ['Analisis Survei Kepuasan', 'analisis-survei-kepuasan', 'Analisis Data'],
            ['Sistem Reservasi Layanan', 'sistem-reservasi-layanan', 'Website'],
            ['Aplikasi Administrasi Desktop', 'aplikasi-administrasi-desktop', 'Desktop'],
        ];
        foreach ($items as [$title, $slug, $category]) {
            Portfolio::query()->updateOrCreate(['slug' => $slug], ['title' => $title, 'category' => $category, 'description' => 'Studi kasus demonstrasi solusi digital yang dibangun berdasarkan kebutuhan pengguna.', 'problem' => 'Data dan alur kerja belum terpusat.', 'solution' => 'Membangun solusi terstruktur dengan kontrol akses dan dokumentasi.', 'result' => 'Proses menjadi lebih terukur dan mudah dipantau.', 'technologies' => ['Laravel', 'MySQL', 'Tailwind CSS'], 'gallery' => [], 'is_published' => true]);
        }
    }
}
