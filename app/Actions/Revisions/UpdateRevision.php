<?php

namespace App\Actions\Revisions;

use App\Enums\RevisionPriority;
use App\Enums\RevisionStatus;
use App\Models\Revision;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateRevision
{
    public function __construct(private ActivityLogger $logger) {}

    public function execute(Revision $revision, array $data, User $actor): Revision
    {
        $assigned = $actor->isStaff() && $revision->project?->assigned_staff_id === $actor->id;
        if (! $actor->isAdmin() && ! $assigned) {
            throw new AuthorizationException('Anda tidak berwenang memperbarui revisi ini.');
        }

        $status = RevisionStatus::from($data['status']);
        if ($revision->status !== $status && ! $revision->status->canTransitionTo($status)) {
            throw ValidationException::withMessages(['status' => 'Transisi status revisi tidak valid.']);
        }

        return DB::transaction(function () use ($revision, $data, $status, $actor): Revision {
            $before = $revision->status;
            $revision->forceFill([
                'status' => $status,
                'priority' => RevisionPriority::from($data['priority']),
                'admin_response' => filled($data['admin_response'] ?? null) ? trim($data['admin_response']) : null,
                'internal_note' => filled($data['internal_note'] ?? null) ? trim($data['internal_note']) : null,
                'completed_at' => in_array($status, [RevisionStatus::Approved, RevisionStatus::Closed], true) ? now() : null,
            ])->save();

            $this->logger->log('revision.updated', 'Revisi ditanggapi oleh tim operasional.', $actor, $revision, [
                'before' => $before->value,
                'after' => $status->value,
                'has_public_response' => filled($data['admin_response'] ?? null),
                'has_internal_note' => filled($data['internal_note'] ?? null),
            ]);

            return $revision->refresh();
        });
    }
}
