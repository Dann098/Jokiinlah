@extends('layouts.public')
@section('title', $portfolio->title.' | Portofolio Jokiinlah')
@section('description', \Illuminate\Support\Str::limit($portfolio->description, 155))
@section('content')
@php($portfolioImage = $portfolio->thumbnail && file_exists(public_path($portfolio->thumbnail)) ? asset($portfolio->thumbnail) : asset('images/logo.webp'))
<section class='bg-navy py-16 text-white'>
    <div class='container-public'>
        <x-breadcrumb :items="['Portofolio' => route('portfolios.index'), $portfolio->title => null]" />
        <div class='mt-7 flex flex-wrap gap-2'><x-badge>{{ $portfolio->category }}</x-badge><x-badge>Studi Kasus</x-badge></div>
        <h1 class='mt-5 text-balance text-4xl font-bold sm:text-5xl'>{{ $portfolio->title }}</h1>
        <p class='mt-5 max-w-3xl leading-8 text-white/75'>{{ $portfolio->description }}</p>
    </div>
</section>
<section class='section-space'>
    <div class='container-public'>
        <div class='hero-grid flex aspect-[16/7] min-h-64 items-center justify-center overflow-hidden rounded-[2rem] bg-navy-light'>
            <img src='{{ $portfolioImage }}' alt='Visual studi kasus {{ $portfolio->title }}' width='1280' height='560' class='h-full w-full object-contain p-8'>
        </div>
        <div class='mt-10 grid gap-6 lg:grid-cols-3'>
            @foreach([['Permasalahan', $portfolio->problem], ['Solusi', $portfolio->solution], ['Hasil', $portfolio->result]] as [$heading, $copy])
                <article class='surface-card p-7'><h2 class='text-2xl font-bold text-navy'>{{ $heading }}</h2><p class='mt-4 whitespace-pre-line text-sm leading-7 text-muted'>{{ $copy ?: 'Rincian studi kasus akan dilengkapi.' }}</p></article>
            @endforeach
        </div>
        @if($portfolio->gallery)
            <section class='mt-8'>
                <h2 class='text-2xl font-bold text-navy'>Galeri</h2>
                <div class='mt-4 grid gap-4 sm:grid-cols-2'>
                    @foreach($portfolio->gallery as $image)
                        @if(file_exists(public_path($image)))
                            <img src='{{ asset(ltrim($image, '/')) }}' alt='Galeri {{ $portfolio->title }}' width='720' height='440' loading='lazy' class='aspect-[16/10] w-full rounded-2xl border border-navy/10 object-cover'>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif
        @if($portfolio->technologies)
            <div class='mt-8 surface-card p-7'>
                <h2 class='text-2xl font-bold text-navy'>Teknologi</h2>
                <div class='mt-4 flex flex-wrap gap-2'>@foreach($portfolio->technologies as $technology)<x-badge>{{ $technology }}</x-badge>@endforeach</div>
            </div>
        @endif
        <div class='mt-10 flex flex-col gap-3 sm:flex-row'>
            <x-primary-button :href="route('contact.index')">Diskusikan Proyek Serupa</x-primary-button>
            <x-whatsapp-button :url='$whatsAppUrl' />
        </div>
    </div>
</section>
@endsection
