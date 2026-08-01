@php($navigationLinks = [
    ['Beranda', route('home'), request()->routeIs('home')],
    ['Layanan', route('services.index'), request()->routeIs('services.*')],
    ['Fitur Gratis', route('free-tools.index'), request()->routeIs('free-tools.*')],
    ['Portofolio', route('portfolios.index'), request()->routeIs('portfolios.*')],
    ['Cara Kerja', route('home').'#cara-kerja', false],
    ['Artikel', route('articles.index'), request()->routeIs('articles.*')],
    ['FAQ', route('faq.index'), request()->routeIs('faq.*')],
    ['Kontak', route('contact.index'), request()->routeIs('contact.*')],
])
<header class='sticky top-0 z-50 bg-navy text-white transition' x-data='navigation' x-bind:data-scrolled='scrolled' @keydown.escape.window='close(true)'>
    <nav class='container-public flex min-h-20 items-center justify-between gap-3' aria-label='Navigasi utama'>
        <a href='{{ route('home') }}' aria-label='Jokiinlah beranda'><x-logo /></a>
        <div class='hidden items-center gap-4 lg:flex xl:gap-5'>@foreach($navigationLinks as [$label, $url, $active])<a class='whitespace-nowrap text-sm font-medium hover:text-gold {{ $active ? 'text-gold' : '' }}' href='{{ $url }}' @if($active) aria-current='page' @endif>{{ $label }}</a>@endforeach</div>
        <div class='hidden items-center gap-3 lg:flex'>
            @guest<a class='inline-flex min-h-11 items-center px-3 text-sm font-semibold hover:text-gold' href='{{ route('login') }}'>Masuk</a>@else<a class='inline-flex min-h-11 items-center px-3 text-sm font-semibold hover:text-gold' href='{{ auth()->user()->isCustomer() ? route('customer.dashboard') : route('filament.admin.pages.dashboard') }}'>Panel</a>@endguest
            <x-primary-button :href="route('contact.index')"><span class='xl:hidden'>Konsultasi</span><span class='hidden xl:inline'>Konsultasi Sekarang</span></x-primary-button>
        </div>
        <button x-ref='toggleButton' type='button' class='inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border border-white/25 lg:hidden' @click='toggle' x-bind:aria-expanded='open.toString()' x-bind:aria-label="open ? 'Tutup menu navigasi' : 'Buka menu navigasi'" aria-controls='mobile-navigation' aria-label='Buka menu navigasi'>
            <svg aria-hidden='true' class='h-6 w-6' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><path d='M4 6h16M4 12h16M4 18h16'/></svg>
        </button>
    </nav>
    <x-mobile-navigation :links='$navigationLinks' />
    <noscript><nav aria-label='Navigasi mobile tanpa JavaScript' class='border-t border-white/10 px-4 py-4 lg:hidden'><div class='container-public grid gap-2'>@foreach($navigationLinks as [$label, $url])<a class='flex min-h-11 items-center px-4 font-semibold' href='{{ $url }}'>{{ $label }}</a>@endforeach</div></nav></noscript>
</header>
