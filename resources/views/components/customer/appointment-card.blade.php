@props(['appointment'])
<article {{ $attributes->merge(['class' => 'surface-card min-w-0 p-5']) }}>
    <div class='flex flex-wrap items-start justify-between gap-3'>
        <h2 class='safe-content text-lg font-bold text-navy'>{{ $appointment->title }}</h2>
        <x-customer.status-badge :status='$appointment->status' />
    </div>
    <p class='mt-3 text-sm font-bold text-navy'><x-customer.date :value='$appointment->appointment_date' /> WIB</p>
    @if($appointment->notes)<p class='mt-3 text-sm leading-6 text-muted'>{{ $appointment->notes }}</p>@endif
    <div class='mt-4 flex flex-wrap gap-3'>
        <a href='{{ route('customer.projects.show', $appointment->project) }}' class='inline-flex min-h-11 items-center text-sm font-bold text-navy underline decoration-gold decoration-2 underline-offset-4'>{{ $appointment->project->project_code }}</a>
        @if($appointment->safeMeetingUrl())
            <a href='{{ $appointment->safeMeetingUrl() }}' target='_blank' rel='noopener noreferrer' class='inline-flex min-h-11 items-center rounded-xl bg-navy px-4 text-sm font-bold text-white'>Buka pertemuan</a>
        @endif
    </div>
</article>
