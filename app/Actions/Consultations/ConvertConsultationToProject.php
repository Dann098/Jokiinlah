<?php

namespace App\Actions\Consultations;

use App\Enums\ConsultationStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Models\Consultation;
use App\Models\Project;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CodeGenerator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConvertConsultationToProject
{
    public function __construct(private CodeGenerator $codes, private ActivityLogger $logger) {}

    public function execute(Consultation $consultation, User $actor): Project
    {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Hanya admin dapat mengonversi konsultasi.');
        }

        $consultation->loadMissing('user');
        if ($consultation->status !== ConsultationStatus::Reviewed || ! $consultation->user?->hasVerifiedEmail() || ! $consultation->user->isCustomer()) {
            throw ValidationException::withMessages(['consultation' => 'Konsultasi harus ditinjau dan terhubung ke pelanggan terverifikasi.']);
        }
        if ($consultation->project()->exists()) {
            throw ValidationException::withMessages(['consultation' => 'Konsultasi ini sudah dikonversi.']);
        }
        if ($consultation->service_id === null) {
            throw ValidationException::withMessages(['service_id' => 'Layanan wajib dipilih sebelum konversi.']);
        }

        return DB::transaction(function () use ($consultation, $actor): Project {
            $project = Project::query()->forceCreate([
                'consultation_id' => $consultation->id,
                'customer_id' => $consultation->user_id,
                'service_id' => $consultation->service_id,
                'project_code' => $this->codes->generate('project', config('jokiinlah.project_code_prefix')),
                'title' => $consultation->project_title,
                'description' => $consultation->description,
                'status' => ProjectStatus::NewRequest,
                'progress' => 0,
                'deadline' => $consultation->deadline,
                'payment_status' => PaymentStatus::Unpaid,
                'retention_until' => now()->addDays((int) config('jokiinlah.default_retention_days')),
            ]);

            $consultation->forceFill(['status' => ConsultationStatus::Converted])->save();
            $this->logger->log('consultation.converted', 'Konsultasi dikonversi menjadi proyek.', $actor, $consultation, ['project_id' => $project->id, 'project_code' => $project->project_code]);

            return $project;
        });
    }
}
