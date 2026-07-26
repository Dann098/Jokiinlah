<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <meta name='csrf-token' content='{{ csrf_token() }}'>
    <meta name='robots' content='noindex,nofollow'>
    <title>@yield('title', 'Portal Pelanggan') | Jokiinlah</title>
    <link rel='icon' type='image/png' href='{{ asset('images/favicon.png') }}'>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class='bg-cream text-charcoal antialiased'>
    <a href='#main-content' class='fixed left-3 top-3 z-[100] -translate-y-24 rounded-lg bg-white px-4 py-3 font-bold text-navy shadow-lg transition focus:translate-y-0'>Lewati ke konten utama</a>

    <div x-data='portalNavigation' class='min-h-screen lg:grid lg:grid-cols-[18rem_minmax(0,1fr)]'>
        <aside class='hidden min-h-screen bg-navy text-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col' aria-label='Navigasi portal'>
            <div class='border-b border-white/10 px-6 py-6'>
                <a href='{{ route('customer.dashboard') }}' aria-label='Portal Jokiinlah'>
                    <x-logo />
                </a>
            </div>
            <nav class='flex-1 space-y-1 overflow-y-auto px-4 py-6'>
                <x-customer.sidebar-link :href="route('customer.dashboard')" :active="request()->routeIs('customer.dashboard')" icon='home'>Ringkasan</x-customer.sidebar-link>
                <x-customer.sidebar-link :href="route('customer.projects.index')" :active="request()->routeIs('customer.projects.*')" icon='folder'>Proyek Saya</x-customer.sidebar-link>
                <x-customer.sidebar-link :href="route('customer.reminders.index')" :active="request()->routeIs('customer.reminders.*')" icon='bell'>Pengingat</x-customer.sidebar-link>
                <x-customer.sidebar-link :href="route('customer.appointments.index')" :active="request()->routeIs('customer.appointments.*')" icon='calendar'>Jadwal</x-customer.sidebar-link>
                <x-customer.sidebar-link :href="route('customer.profile.edit')" :active="request()->routeIs('customer.profile.*') || request()->routeIs('customer.password.*')" icon='user'>Profil</x-customer.sidebar-link>
            </nav>
            <div class='border-t border-white/10 p-4'>
                <p class='truncate px-3 text-sm font-bold'>{{ auth()->user()->name }}</p>
                <p class='truncate px-3 pt-1 text-xs text-white/60'>{{ auth()->user()->email }}</p>
                <form method='POST' action='{{ route('logout') }}' class='mt-4'>
                    @csrf
                    <button type='submit' class='flex min-h-11 w-full items-center justify-center rounded-xl border border-white/20 px-4 text-sm font-bold text-white transition hover:bg-white/10'>Keluar</button>
                </form>
            </div>
        </aside>

        <div class='min-w-0'>
            <header class='sticky top-0 z-40 border-b border-navy/10 bg-white/95 backdrop-blur'>
                <div class='flex min-h-18 items-center justify-between gap-3 px-4 sm:px-6 lg:px-8'>
                    <div class='flex min-w-0 items-center gap-3'>
                        <button x-ref='toggleButton' type='button' class='inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-navy/15 text-navy lg:hidden' @click='openMenu' :aria-expanded='open.toString()' aria-controls='customer-mobile-menu'>
                            <span class='sr-only'>Buka navigasi portal</span>
                            <svg aria-hidden='true' class='h-6 w-6' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><path d='M4 7h16M4 12h16M4 17h16'/></svg>
                        </button>
                        <div class='min-w-0'>
                            <p class='truncate text-xs font-bold uppercase tracking-[0.16em] text-rose'>Customer Portal</p>
                            <p class='truncate text-sm font-semibold text-navy'>Pantau proyek dengan aman</p>
                        </div>
                    </div>

                    <div class='flex items-center gap-2'>
                        @php($unreadNotifications = auth()->user()->unreadNotifications()->count())
                        <a href='{{ route('customer.reminders.index') }}' class='relative inline-flex h-11 w-11 items-center justify-center rounded-xl border border-navy/15 text-navy' aria-label='Pengingat{{ $unreadNotifications ? ", {$unreadNotifications} notifikasi belum dibaca" : '' }}'>
                            <svg aria-hidden='true' class='h-5 w-5' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8'><path d='M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4'/></svg>
                            @if($unreadNotifications)
                                <span class='absolute right-1 top-1 min-w-4 rounded-full bg-red-600 px-1 text-center text-[0.65rem] font-bold text-white'>{{ min($unreadNotifications, 99) }}</span>
                            @endif
                        </a>
                        <details class='relative'>
                            <summary class='flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-xl border border-navy/15 bg-white px-3 text-sm font-bold text-navy'>
                                <span class='flex h-7 w-7 items-center justify-center rounded-full bg-navy text-xs text-white'>{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                                <span class='hidden max-w-36 truncate sm:block'>{{ auth()->user()->name }}</span>
                            </summary>
                            <div class='absolute right-0 mt-2 w-56 rounded-xl border border-navy/10 bg-white p-2 shadow-xl'>
                                <a href='{{ route('customer.profile.edit') }}' class='flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-navy hover:bg-cream'>Pengaturan profil</a>
                                <form method='POST' action='{{ route('logout') }}'>
                                    @csrf
                                    <button type='submit' class='min-h-11 w-full rounded-lg px-3 text-left text-sm font-semibold text-red-700 hover:bg-red-50'>Keluar</button>
                                </form>
                            </div>
                        </details>
                    </div>
                </div>
            </header>

            <div x-cloak x-show='open' x-transition.opacity class='fixed inset-0 z-50 bg-navy/60 lg:hidden' @click.self='closeMenu'>
                <aside id='customer-mobile-menu' x-ref='mobileMenu' x-show='open' x-transition class='flex h-full w-[min(86vw,20rem)] flex-col bg-navy text-white' aria-label='Navigasi portal mobile'>
                    <div class='flex items-center justify-between border-b border-white/10 px-5 py-5'>
                        <x-logo compact />
                        <button type='button' class='inline-flex h-11 w-11 items-center justify-center rounded-xl border border-white/20' @click='closeMenu(true)'>
                            <span class='sr-only'>Tutup navigasi portal</span>
                            <svg aria-hidden='true' class='h-6 w-6' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><path d='m6 6 12 12M18 6 6 18'/></svg>
                        </button>
                    </div>
                    <nav class='flex-1 space-y-1 overflow-y-auto p-4'>
                        <x-customer.sidebar-link :href="route('customer.dashboard')" :active="request()->routeIs('customer.dashboard')" icon='home'>Ringkasan</x-customer.sidebar-link>
                        <x-customer.sidebar-link :href="route('customer.projects.index')" :active="request()->routeIs('customer.projects.*')" icon='folder'>Proyek Saya</x-customer.sidebar-link>
                        <x-customer.sidebar-link :href="route('customer.reminders.index')" :active="request()->routeIs('customer.reminders.*')" icon='bell'>Pengingat</x-customer.sidebar-link>
                        <x-customer.sidebar-link :href="route('customer.appointments.index')" :active="request()->routeIs('customer.appointments.*')" icon='calendar'>Jadwal</x-customer.sidebar-link>
                        <x-customer.sidebar-link :href="route('customer.profile.edit')" :active="request()->routeIs('customer.profile.*')" icon='user'>Profil</x-customer.sidebar-link>
                    </nav>
                </aside>
            </div>

            @if(session('status'))
                <div role='status' aria-live='polite' class='border-b border-green-200 bg-green-50 px-4 py-3 text-sm font-bold text-green-900 sm:px-6 lg:px-8'>{{ session('status') }}</div>
            @endif

            <main id='main-content' tabindex='-1' class='min-h-[calc(100vh-9rem)] px-4 py-6 sm:px-6 lg:px-8 lg:py-8'>
                @yield('content')
            </main>

            <footer class='border-t border-navy/10 bg-white px-4 py-5 text-center text-xs text-muted sm:px-6 lg:px-8'>
                Portal pelanggan Jokiinlah · Data proyek dilindungi dengan akses terautentikasi.
            </footer>
        </div>
    </div>
</body>
</html>
