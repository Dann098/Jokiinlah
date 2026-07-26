@props(['revision', 'project'])
<article {{ $attributes->merge(['class' => 'surface-card min-w-0 p-5']) }}>
    <div class='flex flex-wrap items-start justify-between gap-3'>
        <p class='text-xs font-bold uppercase tracking-[0.14em] text-rose'>Revisi #{{ $revision->id }}</p>
        <x-customer.status-badge :status='$revision->status' />
    </div>
    <h2 class='safe-content mt-3 text-xl font-bold text-navy'>{{ $revision->title }}</h2>
    <p class='mt-2 line-clamp-3 text-sm leading-6 text-muted'>{{ $revision->description }}</p>
    <div class='mt-4 flex flex-wrap items-center justify-between gap-3 text-xs text-muted'>
        <span>Dikirim <x-customer.date :value='$revision->created_at' /></span>
        <a href='{{ route('customer.projects.revisions.show', [$project, $revision]) }}' class='inline-flex min-h-11 items-center font-bold text-navy underline decoration-gold decoration-2 underline-offset-4'>Lihat detail</a>
    </div>
</article>
