<?php

namespace App\Http\Controllers\Customer;

use App\Actions\Users\UpdateCustomerPassword;
use App\Actions\Users\UpdateCustomerProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCustomerPasswordRequest;
use App\Http\Requests\UpdateCustomerProfileRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('customer.profile.edit', ['user' => $request->user()]);
    }

    public function update(UpdateCustomerProfileRequest $request, UpdateCustomerProfile $action): RedirectResponse
    {
        $action->execute($request->user(), $request->validated());

        return back()->with('status', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(
        UpdateCustomerPasswordRequest $request,
        UpdateCustomerPassword $action,
    ): RedirectResponse {
        $action->execute($request->user(), $request->validated('password'));

        return back()->with('status', 'Kata sandi berhasil diperbarui.');
    }
}
