@props(['href', 'active' => false, 'icon' => 'dot'])
<a href='{{ $href }}' @if($active) aria-current='page' @endif {{ $attributes->class([
    'flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
    'bg-white text-navy shadow-sm' => $active,
    'text-white/75 hover:bg-white/10 hover:text-white' => ! $active,
]) }}>
    <span class='flex h-6 w-6 shrink-0 items-center justify-center' aria-hidden='true'>
        @switch($icon)
            @case('home') <svg class='h-5 w-5' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8'><path d='m3 11 9-8 9 8v9H3z'/><path d='M9 20v-6h6v6'/></svg> @break
            @case('folder') <svg class='h-5 w-5' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8'><path d='M3 6h7l2 2h9v11H3z'/></svg> @break
            @case('bell') <svg class='h-5 w-5' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8'><path d='M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4'/></svg> @break
            @case('calendar') <svg class='h-5 w-5' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8'><path d='M4 5h16v16H4zM8 3v4m8-4v4M4 10h16'/></svg> @break
            @case('user') <svg class='h-5 w-5' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8'><circle cx='12' cy='8' r='4'/><path d='M4 21a8 8 0 0 1 16 0'/></svg> @break
            @default <span class='h-2 w-2 rounded-full bg-current'></span>
        @endswitch
    </span>
    <span class='truncate'>{{ $slot }}</span>
</a>
