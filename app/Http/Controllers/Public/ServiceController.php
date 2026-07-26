<?php

namespace App\Http\Controllers\Public;

use App\Enums\ServiceCategory;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\WhatsAppUrlBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('q')), 0, 100);
        $category = ServiceCategory::tryFrom((string) $request->query('category'));
        $services = Service::query()->active()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('short_description', 'like', '%'.$search.'%')))
            ->when($category, fn ($query) => $query->where('category', $category->value))
            ->orderBy('sort_order')->paginate(9)->withQueryString();

        return view('public.services.index', compact('services', 'search', 'category'));
    }

    public function show(Service $service, WhatsAppUrlBuilder $whatsApp): View
    {
        abort_unless($service->is_active, 404);

        return view('public.services.show', [
            'service' => $service,
            'faqs' => $service->faqs()->active()->orderBy('sort_order')->get(),
            'whatsAppUrl' => $whatsApp->build('Halo, saya ingin berkonsultasi mengenai layanan '.$service->name.'.'),
        ]);
    }
}
