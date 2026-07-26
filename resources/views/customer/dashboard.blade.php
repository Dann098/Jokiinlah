@extends('layouts.customer')
@section('title', 'Ringkasan')
@section('content')
<x-customer.page-heading title="Halo, {{ auth()->user()->name }}" description='Ringkasan terbaru proyek, dokumen, revisi, dan jadwal Anda.'>
    <a href='{{ route('customer.projects.index') }}' class='inline-flex min-h-11 items-center rounded-xl bg-navy px-5 text-sm font-bold text-white'>Lihat semua proyek</a>
</x-customer.page-heading>

@if($recentProjects->isEmpty())
    <x-empty-state title='Belum ada proyek' description='Akun Anda belum terhubung dengan proyek. Anda tetap dapat melihat layanan atau mengirim permintaan konsultasi untuk memulai.' />
    <div class='mt-5 flex flex-col justify-center gap-3 sm:flex-row'>
        <a href='{{ route('services.index') }}' class='inline-flex min-h-11 items-center justify-center rounded-xl bg-navy px-5 text-sm font-bold text-white'>Lihat layanan</a>
        <a href='{{ route('contact.index') }}' class='inline-flex min-h-11 items-center justify-center rounded-xl border border-navy/20 bg-white px-5 text-sm font-bold text-navy'>Ajukan konsultasi</a>
        @php($emptyWhatsApp = app(\App\Services\WhatsAppUrlBuilder::class)->build('Halo, saya ingin berkonsultasi mengenai layanan Jokiinlah.'))
        <x-whatsapp-button :url='$emptyWhatsApp' label='Konsultasi via WhatsApp' />
    </div>
