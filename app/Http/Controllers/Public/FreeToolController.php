<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class FreeToolController extends Controller
{
    public function index(): View
    {
        return view('public.free-tools.index');
    }

    public function cvBuilder(): View
    {
        return view('public.free-tools.cv-builder');
    }
}
