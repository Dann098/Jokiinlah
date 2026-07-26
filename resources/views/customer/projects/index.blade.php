@extends('layouts.customer')
@section('title', 'Proyek Saya')
@section('content')
<x-customer.breadcrumb :items="[['label' => 'Proyek Saya']]" />
<x-customer.page-heading title='Proyek Saya' description='Cari dan pantau status seluruh proyek yang terhubung dengan akun Anda.' />

<form method='GET' action='{{ route('customer.projects.index') }}' class='surface-card mb-6 grid gap-4 p-5 md:grid-cols-[minmax(0,1fr)_15rem_auto]'>
    <x-form-input name='q' label='Cari proyek' :value='$search' hint='Cari berdasarkan kode atau judul proyek.' placeholder='Contoh: PRJ atau Portal' />
    <x-form-select name='status' label='Filter status'>
        <option value=''>Semua status</option>
        @foreach($statuses as $status)<option value='{{ $status->value }}' @selected($selectedStatus === $status)>{{ $status->label() }}</option>@endforeach
    </x-form-select>
    <div class='flex items-end gap-2'>
        <button type='submit' class='inline-flex min-h-12 flex-1 items-center justify-center rounded-xl bg-navy px-5 text-sm font-bold text-white'>Terapkan</button>
        @if($search !== '' || $selectedStatus)<a href='{{ route('customer.projects.index') }}' class='inline-flex min-h-12 items-center justify-center rounded-xl border border-navy/20 px-4 text-sm font-bold text-navy'>Reset</a>@endif
    </div>
</form>

<p class='mb-4 text-sm text-muted'>Menampilkan {{ $projects->count() }} dari {{ $projects->total() }} proyek.</p>
<div class='grid gap-5 md:grid-cols-2 xl:grid-cols-3'>
    @forelse($projects as $project)
        <x-customer.project-card :project='$project' />
    @empty
        <x-empty-state title='Proyek tidak ditemukan' description='Belum ada proyek yang cocok dengan pencarian atau filter ini.' />
    @endforelse
</div>
<x-pagination-wrapper :paginator='$projects' />
@endsection
