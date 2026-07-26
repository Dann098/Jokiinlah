<?php

namespace App\Http\Controllers\Public;

use App\Enums\ArticleCategory;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\ReadingTime;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('q')), 0, 100);
        $category = ArticleCategory::tryFrom((string) $request->query('category'));
        $articles = Article::query()->published()->with('author')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('title', 'like', '%'.$search.'%')->orWhere('excerpt', 'like', '%'.$search.'%')))
            ->when($category, fn ($query) => $query->where('category', $category->value))
            ->latest('published_at')->paginate(9)->withQueryString();

        return view('public.articles.index', compact('articles', 'search', 'category'));
    }

    public function show(Article $article, ReadingTime $readingTime): View
    {
        abort_unless($article->is_published && $article->published_at?->isPast(), 404);
        $article->loadMissing('author');
        $related = Article::query()->published()->where('category', $article->category->value)
            ->whereKeyNot($article->id)->latest('published_at')->limit(3)->get();

        return view('public.articles.show', [
            'article' => $article,
            'readingMinutes' => $readingTime->minutes($article->content),
            'related' => $related,
        ]);
    }
}
