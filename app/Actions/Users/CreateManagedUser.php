<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class CreateManagedUser
{
    public function __construct(private ActivityLogger $logger) {}

    public function execute(array $data, UserRole $role, User $actor): User
    {
        if (! $actor->isAdmin() || ! in_array($role, [UserRole::Customer, UserRole::Staff], true)) {
            throw new AuthorizationException('Anda tidak berwenang membuat akun ini.');
        }

        $user = DB::transaction(function () use ($data, $role, $actor): User {
            $user = User::query()->forceCreate([
                'name' => trim($data['name']),
                'email' => mb_strtolower(trim($data['email'])),
                'phone' => $data['phone'] ?? null,
                'institution' => $data['institution'] ?? null,
                'study_program' => $data['study_program'] ?? null,
                'password' => Hash::make(Str::password(48)),
                'role' => $role,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'email_verified_at' => null,
            ]);

            $this->logger->log(
                'user.created',
                'Admin membuat akun operasional melalui alur undangan aman.',
                $actor,
                $user,
                ['role' => $role->value],
            );

            return $user;
        });

        Password::broker()->sendResetLink(['email' => $user->email]);
        $user->sendEmailVerificationNotification();

        return $user;
    }
}
