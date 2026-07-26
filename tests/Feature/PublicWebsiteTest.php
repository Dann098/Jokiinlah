<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Faq;
use App\Models\Portfolio;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_render_with_seo_and_one_primary_heading(): void
    {
        Service::factory()->create(['name' => 'Analisis Data Profesional']);
        Portfolio::factory()->create(['title' => 'Dashboard Riset Demo']);
        Article::factory()->create(['title' => 'Panduan Analisis Data']);
        Faq::factory()->create(['question' => 'Bagaimana memulai konsultasi?']);

        foreach (['/', '/layanan', '/portofolio', '/artikel', '/faq', '/kontak', '/kebijakan-privasi', '/syarat-dan-ketentuan'] as $uri) {
            $response = $this->get($uri)->assertOk()->assertSee('<meta name=\'description\'', false);
            $this->assertSame(1, substr_count($response->getContent(), '<h1'), 'Halaman '.$uri.' harus memiliki tepat satu h1.');
        }
    }

    public function test_only_active_services_are_publicly_available(): void
    {
        $active = Service::factory()->create(['name' => 'Layanan Aktif', 'is_active' => true]);
        $inactive = Service::factory()->create(['name' => 'Layanan Nonaktif', 'is_active' => false]);

        $this->get(route('services.index'))->assertOk()->assertSee($active->name)->assertDontSee($inactive->name);
        $this->get(route('services.show', $active))->assertOk();
        $this->get(route('services.show', $inactive))->assertNotFound();
    }

    public function test_only_published_portfolios_are_publicly_available(): void
    {
        $published = Portfolio::factory()->create(['title' => 'Studi Kasus Publik', 'is_published' => true]);
        $draft = Portfolio::factory()->create(['title' => 'Studi Kasus Draft', 'is_published' => false]);

        $this->get(route('portfolios.index'))->assertSee($published->title)->assertDontSee($draft->title);
        $this->get(route('portfolios.show', $published))->assertOk()->assertSee('Permasalahan')->assertSee('Solusi')->assertSee('Hasil');
        $this->get(route('portfolios.show', $draft))->assertNotFound();
    }

    public function test_draft_and_future_articles_are_not_public(): void
    {
        $published = Article::factory()->create(['title' => 'Artikel Terbit', 'is_published' => true, 'published_at' => now()->subMinute()]);
        $draft = Article::factory()->create(['title' => 'Artikel Draft', 'is_published' => false]);
        $future = Article::factory()->create(['title' => 'Artikel Masa Depan', 'is_published' => true, 'published_at' => now()->addDay()]);

        $this->get(route('articles.index'))->assertSee($published->title)->assertDontSee($draft->title)->assertDontSee($future->title);
        $this->get(route('articles.show', $draft))->assertNotFound();
        $this->get(route('articles.show', $future))->assertNotFound();
    }

    public function test_only_active_faqs_are_shown(): void
    {
        Faq::factory()->create(['question' => 'Pertanyaan Aktif?', 'is_active' => true]);
        Faq::factory()->create(['question' => 'Pertanyaan Nonaktif?', 'is_active' => false]);

        $this->get(route('faq.index'))->assertOk()->assertSee('Pertanyaan Aktif?')->assertDontSee('Pertanyaan Nonaktif?');
    }

    public function test_public_search_and_category_filters_return_only_matching_content(): void
    {
        $matchingService = Service::factory()->create(['name' => 'Analisis Statistik Khusus', 'category' => 'data_analysis']);
        Service::factory()->create(['name' => 'Website Lain', 'category' => 'web']);
        $this->get(route('services.index', ['q' => 'Statistik', 'category' => 'data_analysis']))
            ->assertOk()->assertSee($matchingService->name)->assertDontSee('Website Lain');

        $matchingPortfolio = Portfolio::factory()->create(['title' => 'Dashboard Statistik', 'category' => 'Analisis Data']);
        Portfolio::factory()->create(['title' => 'Portal Lain', 'category' => 'Website']);
        $this->get(route('portfolios.index', ['q' => 'Statistik', 'category' => 'Analisis Data']))
            ->assertOk()->assertSee($matchingPortfolio->title)->assertDontSee('Portal Lain');

        $matchingArticle = Article::factory()->create(['title' => 'Statistik Penelitian', 'category' => 'data_analysis']);
        Article::factory()->create(['title' => 'Karier Lain', 'category' => 'it_career']);
        $this->get(route('articles.index', ['q' => 'Statistik', 'category' => 'data_analysis']))
            ->assertOk()->assertSee($matchingArticle->title)->assertDontSee('Karier Lain');
    }

    public function test_service_pagination_preserves_search_query(): void
    {
        foreach (range(1, 10) as $index) {
            Service::query()->create([
                'name' => 'Layanan Pagination '.$index,
                'slug' => 'layanan-pagination-'.$index,
                'category' => 'academic',
                'short_description' => 'Deskripsi layanan pagination.',
                'description' => 'Deskripsi lengkap layanan pagination.',
                'is_active' => true,
                'sort_order' => $index,
            ]);
        }

        $firstPage = $this->get(route('services.index', ['q' => 'Pagination']))->assertOk();
        $firstPage->assertSee('page=2', false)->assertSee('q=Pagination', false);

        $this->get(route('services.index', ['q' => 'Pagination', 'page' => 2]))
            ->assertOk()
            ->assertSee('Layanan Pagination 10');
    }

    public function test_faqs_are_ordered_and_accordion_markup_is_accessible(): void
    {
        $second = Faq::factory()->create(['question' => 'Pertanyaan kedua?', 'category' => 'Umum', 'sort_order' => 20]);
        $first = Faq::factory()->create(['question' => 'Pertanyaan pertama?', 'category' => 'Umum', 'sort_order' => 10]);

        $content = $this->get(route('faq.index'))->assertOk()->getContent();

        $this->assertLessThan(strpos($content, $second->question), strpos($content, $first->question));
        $this->assertStringContainsString("id='faq-button-{$first->id}'", $content);
        $this->assertStringContainsString("aria-controls='faq-panel-{$first->id}'", $content);
        $this->assertStringContainsString("role='region'", $content);
    }

    public function test_demo_testimonials_are_labeled_locally_and_hidden_in_production(): void
    {
        $demo = Testimonial::factory()->create(['customer_name' => 'Pelanggan Demo', 'is_published' => true, 'is_demo' => true]);

        $this->get(route('home'))->assertOk()->assertSee($demo->customer_name)->assertSee('Data Demo');

        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn (): string => 'production');

        try {
            $this->get(route('home'))->assertOk()->assertDontSee($demo->customer_name)->assertDontSee('Pengalaman menggunakan proses pendampingan');
        } finally {
            app()->detectEnvironment(fn (): string => $originalEnvironment);
        }
    }

    public function test_search_input_is_escaped(): void
    {
        $payload = '<script>alert(1)</script>';

        $this->get(route('services.index', ['q' => $payload]))
            ->assertOk()
            ->assertDontSee($payload, false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_article_content_is_escaped_instead_of_rendered_as_html(): void
    {
        $article = Article::factory()->create(['content' => '<script>alert(1)</script><strong>aman</strong>']);

        $this->get(route('articles.show', $article))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_sitemap_contains_only_public_routes_and_content(): void
    {
        $service = Service::factory()->create(['is_active' => true]);
        $inactive = Service::factory()->create(['is_active' => false]);
        $article = Article::factory()->create();
        $futureArticle = Article::factory()->create(['is_published' => true, 'published_at' => now()->addDay()]);
        $draftPortfolio = Portfolio::factory()->create(['is_published' => false]);

        $response = $this->get(route('sitemap'))->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertNotFalse(simplexml_load_string($response->getContent()));
        $response->assertSee(route('services.show', $service), false)
            ->assertSee(route('articles.show', $article), false)
            ->assertDontSee(route('services.show', $inactive), false)
            ->assertDontSee(route('articles.show', $futureArticle), false)
            ->assertDontSee(route('portfolios.show', $draftPortfolio), false)
            ->assertDontSee('/admin', false)
            ->assertDontSee('/project-files', false);
    }

    public function test_public_forms_and_navigation_have_accessible_controls(): void
    {
        Service::factory()->create();
        $content = $this->get(route('contact.index'))->assertOk()->getContent();

        $this->assertStringContainsString('for=\'name\'', $content);
        $this->assertStringContainsString('aria-invalid=', $content);
        $this->assertStringContainsString('aria-controls=\'mobile-navigation\'', $content);
        $this->assertStringContainsString('Lewati ke konten utama', $content);
    }

    public function test_public_navigation_seo_legal_copy_and_structured_data_are_consistent(): void
    {
        $service = Service::factory()->create();
        $author = User::factory()->admin()->create();
        $article = Article::factory()->for($author, 'author')->create();

        $servicesPage = $this->get(route('services.index', ['page' => 2]))->assertOk();
        $servicesPage->assertSee("aria-current='page'", false)
            ->assertSee('<link rel=\'canonical\' href=\''.route('services.index', ['page' => 2]).'\'>', false);

        $this->get(route('terms'))->assertOk()
            ->assertSee('Perubahan dan pembatalan')
            ->assertSee('Batas tanggung jawab')
            ->assertSee((string) config('jokiinlah.terms_version'));
        $this->get(route('privacy'))->assertOk()->assertSee((string) config('jokiinlah.privacy_policy_version'));

        $articleContent = $this->get(route('articles.show', $article))->assertOk()->getContent();
        preg_match_all("#<script type='application/ld\\+json'>(.*?)</script>#s", $articleContent, $matches);
        $this->assertNotEmpty($matches[1]);
        foreach ($matches[1] as $json) {
            $this->assertIsArray(json_decode($json, true, flags: JSON_THROW_ON_ERROR));
        }

        $this->get(route('services.show', $service))->assertOk()
            ->assertSee('"@type":"Service"', false);

        $this->get('/layanan/konten-tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman Tidak Ditemukan')
            ->assertSee("<meta name='robots' content='noindex,nofollow'>", false);
    }
}
