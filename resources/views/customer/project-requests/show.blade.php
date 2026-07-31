@extends('layouts.customer')
@section('title', $consultation->request_code)
@section('content')
<x-customer.breadcrumb :items="[['label' => 'Permintaan Proyek', 'url' => route('customer.project-requests.index')], ['label' => $consultation->request_code]]" />
<x-customer.page-heading :eyebrow='$consultation->request_code' :title='$consultation->project_title' description='Detail dan perkembangan review permintaan proyek.' />

<div class='surface-card mt-6 p-5 sm:p-7'>
    <div class='flex flex-wrap items-center justify-between gap-3'>
        <span class='rounded-full bg-navy/10 px-3 py-1 text-sm font-bold text-navy'>{{ $consultation->customerStatusLabel() }}</span>
        <span class='text-sm text-muted'>Dikirim <x-customer.date :value='$consultation->created_at' /></span>
    </div>
    <dl class='mt-6 grid gap-5 text-sm sm:grid-cols-2'>
        <div><dt class='text-muted'>Layanan</dt><dd class='mt-1 font-bold text-navy'>{{ $consultation->service?->name ?? '—' }}</dd></div>
        <div><dt class='text-muted'>Deadline</dt><dd class='mt-1 font-bold text-navy'><x-customer.date :value='$consultation->deadline' format='d M Y' fallback='Belum ditentukan' /></dd></div>
        <div class='sm:col-span-2'><dt class='text-muted'>Deskripsi</dt><dd class='mt-2 whitespace-pre-line leading-7 text-charcoal'>{{ $consultation->description }}</dd></div>
    </dl>
</div>

@if($consultation->customer_response)
    <section class='mt-6 rounded-xl border border-gold/40 bg-white p-5 sm:p-7' aria-labelledby='admin-response-title'>
        <h2 id='admin-response-title' class='text-xl font-bold text-navy'>Tanggapan admin</h2>
        <p class='mt-3 whitespace-pre-line text-sm leading-7'>{{ $consultation->customer_response }}</p>
    </section>
@endif

@if($consultation->rejection_reason)
    <section class='mt-6 rounded-xl border border-red-300 bg-red-50 p-5 text-red-950' aria-labelledby='rejection-title'>
        <h2 id='rejection-title' class='text-xl font-bold'>Alasan penolakan</h2>
        <p class='mt-3 whitespace-pre-line text-sm leading-7'>{{ $consultation->rejection_reason }}</p>
    </section>
@endif

@if($consultation->status === \App\Enums\ConsultationStatus::Contacted && !$consultation->project)
    <form method='POST' action='{{ route('customer.project-requests.update', $consultation) }}' class='surface-card mt-6 p-5 sm:p-7'>
        @csrf
        @method('PATCH')
        <h2 class='text-xl font-bold text-navy'>Lengkapi informasi</h2>
        <label for='description' class='mt-5 block font-bold text-navy'>Deskripsi terbaru</label>
        <textarea id='description' name='description' required minlength='30' maxlength='5000' rows='7' class='mt-2 w-full rounded-xl border border-navy/20 p-4'>{{ old('description', $consultation->description) }}</textarea>
        @error('description')<p class='mt-2 text-sm text-red-700'>{{ $message }}</p>@enderror
        <button class='mt-4 min-h-11 rounded-xl bg-navy px-5 font-bold text-white'>Kirim informasi tambahan</button>
    </form>
@endif

@if($consultation->project)
    <section class='mt-6 rounded-xl bg-navy p-5 text-white sm:p-7'>
        <h2 class='text-xl font-bold'>Permintaan telah menjadi proyek</h2>
        <p class='mt-2 text-sm text-white/75'>{{ $consultation->project->project_code }} · {{ $consultation->project->title }}</p>
        <a href='{{ route('customer.projects.show', $consultation->project) }}' class='mt-4 inline-flex min-h-11 items-center rounded-xl bg-white px-5 font-bold text-navy'>Buka proyek</a>
    </section>
@endif
@endsection
