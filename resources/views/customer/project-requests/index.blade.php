@extends('layouts.customer')
@section('title', 'Permintaan Proyek')
@section('content')
<x-customer.page-heading eyebrow='Pengajuan' title='Permintaan Proyek' description='Ajukan kebutuhan baru dan pantau hasil review admin.'>
    <a href='{{ route('customer.project-requests.create') }}' class='inline-flex min-h-11 items-center rounded-xl bg-navy px-5 text-sm font-bold text-white'>Buat permintaan</a>
</x-customer.page-heading>

<form method='GET' class='surface-card mt-6 grid gap-4 p-5 sm:grid-cols-[1fr_14rem_auto]'>
    <div>
        <label for='request-search' class='text-sm font-bold text-navy'>Cari kode atau judul</label>
        <input id='request-search' name='q' value='{{ $search }}' class='mt-2 min-h-11 w-full rounded-xl border border-navy/20 px-4' />
    </div>
    <div>
        <label for='request-status' class='text-sm font-bold text-navy'>Status</label>
        <select id='request-status' name='status' class='mt-2 min-h-11 w-full rounded-xl border border-navy/20 px-4'>
            <option value=''>Semua status</option>
            @foreach($statuses as $status)
                <option value='{{ $status->value }}' @selected($selectedStatus === $status)>{{ match($status) {
                    \App\Enums\ConsultationStatus::New => 'Menunggu Review',
                    \App\Enums\ConsultationStatus::Contacted => 'Perlu Info Tambahan',
                    \App\Enums\ConsultationStatus::Reviewed => 'Disetujui',
                    \App\Enums\ConsultationStatus::Converted => 'Menjadi Proyek',
                    \App\Enums\ConsultationStatus::Cancelled => 'Ditolak',
                    \App\Enums\ConsultationStatus::Closed => 'Ditutup',
                } }}</option>
            @endforeach
        </select>
    </div>
    <button class='min-h-11 self-end rounded-xl border border-navy px-5 font-bold text-navy'>Terapkan</button>
</form>

<div class='mt-6 space-y-4'>
    @forelse($consultations as $consultation)
        <a href='{{ route('customer.project-requests.show', $consultation) }}' class='surface-card block p-5 transition hover:border-gold sm:p-6'>
            <div class='flex flex-wrap items-start justify-between gap-3'>
                <div>
                    <p class='text-xs font-bold uppercase tracking-[0.15em] text-rose'>{{ $consultation->request_code }}</p>
                    <h2 class='mt-2 text-xl font-bold text-navy'>{{ $consultation->project_title }}</h2>
                    <p class='mt-2 text-sm text-muted'>{{ $consultation->service?->name ?? 'Layanan tidak tersedia' }}</p>
                </div>
                <span class='rounded-full bg-navy/10 px-3 py-1 text-xs font-bold text-navy'>{{ $consultation->customerStatusLabel() }}</span>
            </div>
        </a>
    @empty
        <div class='surface-card p-8 text-center'>
            <h2 class='text-xl font-bold text-navy'>Belum ada permintaan</h2>
            <p class='mt-2 text-sm text-muted'>Buat permintaan proyek pertama Anda.</p>
        </div>
    @endforelse
</div>

<div class='mt-6'>{{ $consultations->links() }}</div>
@endsection
