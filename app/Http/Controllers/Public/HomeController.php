<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Portfolio;
use App\Models\Service;
use App\Models\Testimonial;
use App\Services\WhatsAppUrlBuilder;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(WhatsAppUrlBuilder $whatsApp): View
    {
        $testimonials = Testimonial::query()->published()
            ->when(app()->environment('production'), fn ($query) => $query->where('is_demo', false))
            ->latest()->limit(5)->get();

        return view('public.home', [
            'services' => Service::query()->active()->orderBy('sort_order')->limit(8)->get(),
            'portfolios' => Portfolio::query()->published()->latest()->limit(6)->get(),
            'testimonials' => $testimonials,
            'faqs' => Faq::query()->active()->orderBy('sort_order')->limit(8)->get(),
            'whatsAppUrl' => $whatsApp->build('Halo, saya ingin berkonsultasi mengenai layanan di Jokiinlah.'),
        ]);
    }
}
