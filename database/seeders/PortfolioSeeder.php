<?php

namespace Database\Seeders;

use App\Models\Portfolio;
use Illuminate\Database\Seeder;

class PortfolioSeeder extends Seeder
{
    private const CURATED_PORTFOLIO_SLUGS = [
        'analisis-survei-kepuasan',
        'aplikasi-inventaris-mobile',
    ];

    public function run(): void
    {
        $items = [
            ['title' => 'Dashboard Monitoring Penjualan', 'slug' => 'dashboard-monitoring-penjualan', 'category' => 'Sistem Informasi'],
            ['title' => 'Portal Akademik Terintegrasi', 'slug' => 'portal-akademik-terintegrasi', 'category' => 'Website'],
            [
                'title' => 'Aplikasi Inventaris Mobile',
                'slug' => 'aplikasi-inventaris-mobile',
                'category' => 'Mobile',
                'data' => [
                    'description' => 'Aplikasi inventaris berbasis Flutter untuk mencatat barang, memantau ketersediaan stok, mengelola transaksi barang masuk dan keluar, serta mendeteksi persediaan yang mulai menipis.',
                    'problem' => 'Pencatatan inventaris secara manual membuat jumlah stok sulit dipantau dan meningkatkan risiko keterlambatan dalam mengetahui barang yang hampir habis.',
                    'solution' => 'Membangun aplikasi inventaris mobile yang menyediakan pengelolaan barang, kategori, transaksi stok, riwayat aktivitas, dan peringatan stok menipis dalam satu aplikasi offline.',
                    'result' => 'Data inventaris dapat dicatat dan dipantau melalui perangkat Android sehingga informasi stok lebih terstruktur dan mudah diperiksa.',
                    'technologies' => ['Flutter', 'Dart', 'SQLite', 'Provider', 'Material 3'],
                    'thumbnail' => 'images/portfolios/aplikasi-inventaris-mobile/01-dashboard-inventaris.png',
                    'gallery' => [
                        'images/portfolios/aplikasi-inventaris-mobile/01-dashboard-inventaris.png',
                        'images/portfolios/aplikasi-inventaris-mobile/02-daftar-barang.png',
                        'images/portfolios/aplikasi-inventaris-mobile/03-detail-barang.png',
                        'images/portfolios/aplikasi-inventaris-mobile/04-tambah-barang.png',
                        'images/portfolios/aplikasi-inventaris-mobile/05-barang-masuk.png',
                        'images/portfolios/aplikasi-inventaris-mobile/06-barang-keluar.png',
                        'images/portfolios/aplikasi-inventaris-mobile/07-riwayat-transaksi.png',
                        'images/portfolios/aplikasi-inventaris-mobile/08-stok-menipis.png',
                        'images/portfolios/aplikasi-inventaris-mobile/09-kategori.png',
                    ],
                    'repository_url' => null,
                    'is_published' => true,
                    'is_demo' => false,
                ],
            ],
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

            if (in_array($item['slug'], self::CURATED_PORTFOLIO_SLUGS, true)) {
                Portfolio::query()->updateOrCreate(['slug' => $item['slug']], $data);

                continue;
            }

            Portfolio::query()->firstOrCreate(['slug' => $item['slug']], $data);
        }
    }
}
