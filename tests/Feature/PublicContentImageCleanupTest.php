<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Service;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class PublicContentImageCleanupTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_replacing_images_cleans_the_previous_managed_files_after_commit(): void
    {
        $records = [
            Article::factory()->create(['thumbnail' => 'articles/thumbnails/old.jpg']),
            Service::factory()->create(['image' => 'services/images/old.jpg']),
            Testimonial::factory()->create(['photo' => 'testimonials/photos/old.jpg']),
        ];

        $updates = [
            ['thumbnail', 'articles/thumbnails/old.jpg', 'articles/thumbnails/new.jpg'],
            ['image', 'services/images/old.jpg', 'services/images/new.jpg'],
            ['photo', 'testimonials/photos/old.jpg', 'testimonials/photos/new.jpg'],
        ];

        foreach ($updates as [$attribute, $oldPath, $newPath]) {
            Storage::disk('public')->put($oldPath, 'old');
            Storage::disk('public')->put($newPath, 'new');

            $record = array_shift($records);
            $record->update([$attribute => $newPath]);

            Storage::disk('public')->assertMissing($oldPath);
            Storage::disk('public')->assertExists($newPath);
        }
    }

    public function test_rollback_preserves_the_previous_public_image(): void
    {
        $oldPath = 'services/images/old.jpg';
        Storage::disk('public')->put($oldPath, 'old');
        $service = Service::factory()->create(['image' => $oldPath]);

        try {
            DB::transaction(function () use ($service): void {
                $service->update(['image' => 'services/images/new.jpg']);

                throw new RuntimeException('Force rollback.');
            });
        } catch (RuntimeException) {
            // The rollback is the behavior under test.
        }

        Storage::disk('public')->assertExists($oldPath);
        $this->assertSame($oldPath, $service->fresh()->image);
    }

    public function test_deleting_records_cleans_managed_images_but_preserves_legacy_assets(): void
    {
        $managedArticle = 'articles/thumbnails/owned.jpg';
        $managedTestimonial = 'testimonials/photos/owned.jpg';
        $legacyService = 'images/services/legacy.jpg';

        foreach ([$managedArticle, $managedTestimonial, $legacyService] as $path) {
            Storage::disk('public')->put($path, $path);
        }

        $article = Article::factory()->create(['thumbnail' => $managedArticle]);
        $testimonial = Testimonial::factory()->create(['photo' => $managedTestimonial]);
        $service = Service::factory()->create(['image' => $legacyService]);

        $article->delete();
        $testimonial->delete();
        $service->delete();

        Storage::disk('public')->assertMissing($managedArticle);
        Storage::disk('public')->assertMissing($managedTestimonial);
        Storage::disk('public')->assertExists($legacyService);
    }

    public function test_cleanup_preserves_a_managed_path_referenced_by_another_resource(): void
    {
        $sharedPath = 'articles/thumbnails/shared.jpg';
        Storage::disk('public')->put($sharedPath, 'shared');

        $article = Article::factory()->create(['thumbnail' => $sharedPath]);
        Service::factory()->create(['image' => $sharedPath]);

        $article->delete();

        Storage::disk('public')->assertExists($sharedPath);
    }
}
