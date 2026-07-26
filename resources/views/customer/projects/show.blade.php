@extends('layouts.customer')
@section('title', $project->title)
@section('content')
<x-customer.breadcrumb :items="[['label' => 'Proyek Saya', 'url' => route('customer.projects.index')], ['label' => $project->project_code]]" />
<x-customer.page-heading :eyebrow='$project->project_code' :title='$project->title' description='Pusat informasi proyek, progress, milestone, file, revisi, pengingat, dan jadwal.'>
    <x-whatsapp-button :url='$whatsAppUrl' label='Tanyakan proyek via WhatsApp' />
</x-customer.page-heading>

<div class='surface-card overflow-hidden'>
    <div class='bg-navy p-5 text-white sm:p-7'>
        <div class='flex flex-wrap items-start justify-between gap-4'>
            <div>
                <p class='text-sm font-semibold text-white/65'>{{ $project->service->name }}</p>
                <div class='mt-3'><x-customer.status-badge :status='$project->status' /></div>
            </div>
            <dl class='grid grid-cols-2 gap-x-6 gap-y-2 text-sm'>
                <div><dt class='text-white/60'>Deadline</dt><dd class='mt-1 font-bold'><x-customer.date :value='$project->deadline' format='d M Y' fallback='Belum ditetapkan' /></dd></div>
                <div><dt class='text-white/60'>Diperbarui</dt><dd class='mt-1 font-bold'><x-customer.date :value='$project->updated_at' format='d M Y' /></dd></div>
            </dl>
        </div>
        <x-customer.progress-bar :value='$project->progress' class='mt-6 [&_span]:text-white [&_[role=progressbar]]:bg-white/15' />
    </div>
    <nav aria-label='Bagian detail proyek' class='flex gap-2 overflow-x-auto border-b border-navy/10 bg-white p-3 text-sm font-bold text-navy'>
        <a href='#informasi' class='min-h-11 shrink-0 rounded-lg px-3 py-3 hover:bg-cream'>Informasi</a>
        <a href='#milestone' class='min-h-11 shrink-0 rounded-lg px-3 py-3 hover:bg-cream'>Milestone</a>
        <a href='#file' class='min-h-11 shrink-0 rounded-lg px-3 py-3 hover:bg-cream'>File</a>
        <a href='#revisi' class='min-h-11 shrink-0 rounded-lg px-3 py-3 hover:bg-cream'>Revisi</a>
        <a href='#jadwal' class='min-h-11 shrink-0 rounded-lg px-3 py-3 hover:bg-cream'>Jadwal</a>
    </nav>
</div>

<section id='informasi' class='surface-card mt-6 p-5 sm:p-7' aria-labelledby='information-title'>
    <h2 id='information-title' class='text-2xl font-bold text-navy'>Informasi proyek</h2>
    <p class='mt-4 safe-content whitespace-pre-line text-sm leading-7 text-muted'>{{ $project->description }}</p>
    <dl class='mt-6 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4'>
        <div><dt class='text-muted'>Layanan</dt><dd class='mt-1 font-bold text-navy'>{{ $project->service->name }}</dd></div>
        <div><dt class='text-muted'>Kategori</dt><dd class='mt-1 font-bold text-navy'>{{ $project->service->category->label() }}</dd></div>
        <div><dt class='text-muted'>Mulai</dt><dd class='mt-1 font-bold text-navy'><x-customer.date :value='$project->start_date' format='d M Y' fallback='Belum ditetapkan' /></dd></div>
        <div><dt class='text-muted'>Pendamping</dt><dd class='mt-1 font-bold text-navy'>{{ $project->assignedStaff?->name ?? 'Sedang ditentukan' }}</dd></div>
    </dl>
</section>

<section id='milestone' class='surface-card mt-6 p-5 sm:p-7' aria-labelledby='milestones-title'>
    <div class='mb-6'><p class='text-xs font-bold uppercase tracking-[0.16em] text-rose'>Timeline</p><h2 id='milestones-title' class='mt-1 text-2xl font-bold text-navy'>Milestone proyek</h2></div>
    @if($project->milestones->isNotEmpty())
        <ol>@foreach($project->milestones as $milestone)<x-customer.milestone-item :milestone='$milestone' :last='$loop->last' />@endforeach</ol>
    @else
        <p class='text-sm text-muted'>Milestone belum ditambahkan.</p>
    @endif
