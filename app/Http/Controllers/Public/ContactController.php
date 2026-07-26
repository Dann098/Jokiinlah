<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\WhatsAppUrlBuilder;
use Illuminate\Contracts\View\View;

class ContactController extends Controller
{
    public function __invoke(WhatsAppUrlBuilder $whatsApp): View
    {
        return view('public.contact', [
            'services' => Service::query()->active()->orderBy('sort_order')->get(),
            'whatsAppUrl' => $whatsApp->build('Halo, saya ingin berkonsultasi mengenai layanan di Jokiinlah.'),
        ]);
    }
}
