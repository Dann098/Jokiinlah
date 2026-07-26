@extends('layouts.customer')
@section('title', 'Pengingat')
@section('content')
<x-customer.breadcrumb :items="[['label' => 'Pengingat']]" />
<x-customer.page-heading title='Pengingat' description='Daftar pengingat yang ditujukan kepada akun Anda dan terkait dengan proyek milik Anda.' />
<div class='grid gap-5 md:grid-cols-2 xl:grid-cols-3'>
    @forelse($reminders as $reminder)
        <x-customer.reminder-card :reminder='$reminder' />
    @empty
        <x-empty-state title='Belum ada pengingat' description='Tidak ada pengingat yang ditujukan kepada Anda saat ini.' />
    @endforelse
</div>
<x-pagination-wrapper :paginator='$reminders' />
@endsection
