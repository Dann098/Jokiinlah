<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->user() && ($request->user()->isAdmin() || $request->user()->isStaff())) {
            $request->session()->put('security.two_factor_passed_user', $request->user()->getKey());
        }

        $destination = $request->user()->isCustomer()
            ? route('customer.dashboard')
            : route('filament.admin.pages.dashboard');

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->intended($destination);
    }
}
