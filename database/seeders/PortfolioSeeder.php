<?php

namespace Database\Seeders;

use App\Models\Portfolio;
use Illuminate\Database\Seeder;

class PortfolioSeeder extends Seeder
{
    private const PUBLISHED_PORTFOLIO_SLUG = 'analisis-survei-kepuasan';

    public function run(): void
    {
        $items = [
            ['title' => 'Dashboard Monitoring Penjualan', 'slug' => 'dashboard-monitoring-penjualan', 'category' => 'Sistem Informasi'],
            ['title' => 'Portal Akademik Terintegrasi', 'slug' => 'portal-akademik-terintegrasi', 'category' => 'Website'],
            ['title' => 'Aplikasi Inventaris Mobile', 'slug' => 'aplikasi-inventaris-mobile', 'category' => 'Mobile'],
            [
                'title' => 'Analisis Survei Kepuasan',
                'slug' => 'analisis-survei-kepuasan',
                'category' => 'Analisis Data',
                'data' => [
                    'description' => 'Dashboard analisis data interaktif untuk mengukur tingkat kepuasan, membandingkan hasil berdasarkan kelompok responden, mengidentifikasi aspek prioritas, dan menyusun rekomendasi berdasarkan data survei.',
                    'problem' => 'Data survei yang hanya tersimpan dalam spreadsheet membuat hasil kepuasan sulit dipahami, terutama ketika perlu membandingkan jawaban berdasarkan periode, kelompok responden, dan aspek layanan.',
                    'solution' => 'Membangun dashboard analisis interaktif yang menyediakan pembersihan data, indikator kepuasan, Net Promoter Score, analisis aspek, segmentasi responden, tren, dan rekomendasi berbasis aturan.',
                    'result' => 'Hasil survei dapat diringkas dan dieksplorasi melalui visualisasi yang lebih terstruktur sehingga aspek dengan performa tinggi dan area yang membutuhkan perbaikan lebih mudah dikenali.',
                    'technologies' => ['Python', 'Pandas', 'Streamlit', 'Plotly', 'OpenPyXL'],
                    'thumbnail' => 'images/portfolios/analisis-survei-kepuasan/01-ringkasan-dashboard.png',
                    'gallery' => [
                        'images/portfolios/analisis-survei-kepuasan/01-ringkasan-dashboard.png',
                        'images/portfolios/analisis-survei-kepuasan/02-analisis-aspek.png',
                        'images/portfolios/analisis-survei-kepuasan/03-segmentasi-responden.png',
                        'images/portfolios/analisis-survei-kepuasan/04-tren-kepuasan.png',
                        'images/portfolios/analisis-survei-kepuasan/05-analisis-komentar.png',
                        'images/portfolios/analisis-survei-kepuasan/06-data-responden.png',
                        'images/portfolios/analisis-survei-kepuasan/07-upload-dan-validasi.png',
                        'images/portfolios/analisis-survei-kepuasan/08-dashboard-mobile.png',
                    ],
                    'repository_url' => 'https://github.com/Dann098/analisis-survei-kepuasan',
                    'is_demo' => false,
                ],
            ],
            ['title' => 'Sistem Reservasi Layanan', 'slug' => 'sistem-reservasi-layanan', 'category' => 'Website'],
            ['title' => 'Aplikasi Administrasi Desktop', 'slug' => 'aplikasi-administrasi-desktop', 'category' => 'Desktop'],
        ];

        $demoDefaults = [
            'description' => 'Studi kasus demonstrasi solusi digital yang dibangun berdasarkan kebutuhan pengguna.',
            'problem' => 'Data dan alur kerja belum terpusat.',
            'solution' => 'Membangun solusi terstruktur dengan kontrol akses dan dokumentasi.',
            'result' => 'Proses menjadi lebih terukur dan mudah dipantau.',
            'technologies' => ['Laravel', 'MySQL', 'Tailwind CSS'],
            'thumbnail' => null,
            'gallery' => [],
            'repository_url' => null,
            'is_published' => true,
            'is_demo' => true,
        ];

        foreach ($items as $item) {
            $data = array_replace($demoDefaults, [
                'title' => $item['title'],
                'category' => $item['category'],
            ], $item['data'] ?? []);

            if ($item['slug'] === self::PUBLISHED_PORTFOLIO_SLUG) {
                Portfolio::query()->updateOrCreate(['slug' => $item['slug']], $data);

                continue;
            }

            Portfolio::query()->firstOrCreate(['slug' => $item['slug']], $data);
        }
    }
}
