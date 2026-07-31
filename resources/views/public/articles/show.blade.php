@extends('layouts.public')
@section('title', $article->title.' | Jokiinlah')
@section('description', $article->excerpt)
@section('ogType', 'article')
@section('content')
@php
    $thumbnailUrl = $article->thumbnailUrl();
@endphp
<article>
<header class='bg-navy py-16 text-white'><div class='container-public max-w-4xl'><x-breadcrumb :items="$breadcrumbs = ['Artikel' => route('articles.index'), $article->title => null]" /><x-badge class='mt-8'>{{ $article->category->label() }}</x-badge><h1 class='mt-5 text-balance text-4xl font-bold leading-tight sm:text-5xl'>{{ $article->title }}</h1><p class='mt-5 text-lg leading-8 text-white/75'>{{ $article->excerpt }}</p><div class='mt-6 flex flex-wrap gap-4 text-sm text-white/65'><span>{{ $article->published_at->setTimezone(config('jokiinlah.display_timezone'))->translatedFormat('d F Y') }}</span><span aria-hidden='true'>•</span><span>{{ $readingMinutes }} menit baca</span>@if($article->author)<span aria-hidden='true'>•</span><span>{{ $article->author->name }}</span>@endif</div></div></header>
@if($thumbnailUrl)<figure class='container-public mt-10 max-w-4xl'><img src='{{ $thumbnailUrl }}' alt='Ilustrasi {{ $article->title }}' width='1200' height='675' class='aspect-[16/9] w-full rounded-[2rem] object-cover shadow-xl'></figure>@endif<div class='section-space'><div class='container-public max-w-4xl'><div class='surface-card safe-content whitespace-pre-line p-7 text-base leading-8 text-charcoal sm:p-12'>{{ $article->content }}</div><div class='mt-10 rounded-2xl bg-navy p-8 text-white'><h2 class='text-3xl font-bold'>Butuh arahan untuk kebutuhan Anda?</h2><p class='mt-3 leading-7 text-white/70'>Diskusikan ruang lingkup penelitian, analisis data, atau solusi digital melalui konsultasi awal.</p><x-primary-button class='mt-6' :href="route('contact.index')">Ajukan Konsultasi</x-primary-button></div></div></div>
</article>
@if($related->isNotEmpty())<section class='section-space bg-white'><div class='container-public'><x-section-heading eyebrow='Artikel Terkait' title='Bacaan berikutnya' /><div class='mt-10 grid gap-6 md:grid-cols-3'>@foreach($related as $relatedArticle)<x-article-card :article='$relatedArticle' />@endforeach</div></div></section>@endif
@php
    $articleSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $article->title,
        'description' => $article->excerpt,
        'datePublished' => $article->published_at->toIso8601String(),
        'dateModified' => $article->updated_at->toIso8601String(),
        'author' => $article->author
            ? ['@type' => 'Person', 'name' => $article->author->name]
            : ['@type' => 'Organization', 'name' => 'Jokiinlah'],
        'publisher' => ['@type' => 'Organization', 'name' => 'Jokiinlah', 'logo' => ['@type' => 'ImageObject', 'url' => asset('images/logo.webp')]],
        'mainEntityOfPage' => route('articles.show', $article),
        'url' => route('articles.show', $article),
    ];
    if ($thumbnailUrl) {
        $articleSchema['image'] = $thumbnailUrl;
    }
@endphp
@push('structured-data')<x-structured-data :data='$articleSchema' />@endpush
@endsection
