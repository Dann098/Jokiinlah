@extends('layouts.customer')
@section('title', 'Jadwal')
@section('content')
<x-customer.breadcrumb :items="[['label' => 'Jadwal']]" />
<x-customer.page-heading title='Jadwal Konsultasi' description='Jadwal yang terkait dengan proyek Anda. Seluruh waktu ditampilkan dalam zona Asia/Jakarta (WIB).' />
<div class='grid gap-5 md:grid-cols-2 xl:grid-cols-3'>
    @forelse($appointments as $appointment)
        <x-customer.appointment-card :appointment='$appointment' />
    @empty
        <x-empty-state title='Belum ada jadwal' description='Belum ada konsultasi atau pertemuan yang dijadwalkan untuk akun Anda.' />
    @endforelse
</div>
<x-pagination-wrapper :paginator='$appointments' />
@endsection
