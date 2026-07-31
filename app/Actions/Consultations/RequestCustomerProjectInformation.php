<?php

namespace App\Actions\Consultations;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RequestCustomerProjectInformation
{
    public function __construct(private UpdateCustomerProjectRequestStatus $status) {}

    public function execute(Consultation $consultation, User $actor, string $response): Consultation
    {
        if (! in_array($consultation->status, [ConsultationStatus::New, ConsultationStatus::Contacted], true)) {
            throw ValidationException::withMessages(['consultation' => 'Status permintaan tidak dapat meminta informasi tambahan.']);
        }

        $response = trim($response);
        if ($response === '') {
            throw ValidationException::withMessages(['customer_response' => 'Informasi yang diminta wajib dijelaskan.']);
        }

        return $this->status->execute(
            $consultation,
            $actor,
            ConsultationStatus::Contacted,
            'consultation.information_requested',
            $response,
        );
    }
}
