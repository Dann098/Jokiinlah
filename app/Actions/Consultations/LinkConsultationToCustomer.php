<?php

namespace App\Actions\Consultations;

use App\Models\Consultation;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LinkConsultationToCustomer
{
    public function __construct(private ActivityLogger $logger) {}

    public function execute(Consultation $consultation, User $customer, User $actor): Consultation
    {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Hanya admin dapat menghubungkan konsultasi.');
        }

        if ($consultation->user_id !== null) {
            throw ValidationException::withMessages(['consultation' => 'Konsultasi sudah terhubung dan tidak dapat dipindahkan.']);
        }

        if (! $customer->isCustomer() || ! $customer->hasVerifiedEmail() || strcasecmp($consultation->email, $customer->email) !== 0) {
            throw ValidationException::withMessages(['customer' => 'Akun pelanggan harus terverifikasi dan menggunakan email konsultasi yang sama.']);
        }

        return DB::transaction(function () use ($consultation, $customer, $actor): Consultation {
            $consultation->forceFill(['user_id' => $customer->id])->save();
            $this->logger->log('consultation.customer_linked', 'Konsultasi dihubungkan ke akun pelanggan terverifikasi.', $actor, $consultation, ['customer_id' => $customer->id]);

            return $consultation->refresh();
        });
    }
}
