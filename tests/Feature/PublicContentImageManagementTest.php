<?php

namespace Tests\Feature;

use App\Enums\ArticleCategory;
use App\Enums\ServiceCategory;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Testimonials\Pages\CreateTestimonial;
use App\Models\Article;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PublicContentImageManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_article_service_and_testimonial_images_are_uploaded_to_managed_public_directories(): void
    {
        Livewire::test(CreateArticle::class)
            ->fillForm($this->articleData([
                'thumbnail' => UploadedFile::fake()->image('article.jpg', 1280, 720),
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(CreateService::class)
            ->fillForm($this->serviceData([
                'image' => UploadedFile::fake()->image('service.png', 1200, 800),
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(CreateTestimonial::class)
            ->fillForm($this->testimonial_data([
                'photo' => UploadedFile::fake()->image('customer.webp', 500, 500),
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $paths = [
            Article::query()->sole()->thumbnail => '#^articles/thumbnails/[0-9a-f-]{36}\.jpg$#',
            Service::query()->sole()->image => '#^services/images/[0-9a-f-]{36}\.png$#',
            Testimonial::query()->sole()->photo => '#^testimonials/photos/[0-9a-f-]{36}\.webp$#',
        ];

        foreach ($paths as $path => $pattern) {
            $this->assertMatchesRegularExpression($pattern, $path);
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_all_public_content_uploads_reject_unsafe_or_oversized_files(): void
    {
        $invalidFiles = [
            UploadedFile::fake()->createWithContent(
                'payload.svg',
                '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            )->mimeType('image/svg+xml'),
            UploadedFile::fake()->image('payload.php')->mimeType('image/jpeg'),
            UploadedFile::fake()->image('oversized.jpg')->size(4097),
        ];

        foreach ($invalidFiles as $index => $file) {
            Livewire::test(CreateArticle::class)
                ->fillForm($this->articleData([
                    'slug' => "unsafe-article-{$index}",
                    'thumbnail' => $file,
                ]))
                ->call('create')
                ->assertHasFormErrors(['thumbnail']);
        }

        $this->assertDatabaseCount('articles', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('articles'));
    }

    public function test_uploaded_images_render_on_public_pages_and_missing_files_are_not_rendered(): void
    {
        foreach ([
            'articles/thumbnails/article.jpg',
            'services/images/service.jpg',
            'testimonials/photos/customer.jpg',
        ] as $path) {
            Storage::disk('public')->put(
                $path,
                UploadedFile::fake()->image(basename($path))->getContent(),
            );
        }

        $article = Article::factory()->create([
            'thumbnail' => 'articles/thumbnails/article.jpg',
        ]);
        $service = Service::factory()->create([
            'image' => 'services/images/service.jpg',
        ]);
        Testimonial::factory()->create([
            'photo' => 'testimonials/photos/customer.jpg',
        ]);
        Testimonial::factory()->create([
            'customer_name' => 'Foto Hilang',
            'photo' => 'testimonials/photos/missing.jpg',
        ]);

        $this->get(route('articles.show', $article))
            ->assertOk()
            ->assertSee('/storage/articles/thumbnails/article.jpg', false);

        $this->get(route('services.show', $service))
            ->assertOk()
            ->assertSee('/storage/services/images/service.jpg', false);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('/storage/testimonials/photos/customer.jpg', false)
            ->assertDontSee('/storage/testimonials/photos/missing.jpg', false);
    }

    public function test_unsafe_stored_paths_never_become_public_urls(): void
    {
        $article = Article::factory()->make(['thumbnail' => '../outside.jpg']);
        $service = Service::factory()->make(['image' => 'https://attacker.example/payload.jpg']);
        $testimonial = Testimonial::factory()->make(['photo' => 'testimonials/photos/payload.php']);

        $this->assertNull($article->thumbnailUrl());
        $this->assertNull($service->imageUrl());
        $this->assertNull($testimonial->photoUrl());
    }

    public function test_existing_legacy_public_images_remain_compatible(): void
    {
        $imageContent = UploadedFile::fake()->image('legacy.jpg')->getContent();
        $originalPublicPath = public_path();
        $fakePublicPath = Storage::disk('public')->path('isolated-public');

        foreach (['articles', 'services', 'testimonials'] as $directory) {
            File::ensureDirectoryExists("{$fakePublicPath}/images/{$directory}");
            File::put("{$fakePublicPath}/images/{$directory}/legacy.jpg", $imageContent);
        }

        app()->usePublicPath($fakePublicPath);

        try {
            $this->assertStringContainsString(
                '/images/articles/legacy.jpg',
                (string) Article::factory()->make(['thumbnail' => 'images/articles/legacy.jpg'])->thumbnailUrl(),
            );
            $this->assertStringContainsString(
                '/images/services/legacy.jpg',
                (string) Service::factory()->make(['image' => 'images/services/legacy.jpg'])->imageUrl(),
            );
            $this->assertStringContainsString(
                '/images/testimonials/legacy.jpg',
                (string) Testimonial::factory()->make(['photo' => 'images/testimonials/legacy.jpg'])->photoUrl(),
            );
        } finally {
            app()->usePublicPath($originalPublicPath);
        }
    }

    private function articleData(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Artikel Upload Gambar',
            'slug' => 'artikel-upload-gambar',
            'excerpt' => 'Ringkasan artikel untuk pengujian upload.',
            'content' => 'Konten artikel untuk pengujian upload gambar publik.',
            'category' => ArticleCategory::Website->value,
            'thumbnail' => null,
            'is_published' => true,
            'published_at' => now(),
        ], $overrides);
    }

    private function serviceData(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Layanan Upload Gambar',
            'slug' => 'layanan-upload-gambar',
            'category' => ServiceCategory::Web->value,
            'short_description' => 'Ringkasan layanan untuk pengujian upload.',
            'description' => 'Deskripsi layanan untuk pengujian upload gambar publik.',
            'features' => ['Aman', 'Teruji'],
            'technologies' => ['Laravel'],
            'icon' => 'briefcase',
            'image' => null,
            'is_active' => true,
            'sort_order' => 1,
        ], $overrides);
    }

    private function testimonial_data(array $overrides = []): array
    {
        return array_replace([
            'customer_name' => 'Pelanggan Upload',
            'customer_role' => 'Product Owner',
            'content' => 'Pengujian upload foto testimoni.',
            'rating' => 5,
            'photo' => null,
            'is_published' => true,
            'is_demo' => false,
        ], $overrides);
    }
}