</section>

<div class='mt-6 grid gap-6 xl:grid-cols-2'>
    <section id='file' class='surface-card p-5 sm:p-7' aria-labelledby='project-files-title'>
        <div class='flex flex-wrap items-center justify-between gap-3'>
            <h2 id='project-files-title' class='text-2xl font-bold text-navy'>File proyek</h2>
            <a href='{{ route('customer.projects.files.index', $project) }}' class='inline-flex min-h-11 items-center rounded-xl bg-navy px-4 text-sm font-bold text-white'>Kelola file</a>
        </div>
        <div class='mt-5 space-y-3'>
            @forelse($project->files->take(5) as $file)
                <div class='min-w-0 rounded-xl border border-navy/10 p-4'>
                    <p class='safe-content font-bold text-navy'>{{ $file->original_name }}</p>
                    <p class='mt-2 text-xs text-muted'>Versi {{ $file->version }} · <x-customer.file-size :bytes='$file->file_size' /></p>
                </div>
            @empty
                <p class='text-sm text-muted'>Belum ada file proyek.</p>
            @endforelse
        </div>
    </section>

    <section id='revisi' class='surface-card p-5 sm:p-7' aria-labelledby='project-revisions-title'>
        <div class='flex flex-wrap items-center justify-between gap-3'>
            <h2 id='project-revisions-title' class='text-2xl font-bold text-navy'>Permintaan revisi</h2>
            <a href='{{ route('customer.projects.revisions.index', $project) }}#form-revisi' class='inline-flex min-h-11 items-center rounded-xl bg-navy px-4 text-sm font-bold text-white'>Ajukan revisi</a>
        </div>
        <div class='mt-5 space-y-3'>
            @forelse($project->revisions->take(4) as $revision)
                <a href='{{ route('customer.projects.revisions.show', [$project, $revision]) }}' class='block min-w-0 rounded-xl border border-navy/10 p-4 hover:border-gold'>
                    <p class='safe-content font-bold text-navy'>{{ $revision->title }}</p>
                    <div class='mt-2'><x-customer.status-badge :status='$revision->status' /></div>
                </a>
            @empty
                <p class='text-sm text-muted'>Belum ada permintaan revisi.</p>
            @endforelse
        </div>
    </section>
</div>

<div id='jadwal' class='mt-6 grid gap-6 lg:grid-cols-2'>
    <section class='surface-card p-5 sm:p-7' aria-labelledby='project-reminders-title'>
        <h2 id='project-reminders-title' class='text-2xl font-bold text-navy'>Pengingat</h2>
        <div class='mt-5 space-y-3'>
            @forelse($project->reminders as $reminder)
                <div class='rounded-xl border border-navy/10 p-4'><p class='font-bold text-navy'>{{ $reminder->title }}</p><p class='mt-2 text-sm text-muted'><x-customer.date :value='$reminder->reminder_date' /> WIB</p></div>
            @empty
                <p class='text-sm text-muted'>Tidak ada pengingat untuk proyek ini.</p>
            @endforelse
        </div>
    </section>
    <section class='surface-card p-5 sm:p-7' aria-labelledby='project-appointments-title'>
        <h2 id='project-appointments-title' class='text-2xl font-bold text-navy'>Jadwal konsultasi</h2>
        <div class='mt-5 space-y-3'>
            @forelse($project->appointments as $appointment)
                <div class='rounded-xl border border-navy/10 p-4'>
                    <p class='font-bold text-navy'>{{ $appointment->title }}</p>
                    <p class='mt-2 text-sm text-muted'><x-customer.date :value='$appointment->appointment_date' /> WIB</p>
                    @if($appointment->safeMeetingUrl())<a href='{{ $appointment->safeMeetingUrl() }}' target='_blank' rel='noopener noreferrer' class='mt-3 inline-flex min-h-11 items-center font-bold text-navy underline'>Buka pertemuan</a>@endif
                </div>
            @empty
                <p class='text-sm text-muted'>Belum ada jadwal konsultasi.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
