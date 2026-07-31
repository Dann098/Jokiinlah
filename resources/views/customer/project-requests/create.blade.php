@extends('layouts.customer')
@section('title', 'Buat Permintaan Proyek')
@section('content')
<x-customer.breadcrumb :items="[['label' => 'Permintaan Proyek', 'url' => route('customer.project-requests.index')], ['label' => 'Buat']]" />
<x-customer.page-heading eyebrow='Pengajuan baru' title='Buat Permintaan Proyek' description='Admin akan meninjau kebutuhan sebelum proyek dibuat.' />

@if($errors->any())
    <div role='alert' aria-live='assertive' class='mt-6 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-900'>
        <p class='font-bold'>Periksa kembali formulir.</p>
        <ul class='mt-2 list-disc pl-5'>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<form method='POST' action='{{ route('customer.project-requests.store') }}' enctype='multipart/form-data' class='surface-card mt-6 grid gap-5 p-5 sm:p-7'>
    @csrf
    <div>
        <label for='service_id' class='font-bold text-navy'>Layanan <span aria-hidden='true'>*</span></label>
        <select id='service_id' name='service_id' required aria-describedby='service_id-error' class='mt-2 min-h-11 w-full rounded-xl border border-navy/20 px-4'>
            <option value=''>Pilih layanan</option>
            @foreach($services as $service)<option value='{{ $service->id }}' @selected(old('service_id') == $service->id)>{{ $service->name }}</option>@endforeach
        </select>
        @error('service_id')<p id='service_id-error' class='mt-2 text-sm text-red-700'>{{ $message }}</p>@enderror
    </div>
    <div>
        <label for='project_title' class='font-bold text-navy'>Judul proyek <span aria-hidden='true'>*</span></label>
        <input id='project_title' name='project_title' value='{{ old('project_title') }}' required maxlength='180' class='mt-2 min-h-11 w-full rounded-xl border border-navy/20 px-4' />
    </div>
    <div>
        <label for='description' class='font-bold text-navy'>Deskripsi kebutuhan <span aria-hidden='true'>*</span></label>
        <textarea id='description' name='description' required minlength='30' maxlength='5000' rows='7' class='mt-2 w-full rounded-xl border border-navy/20 p-4'>{{ old('description') }}</textarea>
    </div>
    <div class='grid gap-5 md:grid-cols-2'>
        <div><label for='phone' class='font-bold text-navy'>Nomor WhatsApp <span aria-hidden='true'>*</span></label><input id='phone' name='phone' value='{{ old('phone', auth()->user()->phone) }}' required class='mt-2 min-h-11 w-full rounded-xl border border-navy/20 px-4' /></div>
        <div><label for='deadline' class='font-bold text-navy'>Deadline</label><input id='deadline' type='date' name='deadline' value='{{ old('deadline') }}' class='mt-2 min-h-11 w-full rounded-xl border border-navy/20 px-4' /></div>
        <div><label for='technology' class='font-bold text-navy'>Teknologi</label><input id='technology' name='technology' value='{{ old('technology') }}' maxlength='255' class='mt-2 min-h-11 w-full rounded-xl border border-navy/20 px-4' /></div>
        <div><label for='budget' class='font-bold text-navy'>Estimasi anggaran</label><input id='budget' type='number' min='0' name='budget' value='{{ old('budget') }}' class='mt-2 min-h-11 w-full rounded-xl border border-navy/20 px-4' /></div>
    </div>
    <div><label for='attachment' class='font-bold text-navy'>Lampiran opsional</label><input id='attachment' type='file' name='attachment' class='mt-2 block w-full rounded-xl border border-navy/20 p-3' /><p class='mt-2 text-xs text-muted'>Dokumen disimpan privat. Untuk dokumen proyek setelah konversi, gunakan modul File Proyek.</p></div>
    <label class='flex items-start gap-3'><input type='checkbox' name='privacy' value='1' required class='mt-1 h-5 w-5' /><span class='text-sm'>Saya menyetujui kebijakan privasi.</span></label>
    <label class='flex items-start gap-3'><input type='checkbox' name='academic_integrity' value='1' required class='mt-1 h-5 w-5' /><span class='text-sm'>Saya menyetujui ketentuan integritas akademik dan memiliki hak atas data yang dikirim.</span></label>
    <button class='min-h-11 rounded-xl bg-navy px-5 font-bold text-white'>Kirim permintaan</button>
</form>
@endsection
