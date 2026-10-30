<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'realtime' => fn () => $this->realtime($request),
            'flash' => [
                'success' => fn () => is_string($request->session()->get('success')) ? $request->session()->get('success') : null,
                'error' => fn () => is_string($request->session()->get('error')) ? $request->session()->get('error') : null,
            ],
        ];
    }

    /** @return array{enabled: bool, key: string, host: string, port: int, scheme: string, channel: string|null} */
    private function realtime(Request $request): array
    {
        $key = (string) config('broadcasting.connections.reverb.key', '');
        $host = (string) config('broadcasting.connections.reverb.options.host', '');
        $scheme = (string) config('broadcasting.connections.reverb.options.scheme', 'https');
        $user = $request->user();
        $channel = null;
        if ($user?->is_active) {
            if ($user->isAdmin()) {
                $channel = 'staff.admin';
            } elseif ($user->isBarber() && $user->barber?->is_active) {
                $channel = 'staff.barber.'.$user->barber->id;
            }
        }

        return [
            'enabled' => config('broadcasting.default') === 'reverb' && $key !== '' && $host !== '',
            'key' => $key, 'host' => $host, 'scheme' => $scheme,
            'port' => (int) config('broadcasting.connections.reverb.options.port', $scheme === 'https' ? 443 : 80),
            'channel' => $channel,
        ];
    }
}
