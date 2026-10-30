<?php

namespace App\Http\Controllers\Realtime;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

class BroadcastAuthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(config('broadcasting.default') === 'reverb', 503, 'Realtime belum diaktifkan.');

        $request->validate([
            'socket_id' => ['required', 'string', 'max:100', 'regex:/^\\d+\\.\\d+$/'],
            'channel_name' => ['required', 'string', 'max:100', 'regex:/^private-staff\\.(admin|barber\\.[1-9]\\d*)$/'],
        ]);

        return response()->json(Broadcast::auth($request));
    }
}
