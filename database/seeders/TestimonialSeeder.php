<?php

namespace Database\Seeders;

use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['Nadia A.', 'Mahasiswa', 'Komunikasi jelas dan setiap tahapan dijelaskan dengan baik.', 5],
            ['Rizky P.', 'Peneliti', 'Pendampingan analisis data membantu saya memahami interpretasi hasil.', 5],
            ['Maya K.', 'Pemilik Usaha', 'Dashboard yang dirancang memudahkan pemantauan operasional.', 5],
            ['Fajar D.', 'Mahasiswa', 'Formatting dokumen rapi dan revisi ditangani secara terstruktur.', 4],
            ['Sinta R.', 'Product Owner', 'Dokumentasi aplikasi mudah dipahami oleh tim internal.', 5],
        ];
        foreach ($items as [$name, $role, $content, $rating]) {
            Testimonial::query()->updateOrCreate(['customer_name' => $name], ['customer_role' => $role, 'content' => $content, 'rating' => $rating, 'is_published' => true, 'is_demo' => true]);
        }
    }
}
