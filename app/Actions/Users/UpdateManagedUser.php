<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateManagedUser
{
    public function __construct(private ActivityLogger $logger) {}

    public function execute(User $subject, array $data, UserRole $expectedRole, User $actor): User
    {
        if (! $actor->isAdmin() || $subject->role !== $expectedRole) {
            throw new AuthorizationException('Anda tidak berwenang memperbarui akun ini.');
        }
        if ($subject->is($actor) && array_key_exists('is_active', $data) && ! $data['is_active']) {
            throw ValidationException::withMessages(['is_active' => 'Admin tidak dapat menonaktifkan akunnya sendiri.']);
        }

        return DB::transaction(function () use ($subject, $data, $actor): User {
            $beforeEmail = $subject->email;
            $subject->forceFill([
                'name' => trim($data['name']),
                'email' => mb_strtolower(trim($data['email'])),
                'phone' => $data['phone'] ?? null,
                'institution' => $data['institution'] ?? null,
                'study_program' => $data['study_program'] ?? null,
                'is_active' => (bool) $data['is_active'],
                'email_verified_at' => mb_strtolower(trim($data['email'])) === $beforeEmail
                    ? $subject->email_verified_at
                    : null,
            ])->save();

            $this->logger->log(
                'user.updated',
                'Admin memperbarui data akun yang di-whitelist.',
                $actor,
                $subject,
                ['changed_fields' => array_values(array_diff(array_keys($subject->getChanges()), ['updated_at']))],
            );

            return $subject->refresh();
        });
    }
}
