@props(['project'])
<article {{ $attributes->merge(['class' => 'surface-card flex h-full min-w-0 flex-col p-5 hover-lift']) }}>
    <div class='flex flex-wrap items-start justify-between gap-3'>
        <p class='text-xs font-bold uppercase tracking-[0.14em] text-rose'>{{ $project->project_code }}</p>
        <x-customer.status-badge :status='$project->status' />
    </div>
    <h2 class='safe-content mt-4 text-xl font-bold text-navy'>{{ $project->title }}</h2>
    <p class='mt-2 text-sm text-muted'>{{ $project->service?->name }}</p>
    <x-customer.progress-bar :value='$project->progress' class='mt-5' />
    <dl class='mt-5 grid grid-cols-2 gap-3 text-xs'>
        <div><dt class='text-muted'>Deadline</dt><dd class='mt-1 font-bold text-navy'><x-customer.date :value='$project->deadline' format='d M Y' fallback='Belum ditetapkan' /></dd></div>
        <div><dt class='text-muted'>Diperbarui</dt><dd class='mt-1 font-bold text-navy'><x-customer.date :value='$project->updated_at' format='d M Y' /></dd></div>
    </dl>
    <a href='{{ route('customer.projects.show', $project) }}' class='mt-6 inline-flex min-h-11 items-center justify-center rounded-xl bg-navy px-4 text-sm font-bold text-white hover:bg-navy-light'>Lihat detail proyek</a>
</article>
