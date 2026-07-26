@props(['milestone', 'last' => false])
<li class='relative grid grid-cols-[2rem_minmax(0,1fr)] gap-3 pb-6'>
    @unless($last)<span class='absolute left-[0.95rem] top-6 h-[calc(100%-0.5rem)] w-px bg-navy/15' aria-hidden='true'></span>@endunless
    <span class='relative z-10 mt-1 flex h-8 w-8 items-center justify-center rounded-full border-4 border-white {{ $milestone->status->value === 'completed' ? 'bg-green-600' : ($milestone->status->value === 'in_progress' ? 'bg-blue-600' : 'bg-navy/25') }}'>
        <span class='sr-only'>{{ $milestone->status->label() }}</span>
    </span>
    <article class='min-w-0 rounded-xl border border-navy/10 bg-white p-4'>
        <div class='flex flex-wrap items-start justify-between gap-2'>
            <h3 class='safe-content font-bold text-navy'>{{ $milestone->title }}</h3>
            <x-customer.status-badge :status='$milestone->status' />
        </div>
        @if($milestone->description)<p class='mt-2 text-sm leading-6 text-muted'>{{ $milestone->description }}</p>@endif
        <p class='mt-3 text-xs font-semibold text-muted'>Target: <x-customer.date :value='$milestone->due_date' format='d M Y' fallback='Belum ditetapkan' /></p>
        @if($milestone->completed_at)<p class='mt-1 text-xs font-semibold text-green-700'>Selesai: <x-customer.date :value='$milestone->completed_at' /></p>@endif
    </article>
</li>
