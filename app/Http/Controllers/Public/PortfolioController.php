<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use App\Services\WhatsAppUrlBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{
    public function index(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('q')), 0, 100);
        $category = mb_substr(trim((string) $request->query('category')), 0, 50);
        $query = Portfolio::query()->published();
        $categories = (clone $query)->select('category')->distinct()->orderBy('category')->pluck('category');
        $portfolios = $query
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('title', 'like', '%'.$search.'%')->orWhere('description', 'like', '%'.$search.'%')))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->latest()->paginate(9)->withQueryString();

        return view('public.portfolios.index', compact('portfolios', 'categories', 'search', 'category'));
    }

    public function show(Portfolio $portfolio, WhatsAppUrlBuilder $whatsApp): View
    {
        abort_unless($portfolio->is_published, 404);

        return view('public.portfolios.show', [
            'portfolio' => $portfolio,
            'whatsAppUrl' => $whatsApp->build('Halo, saya ingin berkonsultasi mengenai solusi seperti '.$portfolio->title.'.'),
        ]);
    }
}
