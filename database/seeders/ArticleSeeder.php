<?php

namespace Database\Seeders;

use App\Enums\ArticleCategory;
use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $items = [
            ['Menyusun Rumusan Masalah yang Terarah', 'menyusun-rumusan-masalah', ArticleCategory::Thesis],
            ['Memilih Metode Penelitian Sesuai Tujuan', 'memilih-metode-penelitian', ArticleCategory::Research],
            ['Langkah Awal Membersihkan Data Penelitian', 'membersihkan-data-penelitian', ArticleCategory::DataAnalysis],
            ['Prinsip Dasar Keamanan Aplikasi Web', 'keamanan-aplikasi-web', ArticleCategory::Website],
            ['Merancang Database yang Mudah Dikembangkan', 'merancang-database', ArticleCategory::Database],
        ];
        foreach ($items as [$title, $slug, $category]) {
            Article::query()->updateOrCreate(['slug' => $slug], ['author_id' => $author->id, 'title' => $title, 'excerpt' => 'Panduan praktis untuk membantu proses kerja akademik dan digital menjadi lebih terstruktur.', 'content' => 'Artikel demonstrasi ini menjelaskan prinsip, langkah pemeriksaan, serta hal yang perlu didokumentasikan. Konten dapat diubah melalui dashboard admin pada tahap berikutnya.', 'category' => $category, 'is_published' => true, 'published_at' => now()->subDays(2)]);
        }
    }
}