@else
    <section aria-labelledby='summary-title'>
        <h2 id='summary-title' class='sr-only'>Ringkasan proyek</h2>
        <div class='grid gap-4 sm:grid-cols-2 xl:grid-cols-4'>
            <x-customer.stat-card label='Proyek aktif' :value='$summary["active"]' hint='Selain proyek selesai atau dibatalkan' />
            <x-customer.stat-card label='Selesai' :value='$summary["completed"]' tone='green' hint='Proyek yang telah diselesaikan' />
            <x-customer.stat-card label='Menunggu data' :value='$summary["waiting_data"]' tone='gold' hint='Membutuhkan kelengkapan dari Anda' />
            <x-customer.stat-card label='Perlu review' :value='$summary["customer_review"]' tone='rose' hint='Siap untuk Anda periksa' />
        </div>
    </section>

    <div class='mt-8 grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.65fr)]'>
        <section aria-labelledby='recent-projects-title'>
            <div class='mb-4 flex items-end justify-between gap-3'>
                <div><p class='text-xs font-bold uppercase tracking-[0.16em] text-rose'>Terbaru</p><h2 id='recent-projects-title' class='mt-1 text-2xl font-bold text-navy'>Proyek Anda</h2></div>
                <a href='{{ route('customer.projects.index') }}' class='min-h-11 py-3 text-sm font-bold text-navy underline decoration-gold decoration-2 underline-offset-4'>Semua proyek</a>
            </div>
            <div class='grid gap-4 md:grid-cols-2'>
                @foreach($recentProjects as $project)<x-customer.project-card :project='$project' />@endforeach
            </div>
        </section>

        <div class='space-y-6'>
            <section class='surface-card p-5' aria-labelledby='actions-title'>
                <h2 id='actions-title' class='text-xl font-bold text-navy'>Tindakan yang dibutuhkan</h2>
                @forelse($actionProjects as $project)
                    <a href='{{ route('customer.projects.show', $project) }}' class='mt-4 block rounded-xl border border-navy/10 p-4 hover:border-gold'>
                        <span class='text-xs font-bold text-rose'>{{ $project->project_code }}</span>
                        <span class='safe-content mt-1 block font-bold text-navy'>{{ $project->title }}</span>
                        <span class='mt-2 inline-block'><x-customer.status-badge :status='$project->status' /></span>
                    </a>
                @empty
                    <p class='mt-3 text-sm leading-6 text-muted'>Tidak ada tindakan khusus yang dibutuhkan saat ini.</p>
                @endforelse
            </section>

            @if($upcomingMilestone)
                <section class='surface-card p-5' aria-labelledby='milestone-title'>
                    <p class='text-xs font-bold uppercase tracking-[0.16em] text-rose'>Milestone terdekat</p>
                    <h2 id='milestone-title' class='safe-content mt-2 text-xl font-bold text-navy'>{{ $upcomingMilestone->title }}</h2>
                    <p class='mt-2 text-sm text-muted'>{{ $upcomingMilestone->project->project_code }} · {{ $upcomingMilestone->project->title }}</p>
                    <p class='mt-3 text-sm font-bold text-navy'><x-customer.date :value='$upcomingMilestone->due_date' format='d M Y' /></p>
                </section>
            @endif
        </div>
    </div>

    <div class='mt-8 grid gap-6 lg:grid-cols-2'>
        <section class='surface-card p-5 sm:p-6' aria-labelledby='appointments-title'>
            <div class='flex items-center justify-between gap-3'><h2 id='appointments-title' class='text-xl font-bold text-navy'>Jadwal terdekat</h2><a href='{{ route('customer.appointments.index') }}' class='text-sm font-bold text-navy underline'>Semua</a></div>
            <div class='mt-4 space-y-3'>
                @forelse($upcomingAppointments as $appointment)
                    <div class='rounded-xl border border-navy/10 p-4'>
                        <p class='safe-content font-bold text-navy'>{{ $appointment->title }}</p>
                        <p class='mt-2 text-xs text-muted'><x-customer.date :value='$appointment->appointment_date' /> WIB · {{ $appointment->status->label() }}</p>
                    </div>
                @empty
                    <p class='text-sm text-muted'>Belum ada jadwal mendatang.</p>
                @endforelse
            </div>
        </section>

        <section class='surface-card p-5 sm:p-6' aria-labelledby='reminders-title'>
            <div class='flex items-center justify-between gap-3'><h2 id='reminders-title' class='text-xl font-bold text-navy'>Pengingat aktif</h2><a href='{{ route('customer.reminders.index') }}' class='text-sm font-bold text-navy underline'>Semua</a></div>
            <div class='mt-4 space-y-3'>
                @forelse($activeReminders as $reminder)
                    <div class='rounded-xl border border-navy/10 p-4'>
                        <p class='safe-content font-bold text-navy'>{{ $reminder->title }}</p>
                        <p class='mt-2 text-xs text-muted'><x-customer.date :value='$reminder->reminder_date' /> WIB</p>
                    </div>
                @empty
                    <p class='text-sm text-muted'>Tidak ada pengingat aktif.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class='mt-8 grid gap-6 lg:grid-cols-2'>
        <section class='surface-card p-5 sm:p-6' aria-labelledby='files-title'>
            <h2 id='files-title' class='text-xl font-bold text-navy'>File terbaru</h2>
            <div class='mt-4 divide-y divide-navy/10'>
                @forelse($latestFiles as $file)
                    <div class='min-w-0 py-3 first:pt-0'>
                        <p class='safe-content font-bold text-navy'>{{ $file->original_name }}</p>
                        <p class='mt-1 text-xs text-muted'>{{ $file->project->project_code }} · Versi {{ $file->version }} · <x-customer.date :value='$file->created_at' /></p>
                    </div>
                @empty
                    <p class='text-sm text-muted'>Belum ada file proyek.</p>
                @endforelse
            </div>
        </section>

        <section class='surface-card p-5 sm:p-6' aria-labelledby='revisions-title'>
            <h2 id='revisions-title' class='text-xl font-bold text-navy'>Revisi terbaru</h2>
            <div class='mt-4 divide-y divide-navy/10'>
                @forelse($latestRevisions as $revision)
                    <a href='{{ route('customer.projects.revisions.show', [$revision->project, $revision]) }}' class='block min-w-0 py-3 first:pt-0'>
                        <p class='safe-content font-bold text-navy'>{{ $revision->title }}</p>
                        <p class='mt-1 text-xs text-muted'>{{ $revision->project->project_code }} · {{ $revision->status->label() }}</p>
                    </a>
                @empty
                    <p class='text-sm text-muted'>Belum ada permintaan revisi.</p>
                @endforelse
            </div>
        </section>
    </div>
@endif
@endsection
