@props(['links'])
<div x-ref='mobileMenu' id='mobile-navigation' class='border-t border-white/10 bg-navy px-4 pb-5 lg:hidden' x-cloak x-show='open' x-transition @click.outside='close()'>
    <div class='container-public grid gap-1 py-3'>
        @foreach($links as [$label, $url, $active])
            <a class='flex min-h-11 items-center rounded-xl px-4 text-sm font-semibold hover:bg-white/10 hover:text-gold {{ $active ? 'bg-white/10 text-gold' : '' }}' href='{{ $url }}' @click='close()' @if($active) aria-current='page' @endif>{{ $label }}</a>
        @endforeach
        <div class='mt-3 grid grid-cols-2 gap-3'>
            <x-secondary-button :href="auth()->check() ? (auth()->user()->isCustomer() ? route('customer.dashboard') : route('admin.dashboard')) : route('login')">{{ auth()->check() ? 'Panel' : 'Masuk' }}</x-secondary-button>
            <x-primary-button :href="route('contact.index')">Konsultasi</x-primary-button>
        </div>
    </div>
</div>
