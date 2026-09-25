<?php

namespace App\Http\Controllers\Plataforma;

use App\Actions\Plataforma\GetPlatformStats;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the platform administrator's dashboard.
     */
    public function __invoke(GetPlatformStats $platformStats): Response
    {
        return Inertia::render('plataforma/dashboard', $platformStats->handle());
    }
}
