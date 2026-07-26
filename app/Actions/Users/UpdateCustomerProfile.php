<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

class UpdateCustomerProfile
{
    public function __construct(private ActivityLogger $logger) {}

    public function execute(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $before = ['name' => $user->name, 'phone' => $user->phone];

            $user->forceFill([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
            ])->save();

            $this->logger->log(
                'customer.profile_updated',
                'Pelanggan memperbarui profil.',
                $user,
                $user,
                ['changed_fields' => array_keys(array_diff_assoc(
                    ['name' => $user->name, 'phone' => $user->phone],
                    $before,
                ))],
            );

            return $user;
        });
    }
}
