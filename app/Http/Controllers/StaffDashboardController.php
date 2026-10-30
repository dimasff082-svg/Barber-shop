<?php

namespace App\Http\Controllers;

use App\Services\Staff\OperationsReadModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffDashboardController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        return to_route($request->user()->dashboardRouteName());
    }

    public function admin(Request $request, OperationsReadModel $read): Response
    {
        return Inertia::render('admin/dashboard', $read->dashboard($request->user()));
    }

    public function barber(Request $request, OperationsReadModel $read): Response
    {
        return Inertia::render('barber/dashboard', $read->dashboard($request->user()));
    }
}
