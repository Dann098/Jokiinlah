@if(session('status'))
<div role='status' aria-live='polite' class='border-b border-green-200 bg-green-50 text-green-900'><div class='container-public py-4'><p class='font-bold'>{{ session('status') }}</p>@if(session('consultation_code'))<p class='mt-1 text-sm'>Nomor konsultasi: <strong>{{ session('consultation_code') }}</strong></p>@endif @if(session('consultation_whatsapp'))<a class='mt-3 inline-flex min-h-11 items-center font-bold underline' href='{{ session('consultation_whatsapp') }}' target='_blank' rel='noopener noreferrer'>Lanjutkan melalui WhatsApp</a>@endif</div></div>
@endif
