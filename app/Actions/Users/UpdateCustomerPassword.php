<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UpdateCustomerPassword
{
    public function __construct(private ActivityLogger $logger) {}

    public function execute(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password): void {
            $user->forceFill(['password' => Hash::make($password)])->save();

            $this->logger->log(
                'customer.password_updated',
                'Pelanggan memperbarui kata sandi.',
                $user,
                $user,
            );
        });
    }
}
