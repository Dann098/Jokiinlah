<?php

namespace App\Actions\Projects;

use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CodeGenerator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateManualProject
{
    public function __construct(private CodeGenerator $codes, private ActivityLogger $logger) {}

    public function execute(array $data, User $actor): Project
    {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Hanya admin dapat membuat proyek.');
        }

        $customer = User::query()->findOrFail($data['customer_id']);
        $staff = filled($data['assigned_staff_id'] ?? null) ? User::query()->find($data['assigned_staff_id']) : null;

        if (! $customer->isCustomer() || ! $customer->is_active) {
            throw ValidationException::withMessages(['customer_id' => 'Customer harus aktif dan memiliki role customer.']);
        }
        if ($staff && (! $staff->isStaff() || ! $staff->is_active)) {
            throw ValidationException::withMessages(['assigned_staff_id' => 'Staff harus aktif dan memiliki role staff.']);
        }

        return DB::transaction(function () use ($data, $actor, $customer, $staff): Project {
            $project = Project::query()->forceCreate([
                'customer_id' => $customer->id,
                'assigned_staff_id' => $staff?->id,
                'service_id' => $data['service_id'],
                'project_code' => $this->codes->generate('project', config('jokiinlah.project_code_prefix')),
                'title' => trim($data['title']),
                'description' => trim($data['description']),
                'status' => ProjectStatus::NewRequest,
                'progress' => 0,
                'start_date' => $data['start_date'] ?? null,
                'deadline' => $data['deadline'] ?? null,
                'payment_status' => PaymentStatus::Unpaid,
                'retention_until' => now()->addDays((int) config('jokiinlah.default_retention_days')),
            ]);

            $this->logger->log('project.created', 'Admin membuat proyek manual.', $actor, $project, [
                'customer_id' => $customer->id,
                'assigned_staff_id' => $staff?->id,
            ]);

            return $project;
        });
    }
}
