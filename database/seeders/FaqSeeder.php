<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['Layanan apa saja yang tersedia?', 'Tersedia pendampingan penelitian, analisis data, editing akademik, dan pengembangan aplikasi.'],
            ['Bagaimana cara memulai konsultasi?', 'Isi formulir kebutuhan lalu admin akan menghubungi Anda untuk verifikasi ruang lingkup.'],
            ['Apakah dapat membantu analisis data?', 'Ya, analisis dilakukan berdasarkan tujuan, metode, dan data yang sah dari pelanggan.'],
            ['Apakah aplikasi dapat disesuaikan?', 'Ya, kebutuhan dianalisis terlebih dahulu dan disepakati melalui ruang lingkup proyek.'],
            ['Bagaimana sistem revisinya?', 'Revisi dicatat per proyek, memiliki prioritas, status, dan riwayat yang transparan.'],
            ['Bagaimana keamanan dokumen pelanggan?', 'Berkas disimpan privat dan akses diperiksa berdasarkan pemilik atau staff yang ditugaskan.'],
            ['Berapa lama waktu pengerjaannya?', 'Durasi bergantung pada ruang lingkup, kelengkapan data, dan jadwal review.'],
            ['Apakah layanan menjaga integritas akademik?', 'Ya. Layanan melarang plagiarisme, fabrikasi data, pemalsuan penelitian, pengerjaan ujian, dan pelanggaran integritas akademik lainnya.'],
        ];
        foreach ($items as $index => [$question, $answer]) {
            Faq::query()->updateOrCreate(['question' => $question], ['answer' => $answer, 'category' => 'umum', 'sort_order' => $index + 1, 'is_active' => true]);
        }
    }
}
