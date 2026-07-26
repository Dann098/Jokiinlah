<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Portfolio;
use App\Models\Service;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        return response()->view('public.sitemap', [
            'services' => Service::query()->active()->get(['slug', 'updated_at']),
            'portfolios' => Portfolio::query()->published()->get(['slug', 'updated_at']),
            'articles' => Article::query()->published()->get(['slug', 'updated_at']),
        ])->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $content = app()->environment('production')
            ? 'User-agent: *'.PHP_EOL.'Allow: /'.PHP_EOL.'Sitemap: '.route('sitemap')
            : 'User-agent: *'.PHP_EOL.'Disallow: /';

        return response($content, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
