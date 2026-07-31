<?php

namespace App\Actions\Consultations;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\User;
use App\Services\CodeGenerator;
use App\Services\DateTimeService;
use App\Services\PrivateConsultationAttachment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreateConsultation
{
    public function __construct(
        private CodeGenerator $codes,
        private DateTimeService $dates,
        private PrivateConsultationAttachment $attachments,
    ) {}

    public function execute(
        array $data,
        ?UploadedFile $attachment = null,
        ?User $customer = null,
        string $source = 'public_website',
    ): Consultation {
        $fingerprint = hash('sha256', implode('|', [
            $data['email'], $data['phone'], $data['service_id'], mb_strtolower($data['project_title']),
            hash('sha256', $data['description']), $source, $customer?->id ?? 'guest', now()->format('Y-m-d-H'),
        ]));

        if ($existing = Consultation::query()->where('submission_fingerprint', $fingerprint)->first()) {
            return $existing;
        }

        $fileMetadata = $attachment ? $this->attachments->store($attachment) : [];

        try {
            return DB::transaction(function () use ($data, $fileMetadata, $fingerprint, $customer, $source): Consultation {
                return Consultation::query()->forceCreate(array_merge([
                    'user_id' => $customer?->id,
                    'request_code' => $this->nextAvailableRequestCode(),
                    'submission_fingerprint' => $fingerprint,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'service_id' => $data['service_id'],
                    'project_title' => $data['project_title'],
                    'description' => $data['description'],
                    'deadline' => isset($data['deadline']) && $data['deadline'] ? $this->dates->fromUserInput($data['deadline'], true) : null,
                    'technology' => $data['technology'] ?? null,
                    'budget' => $data['budget'] ?? null,
                    'status' => ConsultationStatus::New,
                    'privacy_accepted_at' => now(),
                    'academic_integrity_accepted_at' => now(),
                    'privacy_policy_version' => config('jokiinlah.privacy_policy_version'),
                    'terms_version' => config('jokiinlah.terms_version'),
                    'source' => $source,
                    'retention_until' => now()->addDays((int) config('jokiinlah.default_retention_days')),
                ], $fileMetadata));
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $this->attachments->delete($fileMetadata['attachment_path'] ?? null);

            $existing = Consultation::query()->where('submission_fingerprint', $fingerprint)->first();

            if ($existing) {
                return $existing;
            }

            throw $exception;
        } catch (Throwable $exception) {
            $this->attachments->delete($fileMetadata['attachment_path'] ?? null);
            throw $exception;
        }
    }

    private function nextAvailableRequestCode(): string
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $code = $this->codes->generate('consultation', config('jokiinlah.consultation_code_prefix'));

            if (! Consultation::withTrashed()->where('request_code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Nomor konsultasi tidak dapat dibuat.');
    }
}
