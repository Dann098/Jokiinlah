<?php

namespace Tests\Feature;

use App\Filament\Resources\Portfolios\Pages\CreatePortfolio;
use App\Filament\Resources\Portfolios\Pages\EditPortfolio;
use App\Models\Portfolio;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PortfolioImageManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_can_upload_supported_thumbnail_formats_to_public_storage(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        foreach (['jpg', 'png', 'webp'] as $extension) {
            Livewire::test(CreatePortfolio::class)
                ->fillForm($this->validFormData([
                    'slug' => "portfolio-{$extension}",
                    'thumbnail' => UploadedFile::fake()->image("thumbnail.{$extension}", 1280, 720),
                ]))
                ->call('create')
                ->assertHasNoFormErrors();

            $portfolio = Portfolio::query()->where('slug', "portfolio-{$extension}")->firstOrFail();

            $this->assertMatchesRegularExpression(
                '/^portfolios\/thumbnails\/[0-9a-f-]{36}\.'.preg_quote($extension, '/').'$/',
                (string) $portfolio->thumbnail,
            );
            $this->assertFalse(str_contains((string) $portfolio->thumbnail, 'thumbnail.'));
            $this->assertFalse(str_starts_with((string) $portfolio->thumbnail, storage_path()));
            Storage::disk('public')->assertExists($portfolio->thumbnail);
        }
    }

    public function test_unsafe_or_oversized_thumbnail_uploads_are_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $invalidFiles = [
            'svg' => UploadedFile::fake()->createWithContent(
                'payload.svg',
                '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            )->mimeType('image/svg+xml'),
            'non_image' => UploadedFile::fake()->create('payload.txt', 10, 'text/plain'),
            'oversized' => UploadedFile::fake()->image('large.jpg')->size(4097),
            'dangerous_extension' => UploadedFile::fake()->image('payload.php')->mimeType('image/jpeg'),
        ];

        foreach ($invalidFiles as $case => $file) {
            Livewire::test(CreatePortfolio::class)
                ->fillForm($this->validFormData([
                    'slug' => "invalid-{$case}",
                    'thumbnail' => $file,
                ]))
                ->call('create')
                ->assertHasFormErrors(['thumbnail']);
        }

        $this->assertDatabaseCount('portfolios', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('portfolios'));
    }

    public function test_gallery_upload_preserves_order_and_uses_relative_random_paths(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreatePortfolio::class)
            ->fillForm($this->validFormData([
                'gallery' => [
                    UploadedFile::fake()->image('first.jpg'),
                    UploadedFile::fake()->image('second.png'),
                    UploadedFile::fake()->image('third.webp'),
                ],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $gallery = Portfolio::query()->sole()->gallery;

        $this->assertCount(3, $gallery);
        $this->assertMatchesRegularExpression('/^portfolios\/gallery\/[0-9a-f-]{36}\.jpg$/', $gallery[0]);
        $this->assertMatchesRegularExpression('/^portfolios\/gallery\/[0-9a-f-]{36}\.png$/', $gallery[1]);
        $this->assertMatchesRegularExpression('/^portfolios\/gallery\/[0-9a-f-]{36}\.webp$/', $gallery[2]);
        $this->assertSame($gallery, array_values($gallery));

        foreach ($gallery as $path) {
            Storage::disk('public')->assertExists($path);
            $this->assertFalse(str_starts_with($path, storage_path()));
        }
    }

    public function test_gallery_is_limited_to_eight_images(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreatePortfolio::class)
            ->fillForm($this->validFormData([
                'gallery' => array_map(
                    fn (int $index): UploadedFile => UploadedFile::fake()->image("gallery-{$index}.jpg"),
                    range(1, 9),
                ),
            ]))
            ->call('create')
            ->assertHasFormErrors(['gallery']);

        $this->assertDatabaseCount('portfolios', 0);
    }

    public function test_gallery_rejects_svg_non_images_and_dangerous_extensions(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $invalidFiles = [
            UploadedFile::fake()->createWithContent(
                'payload.svg',
                '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            )->mimeType('image/svg+xml'),
            UploadedFile::fake()->create('payload.txt', 10, 'text/plain'),
            UploadedFile::fake()->image('payload.php')->mimeType('image/jpeg'),
        ];

        foreach ($invalidFiles as $index => $invalidFile) {
            Livewire::test(CreatePortfolio::class)
                ->fillForm($this->validFormData([
                    'slug' => "invalid-gallery-{$index}",
                    'gallery' => [$invalidFile],
                ]))
                ->call('create')
                ->assertHasFormErrors(['gallery']);
        }

        $this->assertDatabaseCount('portfolios', 0);
    }

    public function test_public_pages_render_storage_urls_and_filter_missing_gallery_images(): void
    {
        Storage::disk('public')->put(
            'portfolios/thumbnails/card.jpg',
            UploadedFile::fake()->image('card.jpg')->getContent(),
        );
        Storage::disk('public')->put(
            'portfolios/gallery/present.jpg',
            UploadedFile::fake()->image('present.jpg')->getContent(),
        );

        $portfolio = Portfolio::factory()->create([
            'thumbnail' => 'portfolios/thumbnails/card.jpg',
            'gallery' => [
                'portfolios/gallery/present.jpg',
                'portfolios/gallery/missing.jpg',
            ],
        ]);

        $this->get(route('portfolios.index'))
            ->assertOk()
            ->assertSee('/storage/portfolios/thumbnails/card.jpg', false);

        $this->get(route('portfolios.show', $portfolio))
            ->assertOk()
            ->assertSee('/storage/portfolios/thumbnails/card.jpg', false)
            ->assertSee('/storage/portfolios/gallery/present.jpg', false)
            ->assertDontSee('/storage/portfolios/gallery/missing.jpg', false);
    }

    public function test_public_storage_images_use_the_current_request_origin(): void
    {
        Storage::disk('public')->put(
            'portfolios/thumbnails/card.jpg',
            UploadedFile::fake()->image('card.jpg')->getContent(),
        );
        $fakeRoot = Storage::disk('public')->path('');

        config()->set([
            'filesystems.disks.public.root' => $fakeRoot,
            'filesystems.disks.public.url' => 'http://localhost:8000/storage',
        ]);
        Storage::forgetDisk('public');

        Portfolio::factory()->create([
            'thumbnail' => 'portfolios/thumbnails/card.jpg',
        ]);

        $this->withHeader('Host', '127.0.0.1:8000')
            ->get('/portofolio')
            ->assertOk()
            ->assertSee('http://127.0.0.1:8000/storage/portfolios/thumbnails/card.jpg', false)
            ->assertDontSee('http://localhost:8000/storage/portfolios/thumbnails/card.jpg', false);
    }

    public function test_missing_or_unsafe_images_use_fallback_and_never_render_broken_paths(): void
    {
        Storage::disk('public')->put('portfolios/thumbnails/payload.php', '<?php echo "unsafe";');
        Storage::disk('public')->put('portfolios/gallery/payload.svg', '<svg></svg>');

        $portfolio = Portfolio::factory()->create([
            'thumbnail' => 'portfolios/thumbnails/payload.php',
            'gallery' => [
                '../outside.jpg',
                'portfolios/gallery/missing.jpg',
                'portfolios/gallery/payload.svg',
            ],
        ]);

        $this->assertStringContainsString('/images/logo.webp', $portfolio->thumbnailUrl());
        $this->assertSame([], $portfolio->galleryUrls());

        $this->get(route('portfolios.show', $portfolio))
            ->assertOk()
            ->assertSee('/images/logo.webp', false)
            ->assertDontSee('../outside.jpg', false)
            ->assertDontSee('portfolios/gallery/missing.jpg', false)
            ->assertDontSee('payload.php', false)
            ->assertDontSee('payload.svg', false);
    }

    public function test_legacy_public_image_and_storage_prefix_are_resolved_safely(): void
    {
        $imageContent = UploadedFile::fake()->image('prefixed.jpg')->getContent();
        Storage::disk('public')->put('portfolios/thumbnails/prefixed.jpg', $imageContent);

        $originalPublicPath = public_path();
        $fakePublicPath = Storage::disk('public')->path('isolated-public');
        File::ensureDirectoryExists($fakePublicPath.'/images/portfolios');
        File::put($fakePublicPath.'/images/portfolios/legacy.jpg', $imageContent);
        app()->usePublicPath($fakePublicPath);

        try {
            $legacy = Portfolio::factory()->make(['thumbnail' => 'images/portfolios/legacy.jpg']);
            $prefixed = Portfolio::factory()->make(['thumbnail' => 'storage/portfolios/thumbnails/prefixed.jpg']);

            $this->assertStringContainsString('/images/portfolios/legacy.jpg', $legacy->thumbnailUrl());
            $this->assertStringContainsString('/storage/portfolios/thumbnails/prefixed.jpg', $prefixed->thumbnailUrl());
        } finally {
            app()->usePublicPath($originalPublicPath);
        }
    }

    public function test_saving_an_edit_without_changing_legacy_images_preserves_values(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $portfolio = Portfolio::factory()->create([
            'thumbnail' => 'images/portfolios/legacy.jpg',
            'gallery' => ['storage/portfolios/gallery/legacy.jpg'],
        ]);

        Livewire::test(EditPortfolio::class, ['record' => $portfolio->getKey()])
            ->fillForm(['title' => 'Judul diperbarui'])
            ->call('save')
            ->assertHasNoFormErrors();

        $portfolio->refresh();

        $this->assertSame('images/portfolios/legacy.jpg', $portfolio->thumbnail);
        $this->assertSame(['storage/portfolios/gallery/legacy.jpg'], $portfolio->gallery);
    }

    public function test_tampered_existing_file_path_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $portfolio = Portfolio::factory()->create(['thumbnail' => null]);

        Livewire::test(EditPortfolio::class, ['record' => $portfolio->getKey()])
            ->fillForm(['thumbnail' => ['../outside.jpg']])
            ->call('save')
            ->assertHasFormErrors(['thumbnail']);

        $this->assertNull($portfolio->refresh()->thumbnail);
    }

    public function test_non_admin_cannot_open_portfolio_mutation_pages(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs(User::factory()->staff()->create())
            ->get('/admin/portfolios/create')
            ->assertForbidden();

        $this->get("/admin/portfolios/{$portfolio->getKey()}/edit")
            ->assertForbidden();
    }

    private function validFormData(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Portfolio Upload Aman',
            'slug' => 'portfolio-upload-aman',
            'category' => 'Website',
            'description' => 'Deskripsi portfolio untuk pengujian upload gambar.',
            'problem' => 'Permasalahan.',
            'solution' => 'Solusi.',
            'result' => 'Hasil.',
            'technologies' => ['Laravel', 'Filament'],
            'thumbnail' => null,
            'gallery' => [],
            'is_published' => true,
            'is_demo' => false,
        ], $overrides);
    }
}
