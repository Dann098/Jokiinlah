<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $destination = $request->user()->isCustomer()
            ? route('customer.dashboard')
            : route('filament.admin.pages.dashboard');

        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->intended($destination);
    }
}
