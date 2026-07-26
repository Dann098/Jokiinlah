<?php

namespace App\Actions\Projects;

use App\Enums\PaymentStatus;
use App\Models\Project;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class UpdatePaymentStatus
{
    public function __construct(private ActivityLogger $logger) {}

    public function execute(Project $project, PaymentStatus $status, ?string $note, User $actor): Project
    {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Hanya admin dapat memperbarui status pembayaran.');
        }

        return DB::transaction(function () use ($project, $status, $note, $actor): Project {
            $before = $project->payment_status;
            $project->forceFill([
                'payment_status' => $status,
                'payment_note' => filled($note) ? trim((string) $note) : null,
                'payment_updated_at' => now(),
            ])->save();

            $this->logger->log('project.payment_status_changed', 'Status pembayaran manual diperbarui.', $actor, $project, [
                'before' => $before->value,
                'after' => $status->value,
                'has_note' => filled($note),
            ]);

            return $project->refresh();
        });
    }
}
