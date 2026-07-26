@extends('layouts.customer')
@section('title', $revision->title)
@section('content')
<x-customer.breadcrumb :items="[
    ['label' => 'Proyek Saya', 'url' => route('customer.projects.index')],
    ['label' => $project->project_code, 'url' => route('customer.projects.show', $project)],
    ['label' => 'Revisi', 'url' => route('customer.projects.revisions.index', $project)],
    ['label' => '#'.$revision->id],
]" />
<x-customer.page-heading :eyebrow="'Revisi #'.$revision->id" :title='$revision->title' description='Detail permintaan revisi dan status peninjauan oleh tim.' />

<article class='surface-card p-5 sm:p-7'>
    <div class='flex flex-wrap items-center justify-between gap-3'>
        <x-customer.status-badge :status='$revision->status' />
        <p class='text-sm text-muted'>Dikirim <x-customer.date :value='$revision->created_at' /> WIB</p>
    </div>
    <dl class='mt-6 grid gap-4 text-sm sm:grid-cols-3'>
        <div><dt class='text-muted'>Proyek</dt><dd class='mt-1 font-bold text-navy'>{{ $project->project_code }}</dd></div>
        <div><dt class='text-muted'>Pengirim</dt><dd class='mt-1 font-bold text-navy'>{{ $revision->submitter?->name ?? 'Akun tidak tersedia' }}</dd></div>
        <div><dt class='text-muted'>Bagian</dt><dd class='mt-1 font-bold text-navy'>{{ $revision->section_reference ?: 'Tidak ditentukan' }}</dd></div>
    </dl>
    <section class='mt-7 border-t border-navy/10 pt-6' aria-labelledby='revision-description-title'>
        <h2 id='revision-description-title' class='text-xl font-bold text-navy'>Deskripsi kebutuhan</h2>
        <p class='safe-content mt-3 whitespace-pre-line text-sm leading-7 text-muted'>{{ $revision->description }}</p>
    </section>
    @if($revision->admin_response)
        <section class='mt-7 rounded-xl bg-cream p-5' aria-labelledby='revision-response-title'>
            <h2 id='revision-response-title' class='text-xl font-bold text-navy'>Tanggapan tim</h2>
            <p class='safe-content mt-3 whitespace-pre-line text-sm leading-7 text-muted'>{{ $revision->admin_response }}</p>
        </section>
    @endif
    @if($revision->attachment_path)
        <section class='mt-7 border-t border-navy/10 pt-6' aria-labelledby='revision-attachment-title'>
            <h2 id='revision-attachment-title' class='text-xl font-bold text-navy'>Lampiran private</h2>
            <p class='safe-content mt-2 text-sm text-muted'>{{ $revision->attachment_original_name }} · <x-customer.file-size :bytes='$revision->attachment_size' /></p>
            <a href='{{ route('customer.projects.revisions.attachment', [$project, $revision]) }}' class='mt-4 inline-flex min-h-11 items-center rounded-xl bg-navy px-5 text-sm font-bold text-white'>Unduh lampiran</a>
        </section>
    @endif
</article>
@endsection
