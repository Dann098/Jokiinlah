@extends('layouts.customer')
@section('title', 'Revisi '.$project->project_code)
@section('content')
<x-customer.breadcrumb :items="[
    ['label' => 'Proyek Saya', 'url' => route('customer.projects.index')],
    ['label' => $project->project_code, 'url' => route('customer.projects.show', $project)],
    ['label' => 'Revisi'],
]" />
<x-customer.page-heading title='Permintaan Revisi' :description="'Lihat riwayat dan sampaikan kebutuhan revisi untuk '.$project->title.'. Status dikelola oleh tim Jokiinlah.'" />

<section class='grid gap-5 lg:grid-cols-2' aria-labelledby='revision-list-title'>
    <h2 id='revision-list-title' class='sr-only'>Daftar revisi</h2>
    @forelse($revisions as $revision)
        <x-customer.revision-card :revision='$revision' :project='$project' />
    @empty
        <x-empty-state title='Belum ada revisi' description='Anda belum pernah mengirim permintaan revisi untuk proyek ini.' />
    @endforelse
</section>
<x-pagination-wrapper :paginator='$revisions' />

<x-customer.upload-panel id='form-revisi' class='mt-8' title='Ajukan permintaan revisi' description='Jelaskan perubahan yang dibutuhkan secara spesifik. Tim akan meninjau permintaan sebelum status diperbarui.'>
    <x-customer.error-summary />
    <form method='POST' action='{{ route('customer.projects.revisions.store', $project) }}' enctype='multipart/form-data' class='grid gap-5'>
        @csrf
        <x-form-input name='title' label='Judul revisi' :value="old('title')" required placeholder='Contoh: Penyesuaian alur persetujuan' />
        <x-form-input name='section_reference' label='Bagian referensi (opsional)' :value="old('section_reference')" hint='Contoh: Halaman dashboard atau Bab 3.' />
        <x-form-textarea name='description' label='Deskripsi kebutuhan' :value="old('description')" rows='6' required hint='Minimal 10 karakter. Jangan cantumkan credential.' />
        <x-form-file name='attachment' label='Lampiran (opsional)' accept='.pdf,.doc,.docx,.xls,.xlsx,.csv,.zip,.rar,.jpg,.jpeg,.png,.webp' hint='Lampiran disimpan private dengan nama fisik UUID. Maksimal 20 MB.' />
        <label class='flex items-start gap-3 rounded-xl border border-navy/10 bg-cream p-4 text-sm leading-6 text-navy'>
            <input type='checkbox' required class='mt-1 h-5 w-5 rounded border-navy/20'>
            <span>Saya memastikan permintaan ini berkaitan dengan scope proyek dan tidak memuat password atau token akses.</span>
        </label>
        <div><button type='submit' class='inline-flex min-h-11 items-center justify-center rounded-xl bg-navy px-5 text-sm font-bold text-white'>Kirim permintaan revisi</button></div>
    </form>
</x-customer.upload-panel>
@endsection
