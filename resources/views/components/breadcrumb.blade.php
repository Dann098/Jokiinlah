@props(['items' => [], 'theme' => 'dark'])
@php
    $breadcrumbItems = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Beranda', 'item' => route('home')]];
    $position = 2;
    foreach ($items as $label => $url) {
        $breadcrumbItems[] = array_filter(['@type' => 'ListItem', 'position' => $position++, 'name' => $label, 'item' => $url]);
    }
    $breadcrumbSchema = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $breadcrumbItems];
@endphp
<nav aria-label='Breadcrumb' class='text-sm {{ $theme === 'dark' ? 'text-white/75' : 'text-muted' }}'><ol class='flex flex-wrap items-center gap-2'><li><a class='font-semibold {{ $theme === 'dark' ? 'hover:text-gold' : 'hover:text-navy' }}' href='{{ route('home') }}'>Beranda</a></li>@foreach($items as $label => $url)<li aria-hidden='true'>/</li><li>@if($url)<a class='font-semibold {{ $theme === 'dark' ? 'hover:text-gold' : 'hover:text-navy' }}' href='{{ $url }}'>{{ $label }}</a>@else<span aria-current='page'>{{ $label }}</span>@endif</li>@endforeach</ol></nav>
<x-structured-data :data='$breadcrumbSchema' />
