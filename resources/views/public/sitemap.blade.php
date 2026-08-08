{!! '<?xml version='.chr(34).'1.0'.chr(34).' encoding='.chr(34).'UTF-8'.chr(34).'?>' !!}
<urlset xmlns='http://www.sitemaps.org/schemas/sitemap/0.9'>
@foreach([route('home'), route('services.index'), route('free-tools.index'), route('free-tools.cv-builder'), route('free-tools.data-cleaner'), route('free-tools.csv-excel-converter'), route('free-tools.word-to-pdf'), route('portfolios.index'), route('articles.index'), route('faq.index'), route('contact.index'), route('privacy'), route('terms')] as $url)
    <url><loc>{{ $url }}</loc></url>
@endforeach
@foreach($services as $service)
    <url><loc>{{ route('services.show', $service) }}</loc><lastmod>{{ $service->updated_at->toAtomString() }}</lastmod></url>
@endforeach
@foreach($portfolios as $portfolio)
    <url><loc>{{ route('portfolios.show', $portfolio) }}</loc><lastmod>{{ $portfolio->updated_at->toAtomString() }}</lastmod></url>
@endforeach
@foreach($articles as $article)
    <url><loc>{{ route('articles.show', $article) }}</loc><lastmod>{{ $article->updated_at->toAtomString() }}</lastmod></url>
@endforeach
</urlset>
