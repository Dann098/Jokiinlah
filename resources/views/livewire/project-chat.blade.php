<section id='project-chat' wire:poll.7s='refreshMessages' class='surface-card mt-6 overflow-hidden' aria-labelledby='project-chat-title'>
    <div class='border-b border-navy/10 p-5 sm:p-7'>
        <p class='text-xs font-bold uppercase tracking-[0.16em] text-rose'>Percakapan proyek</p>
        <h2 id='project-chat-title' class='mt-1 text-2xl font-bold text-navy'>Chat</h2>
        <p class='mt-2 text-sm text-muted'>Percakapan hanya dapat dilihat customer pemilik, staff yang ditugaskan, dan admin.</p>
    </div>

    <div class='max-h-[32rem] overflow-y-auto bg-cream/40 p-4 sm:p-6'>
        @if($hasOlder)
            <div class='mb-4 text-center'>
                <button type='button' wire:click='loadOlder' class='min-h-11 rounded-xl border border-navy/20 bg-white px-4 text-sm font-bold text-navy'>Muat pesan sebelumnya</button>
            </div>
        @endif

        <ol aria-live="polite" aria-relevant="additions" class='space-y-4'>
            @forelse($messages as $chatMessage)
                @php($ownMessage = $chatMessage->sender_id === auth()->id())
                <li class='flex {{ $ownMessage ? 'justify-end' : 'justify-start' }}'>
                    <article class='max-w-[88%] rounded-2xl px-4 py-3 sm:max-w-[75%] {{ $ownMessage ? 'bg-navy text-white' : 'border border-navy/10 bg-white text-charcoal' }}'>
                        <header class='flex flex-wrap items-baseline gap-x-2 text-xs {{ $ownMessage ? 'text-white/70' : 'text-muted' }}'>
                            <span class='font-bold'>{{ $chatMessage->sender?->name ?? 'Pengguna nonaktif' }}</span>
                            <time datetime='{{ $chatMessage->created_at->toIso8601String() }}'>{{ $chatMessage->created_at->timezone(config('jokiinlah.display_timezone'))->format('d M Y H:i') }} WIB</time>
                        </header>
                        <p class='mt-2 whitespace-pre-wrap break-words text-sm leading-6'>{{ $chatMessage->message }}</p>
                    </article>
                </li>
            @empty
                <li class='rounded-xl border border-dashed border-navy/20 bg-white p-6 text-center text-sm text-muted'>Belum ada pesan. Mulai percakapan untuk proyek ini.</li>
            @endforelse
        </ol>
    </div>

    @if($canSend)
        <form wire:submit='send' class='border-t border-navy/10 bg-white p-5 sm:p-6'>
            <label for='project-chat-message' class='font-bold text-navy'>Pesan</label>
            <textarea id='project-chat-message' wire:model='message' rows='4' maxlength='2000' required aria-describedby='project-chat-help project-chat-error' class='mt-2 w-full rounded-xl border border-navy/20 p-4 focus:border-gold focus:outline-none focus:ring-2 focus:ring-gold/30'></textarea>
            <div class='mt-2 flex flex-wrap items-center justify-between gap-2'>
                <p id='project-chat-help' class='text-xs text-muted'>Maksimum 2.000 karakter. Gunakan modul File Proyek untuk dokumen.</p>
                <span class='text-xs text-muted'>{{ mb_strlen($message) }}/2000</span>
            </div>
            @error('message')<p id='project-chat-error' role='alert' class='mt-2 text-sm font-semibold text-red-700'>{{ $message }}</p>@enderror
            <button type='submit' wire:loading.attr='disabled' class='mt-4 min-h-11 rounded-xl bg-navy px-5 font-bold text-white disabled:opacity-60'>
                <span wire:loading.remove wire:target='send'>Kirim pesan</span>
                <span wire:loading wire:target='send'>Mengirim…</span>
            </button>
        </form>
    @else
        <div class='border-t border-amber-200 bg-amber-50 p-5 text-sm font-semibold text-amber-950'>Proyek yang dibatalkan tetap dapat dibaca, tetapi tidak menerima pesan baru.</div>
    @endif
</section>
