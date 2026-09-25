<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($request->user()->is_platform_admin) {
            return to_route('plataforma.dashboard');
        }

        return Inertia::render('dashboard');
    }
}
