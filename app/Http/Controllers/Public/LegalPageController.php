<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class LegalPageController extends Controller
{
    public function privacy(): View
    {
        return view('public.legal.privacy', ['version' => config('jokiinlah.privacy_policy_version')]);
    }

    public function terms(): View
    {
        return view('public.legal.terms', ['version' => config('jokiinlah.terms_version')]);
    }
}
