@props(['title', 'description', 'canonical' => null, 'image' => null, 'type' => 'website', 'noindex' => false])
@php
    $canonicalUrl = $canonical ?: request()->url();
    $page = request()->integer('page', 1);
    if (! $canonical && $page > 1) {
        $canonicalUrl .= '?'.http_build_query(['page' => $page]);
    }
    $ogImage = $image ?: asset('images/og-default.webp');
    $shouldIndex = app()->environment('production') && ! $noindex && ! request()->filled('q');
@endphp
<title>{{ $title }}</title>
<meta name='description' content='{{ $description }}'>
<link rel='canonical' href='{{ $canonicalUrl }}'>
<meta property='og:locale' content='id_ID'>
<meta property='og:type' content='{{ $type }}'>
<meta property='og:site_name' content='Jokiinlah'>
<meta property='og:title' content='{{ $title }}'>
<meta property='og:description' content='{{ $description }}'>
<meta property='og:url' content='{{ $canonicalUrl }}'>
<meta property='og:image' content='{{ $ogImage }}'>
<meta name='twitter:card' content='summary_large_image'>
<meta name='twitter:title' content='{{ $title }}'>
<meta name='twitter:description' content='{{ $description }}'>
<meta name='twitter:image' content='{{ $ogImage }}'>
@unless($shouldIndex)
    <meta name='robots' content='noindex,nofollow'>
@endunless
