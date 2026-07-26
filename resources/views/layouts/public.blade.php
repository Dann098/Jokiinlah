<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <meta name='csrf-token' content='{{ csrf_token() }}'>
    @php
        $seoTitle = trim($__env->yieldContent('title')) ?: 'Jokiinlah';
        $seoDescription = trim($__env->yieldContent('description')) ?: 'Pendampingan akademik dan pengembangan solusi digital yang profesional.';
        $seoType = trim($__env->yieldContent('ogType')) ?: 'website';
        $seoNoindex = trim($__env->yieldContent('robots')) === 'noindex,nofollow';
    @endphp
    <x-seo-meta :title='$seoTitle' :description='$seoDescription' :type='$seoType' :noindex='$seoNoindex' />
    <link rel='icon' type='image/png' href='{{ asset('images/favicon.png') }}'>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
    <a href='#main-content' class='fixed left-3 top-3 z-[100] -translate-y-24 rounded-lg bg-white px-4 py-3 font-bold text-navy shadow-lg transition focus:translate-y-0'>Lewati ke konten utama</a>
    <x-navbar />
    <x-flash-alert />
    <main id='main-content' tabindex='-1'>@yield('content')</main>
    <x-footer />
    @php($organizationData = ['@context' => 'https://schema.org', '@graph' => [['@type' => 'Organization', '@id' => url('/').'#organization', 'name' => 'Jokiinlah', 'url' => url('/'), 'logo' => asset('images/logo.webp'), 'description' => 'Pendampingan Akademik & Digital'], ['@type' => 'WebSite', '@id' => url('/').'#website', 'name' => 'Jokiinlah', 'url' => url('/'), 'inLanguage' => 'id-ID', 'publisher' => ['@id' => url('/').'#organization']]]])
    <x-structured-data :data="$organizationData" />
    @stack('structured-data')
</body>
</html>
