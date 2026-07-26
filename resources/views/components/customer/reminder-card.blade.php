@props(['reminder'])
<article {{ $attributes->merge(['class' => 'surface-card min-w-0 p-5']) }}>
    <div class='flex flex-wrap items-start justify-between gap-3'>
        <h2 class='safe-content text-lg font-bold text-navy'>{{ $reminder->title }}</h2>
        <span class='rounded-full px-2.5 py-1 text-xs font-bold {{ $reminder->is_completed ? 'bg-green-50 text-green-800' : 'bg-amber-50 text-amber-900' }}'>{{ $reminder->is_completed ? 'Selesai' : 'Aktif' }}</span>
    </div>
    @if($reminder->description)<p class='mt-3 text-sm leading-6 text-muted'>{{ $reminder->description }}</p>@endif
    <p class='mt-4 text-xs font-bold text-navy'><x-customer.date :value='$reminder->reminder_date' /> WIB</p>
    @if($reminder->project)
        <a href='{{ route('customer.projects.show', $reminder->project) }}' class='mt-3 inline-flex min-h-11 items-center text-sm font-bold text-navy underline decoration-gold decoration-2 underline-offset-4'>{{ $reminder->project->project_code }} · {{ $reminder->project->title }}</a>
    @endif
</article>
