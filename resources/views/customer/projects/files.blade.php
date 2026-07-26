@extends('layouts.customer')
@section('title', 'File '.$project->project_code)
@section('content')
<x-customer.breadcrumb :items="[
    ['label' => 'Proyek Saya', 'url' => route('customer.projects.index')],
    ['label' => $project->project_code, 'url' => route('customer.projects.show', $project)],
    ['label' => 'File'],
]" />
<x-customer.page-heading title='File Proyek' :description="'Unggah dan unduh dokumen private untuk '.$project->project_code.'. Versi lama tetap tersimpan dan tidak dapat dihapus oleh pelanggan.'" />

<x-customer.upload-panel title='Unggah dokumen baru' description='Sistem membuat identitas dokumen dan versi pertama secara otomatis.'>
    <x-customer.error-summary />
    <form method='POST' action='{{ route('customer.projects.files.store', $project) }}' enctype='multipart/form-data' class='grid gap-5'>
        @csrf
        <x-form-file name='file' id='new-file' label='Pilih berkas' accept='.pdf,.doc,.docx,.xls,.xlsx,.csv,.zip,.rar,.jpg,.jpeg,.png,.webp' hint='PDF, dokumen Office, CSV, ZIP/RAR, atau gambar. Maksimal 20 MB. Archive disimpan tanpa diekstrak.' required />
        <x-form-select name='category' id='new-category' label='Kategori' required>
            <option value=''>Pilih kategori</option>
            @foreach(config('jokiinlah.project_file_categories') as $value => $label)<option value='{{ $value }}' @selected(old('category') === $value)>{{ $label }}</option>@endforeach
        </x-form-select>
        <x-form-textarea name='description' id='new-description' label='Keterangan (opsional)' :value="old('description')" rows='3' hint='Jangan cantumkan password, token, atau credential lain.' />
        <div><button type='submit' class='inline-flex min-h-11 items-center justify-center rounded-xl bg-navy px-5 text-sm font-bold text-white'>Unggah dokumen</button></div>
    </form>
</x-customer.upload-panel>

<section class='mt-7' aria-labelledby='document-list-title'>
    <div class='mb-4 flex flex-wrap items-end justify-between gap-3'>
        <div><p class='text-xs font-bold uppercase tracking-[0.16em] text-rose'>Private storage</p><h2 id='document-list-title' class='mt-1 text-2xl font-bold text-navy'>Dokumen proyek</h2></div>
        <p class='text-sm text-muted'>{{ $files->count() }} dokumen logis</p>
    </div>
    <div class='grid gap-5 lg:grid-cols-2'>
        @forelse($files as $documentVersions)
            @php($latest = $documentVersions->sortByDesc('version')->first())
            <x-customer.file-card :title='$latest->original_name' :category="config('jokiinlah.project_file_categories.'.$latest->category, str($latest->category)->replace('_', ' ')->title())" :version='$latest->version'>
                <dl class='grid grid-cols-2 gap-3 text-xs'>
                    <div><dt class='text-muted'>Ukuran</dt><dd class='mt-1 font-bold text-navy'><x-customer.file-size :bytes='$latest->file_size' /></dd></div>
                    <div><dt class='text-muted'>Diunggah</dt><dd class='mt-1 font-bold text-navy'><x-customer.date :value='$latest->created_at' /></dd></div>
                    <div><dt class='text-muted'>Pengunggah</dt><dd class='mt-1 font-bold text-navy'>{{ $latest->uploader?->name ?? 'Akun tidak tersedia' }}</dd></div>
                    <div><dt class='text-muted'>Integritas</dt><dd class='mt-1 font-bold text-green-700'>Checksum tersedia</dd></div>
                </dl>
                @if($latest->description)<p class='mt-3 safe-content text-sm leading-6 text-muted'>{{ $latest->description }}</p>@endif
                <div class='mt-4 flex flex-wrap gap-3'>
                    <a href='{{ route('customer.projects.files.download', [$project, $latest]) }}' class='inline-flex min-h-11 items-center rounded-xl bg-navy px-4 text-sm font-bold text-white'>Unduh versi {{ $latest->version }}</a>
                </div>

                <x-customer.file-version-list>
                    <ol class='space-y-3'>
                        @foreach($documentVersions->sortByDesc('version') as $version)
                            <li class='flex min-w-0 flex-wrap items-center justify-between gap-3 rounded-lg bg-white p-3'>
                                <div class='min-w-0'><p class='safe-content text-sm font-bold text-navy'>Versi {{ $version->version }} · {{ $version->original_name }}</p><p class='mt-1 text-xs text-muted'><x-customer.date :value='$version->created_at' /> · <x-customer.file-size :bytes='$version->file_size' /></p></div>
                                <a href='{{ route('customer.projects.files.download', [$project, $version]) }}' class='inline-flex min-h-11 items-center px-2 text-sm font-bold text-navy underline'>Unduh</a>
                            </li>
                        @endforeach
                    </ol>
                </x-customer.file-version-list>

                <details class='group mt-4 rounded-xl border border-navy/10'>
                    <summary class='flex min-h-11 cursor-pointer list-none items-center justify-between px-4 py-2 text-sm font-bold text-navy'>Unggah versi baru <span aria-hidden='true' class='transition group-open:rotate-180'>⌄</span></summary>
                    <form method='POST' action='{{ route('customer.projects.files.versions.store', [$project, $latest]) }}' enctype='multipart/form-data' class='grid gap-4 border-t border-navy/10 p-4'>
                        @csrf
                        <x-form-file name='file' :id="'version-file-'.$latest->id" label='Berkas versi baru' accept='.pdf,.doc,.docx,.xls,.xlsx,.csv,.zip,.rar,.jpg,.jpeg,.png,.webp' hint='Nomor versi ditentukan oleh server.' required />
                        <x-form-select name='category' :id="'version-category-'.$latest->id" label='Kategori' required>
                            @foreach(config('jokiinlah.project_file_categories') as $value => $label)<option value='{{ $value }}' @selected($latest->category === $value)>{{ $label }}</option>@endforeach
                        </x-form-select>
                        <x-form-textarea name='description' :id="'version-description-'.$latest->id" label='Keterangan (opsional)' rows='2' />
                        <button type='submit' class='inline-flex min-h-11 items-center justify-center rounded-xl bg-navy px-4 text-sm font-bold text-white'>Unggah versi baru</button>
                    </form>
                </details>
            </x-customer.file-card>
        @empty
            <x-empty-state title='Belum ada file' description='Unggah dokumen pertama menggunakan formulir di atas.' />
        @endforelse
    </div>
</section>
@endsection
