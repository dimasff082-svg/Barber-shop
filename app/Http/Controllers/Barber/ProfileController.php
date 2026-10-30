<?php

namespace App\Http\Controllers\Barber;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $barber = $request->user()->barber;

        return Inertia::render('barber/profile', ['timezone' => (string) config('barberflow.timezone'), 'barber' => $barber === null ? null : [
            'name' => $barber->name, 'bio' => $barber->bio, 'specialization' => $barber->specialization,
            'queue_prefix' => $barber->queue_prefix, 'accepts_queue' => $barber->accepts_queue,
            'schedules' => $barber->schedules()->orderBy('weekday')->get()->map(fn ($schedule) => [
                'weekday' => $schedule->weekday, 'starts_at' => substr($schedule->starts_at, 0, 5),
                'ends_at' => substr($schedule->ends_at, 0, 5), 'is_active' => $schedule->is_active,
            ])->all(),
        ]]);
    }
}
