<?php

namespace App\Actions\Consultations;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RejectCustomerProjectRequest
{
    public function __construct(private UpdateCustomerProjectRequestStatus $status) {}

    public function execute(Consultation $consultation, User $actor, string $reason): Consultation
    {
        if (! in_array($consultation->status, [ConsultationStatus::New, ConsultationStatus::Contacted, ConsultationStatus::Reviewed], true)) {
            throw ValidationException::withMessages(['consultation' => 'Status permintaan tidak dapat ditolak.']);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['rejection_reason' => 'Alasan penolakan wajib diisi.']);
        }

        return $this->status->execute(
            $consultation,
            $actor,
            ConsultationStatus::Cancelled,
            'consultation.rejected',
            null,
            $reason,
        );
    }
}
