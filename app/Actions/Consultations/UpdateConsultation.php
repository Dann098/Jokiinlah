<?php

namespace App\Actions\Consultations;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateConsultation
{
    public function __construct(private ActivityLogger $logger) {}

    public function execute(Consultation $consultation, ConsultationStatus $status, ?string $note, User $actor): Consultation
    {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Hanya admin dapat memperbarui konsultasi.');
        }
        if ($consultation->status !== $status && ! $consultation->status->canTransitionTo($status)) {
            throw ValidationException::withMessages(['status' => 'Transisi status konsultasi tidak valid.']);
        }

        return DB::transaction(function () use ($consultation, $status, $note, $actor): Consultation {
            $before = $consultation->status;
            $consultation->forceFill([
                'status' => $status,
                'admin_note' => filled($note) ? trim((string) $note) : null,
                'archived_at' => in_array($status, [ConsultationStatus::Closed, ConsultationStatus::Cancelled], true) ? now() : null,
            ])->save();

            $this->logger->log('consultation.updated', 'Status konsultasi diperbarui.', $actor, $consultation, [
                'before' => $before->value,
                'after' => $status->value,
                'has_admin_note' => filled($note),
            ]);

            return $consultation->refresh();
        });
    }
}
