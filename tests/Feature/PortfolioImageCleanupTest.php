<?php

namespace Tests\Feature;

use App\Models\Portfolio;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class PortfolioImageCleanupTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_replacing_thumbnail_deletes_only_the_old_managed_file_after_commit(): void
    {
        Storage::disk('public')->put('portfolios/thumbnails/old.jpg', 'old');
        Storage::disk('public')->put('portfolios/thumbnails/other.jpg', 'other');

        $portfolio = Portfolio::factory()->create(['thumbnail' => 'portfolios/thumbnails/old.jpg']);
        Portfolio::factory()->create(['thumbnail' => 'portfolios/thumbnails/other.jpg']);

        $portfolio->update(['thumbnail' => 'portfolios/thumbnails/new.jpg']);

        Storage::disk('public')->assertMissing('portfolios/thumbnails/old.jpg');
        Storage::disk('public')->assertExists('portfolios/thumbnails/other.jpg');
    }

    public function test_rolled_back_thumbnail_change_does_not_delete_the_old_file(): void
    {
        Storage::disk('public')->put('portfolios/thumbnails/old.jpg', 'old');
        $portfolio = Portfolio::factory()->create(['thumbnail' => 'portfolios/thumbnails/old.jpg']);

        try {
            DB::transaction(function () use ($portfolio): void {
                $portfolio->update(['thumbnail' => 'portfolios/thumbnails/new.jpg']);

                throw new RuntimeException('Force rollback.');
            });
        } catch (RuntimeException) {
            // The rollback is the behavior under test.
        }

        Storage::disk('public')->assertExists('portfolios/thumbnails/old.jpg');
        $this->assertSame(
            'portfolios/thumbnails/old.jpg',
            $portfolio->fresh()->thumbnail,
        );
    }

    public function test_removing_gallery_image_deletes_only_unreferenced_managed_file(): void
    {
        foreach (['removed.jpg', 'kept.jpg', 'shared.jpg'] as $file) {
            Storage::disk('public')->put("portfolios/gallery/{$file}", $file);
        }

        $portfolio = Portfolio::factory()->create([
            'gallery' => [
                'portfolios/gallery/removed.jpg',
                'portfolios/gallery/kept.jpg',
                'portfolios/gallery/shared.jpg',
            ],
        ]);
        Portfolio::factory()->create(['gallery' => ['storage/portfolios/gallery/shared.jpg']]);

        $portfolio->update([
            'gallery' => [
                'portfolios/gallery/kept.jpg',
                'portfolios/gallery/shared.jpg',
            ],
        ]);

        Storage::disk('public')->assertMissing('portfolios/gallery/removed.jpg');
        Storage::disk('public')->assertExists('portfolios/gallery/kept.jpg');
        Storage::disk('public')->assertExists('portfolios/gallery/shared.jpg');
    }

    public function test_cleanup_preserves_path_still_used_by_another_field_on_the_same_record(): void
    {
        Storage::disk('public')->put('portfolios/gallery/shared.jpg', 'shared');

        $thumbnailChanged = Portfolio::factory()->create([
            'thumbnail' => 'portfolios/gallery/shared.jpg',
            'gallery' => ['portfolios/gallery/shared.jpg'],
        ]);
        $thumbnailChanged->update(['thumbnail' => null]);

        Storage::disk('public')->assertExists('portfolios/gallery/shared.jpg');

        $galleryChanged = Portfolio::factory()->create([
            'thumbnail' => 'portfolios/gallery/shared.jpg',
            'gallery' => ['portfolios/gallery/shared.jpg'],
        ]);
        $galleryChanged->update(['gallery' => []]);

        Storage::disk('public')->assertExists('portfolios/gallery/shared.jpg');
    }

    public function test_deleting_portfolio_cleans_managed_images_but_not_legacy_or_shared_files(): void
    {
        foreach ([
            'portfolios/thumbnails/owned.jpg',
            'portfolios/gallery/owned.jpg',
            'portfolios/gallery/shared.jpg',
            'images/portfolios/legacy.jpg',
        ] as $path) {
            Storage::disk('public')->put($path, $path);
        }

        $portfolio = Portfolio::factory()->create([
            'thumbnail' => 'portfolios/thumbnails/owned.jpg',
            'gallery' => [
                'portfolios/gallery/owned.jpg',
                'portfolios/gallery/shared.jpg',
                'images/portfolios/legacy.jpg',
            ],
        ]);
        Portfolio::factory()->create(['gallery' => ['portfolios/gallery/shared.jpg']]);

        $portfolio->delete();

        Storage::disk('public')->assertMissing('portfolios/thumbnails/owned.jpg');
        Storage::disk('public')->assertMissing('portfolios/gallery/owned.jpg');
        Storage::disk('public')->assertExists('portfolios/gallery/shared.jpg');
        Storage::disk('public')->assertExists('images/portfolios/legacy.jpg');
    }
}
