<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->canAccessStaffArea(), 403);

        // A destination from a previous guest request may belong to another role.
        $request->session()->forget('url.intended');

        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : to_route($user->dashboardRouteName());
    }
}
