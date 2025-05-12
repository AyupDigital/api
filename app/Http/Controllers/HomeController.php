<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect(config('local.backend_uri'));
    }
}
