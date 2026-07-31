<?php

namespace App\Actions\Consultations;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ProjectRequestNotifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateCustomerProjectRequestStatus
{
    public function __construct(
        private ActivityLogger $logger,
        private ProjectRequestNotifier $notifier,
    ) {}

    public function execute(
        Consultation $consultation,
        User $actor,
        ConsultationStatus $status,
        string $action,
        ?string $response = null,
        ?string $rejectionReason = null,
    ): Consultation {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Hanya admin dapat menindaklanjuti permintaan proyek.');
        }

        if ($consultation->status === ConsultationStatus::Converted || $consultation->project()->exists()) {
            throw ValidationException::withMessages(['consultation' => 'Permintaan yang sudah dikonversi tidak dapat diubah.']);
        }

        $updated = DB::transaction(function () use ($consultation, $actor, $status, $action, $response, $rejectionReason): Consultation {
            $consultation->forceFill([
                'status' => $status,
                'customer_response' => $response,
                'rejection_reason' => $rejectionReason,
                'responded_at' => now(),
            ])->save();

            $this->logger->log($action, 'Status permintaan proyek diperbarui.', $actor, $consultation, [
                'status' => $status->value,
            ]);

            return $consultation->refresh();
        });

        $updated->loadMissing('user');
        $this->notifier->notifyCustomer($updated);

        return $updated;
    }
}
