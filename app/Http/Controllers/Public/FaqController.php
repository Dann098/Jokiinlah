<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('q')), 0, 100);
        $faqs = Faq::query()->active()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('question', 'like', '%'.$search.'%')->orWhere('answer', 'like', '%'.$search.'%')))
            ->orderBy('category')->orderBy('sort_order')->get();

        return view('public.faq', ['faqGroups' => $faqs->groupBy(fn (Faq $faq) => $faq->category ?: 'Umum'), 'search' => $search]);
    }
}
