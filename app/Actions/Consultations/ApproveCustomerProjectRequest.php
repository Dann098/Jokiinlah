<?php

namespace App\Actions\Consultations;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ApproveCustomerProjectRequest
{
    public function __construct(private UpdateCustomerProjectRequestStatus $status) {}

    public function execute(Consultation $consultation, User $actor, ?string $response = null): Consultation
    {
        if (! in_array($consultation->status, [ConsultationStatus::New, ConsultationStatus::Contacted], true)) {
            throw ValidationException::withMessages(['consultation' => 'Status permintaan tidak dapat disetujui.']);
        }

        return $this->status->execute(
            $consultation,
            $actor,
            ConsultationStatus::Reviewed,
            'consultation.approved',
            filled($response) ? trim($response) : null,
        );
    }
}
