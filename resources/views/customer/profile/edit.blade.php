@extends('layouts.customer')
@section('title', 'Profil')
@section('content')
<x-customer.breadcrumb :items="[['label' => 'Profil']]" />
<x-customer.page-heading title='Profil dan Keamanan' description='Perbarui nama, nomor WhatsApp, dan kata sandi akun. Email terverifikasi tidak dapat diubah dari portal ini.' />

<x-customer.error-summary />
<div class='grid gap-6 xl:grid-cols-2'>
    <section class='surface-card p-5 sm:p-7' aria-labelledby='profile-title'>
        <h2 id='profile-title' class='text-2xl font-bold text-navy'>Informasi profil</h2>
        <form method='POST' action='{{ route('customer.profile.update') }}' class='mt-6 grid gap-5'>
            @csrf
            @method('PATCH')
            <x-form-input name='name' label='Nama lengkap' :value='$user->name' required autocomplete='name' />
            <x-form-input name='phone' label='Nomor WhatsApp' :value='$user->phone' type='tel' autocomplete='tel' hint='Gunakan nomor Indonesia, misalnya 081234567890.' />
            <div>
                <label for='email-readonly' class='mb-2 block text-sm font-bold text-navy'>Email terverifikasi</label>
                <input id='email-readonly' type='email' value='{{ $user->email }}' readonly aria-describedby='email-readonly-hint' class='min-h-12 w-full rounded-xl border border-navy/10 bg-cream px-4 py-3 text-sm text-muted'>
                <p id='email-readonly-hint' class='mt-2 text-xs leading-5 text-muted'>Perubahan email memerlukan proses verifikasi terpisah dan tidak tersedia di portal ini.</p>
            </div>
            <div><button type='submit' class='inline-flex min-h-11 items-center justify-center rounded-xl bg-navy px-5 text-sm font-bold text-white'>Simpan profil</button></div>
        </form>
    </section>

    <section class='surface-card p-5 sm:p-7' aria-labelledby='password-title'>
        <h2 id='password-title' class='text-2xl font-bold text-navy'>Ubah kata sandi</h2>
        <p class='mt-2 text-sm leading-6 text-muted'>Gunakan minimal 12 karakter dengan huruf besar, huruf kecil, angka, dan simbol.</p>
        <form method='POST' action='{{ route('customer.password.update') }}' class='mt-6 grid gap-5'>
            @csrf
            @method('PUT')
            <x-form-input name='current_password' label='Kata sandi saat ini' type='password' required autocomplete='current-password' />
            <x-form-input name='password' label='Kata sandi baru' type='password' required autocomplete='new-password' />
            <x-form-input name='password_confirmation' label='Konfirmasi kata sandi baru' type='password' required autocomplete='new-password' />
            <div><button type='submit' class='inline-flex min-h-11 items-center justify-center rounded-xl bg-navy px-5 text-sm font-bold text-white'>Perbarui kata sandi</button></div>
        </form>
    </section>
</div>
@endsection
