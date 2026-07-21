<?php

namespace Tests\Unit;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Services\DateTimeService;
use Tests\TestCase;

class EnumAndTimeTest extends TestCase
{
    public function test_enum_values_are_stable_and_have_indonesian_labels(): void
    {
        $this->assertSame('customer', UserRole::Customer->value);
        $this->assertSame('Pelanggan', UserRole::Customer->label());
        $this->assertSame('Selesai', ProjectStatus::Completed->label());
    }

    public function test_project_status_normal_transitions_are_enforced(): void
    {
        $this->assertTrue(ProjectStatus::NewRequest->canTransitionTo(ProjectStatus::Consultation));
        $this->assertTrue(ProjectStatus::CustomerReview->canTransitionTo(ProjectStatus::Revision));
        $this->assertFalse(ProjectStatus::InProgress->canTransitionTo(ProjectStatus::Completed));
        $this->assertFalse(ProjectStatus::Completed->canTransitionTo(ProjectStatus::InProgress));
    }

    public function test_jakarta_input_is_converted_to_utc_and_back(): void
    {
        $service = new DateTimeService;
        $utc = $service->fromUserInput('2026-07-22 10:00');

        $this->assertSame('2026-07-22 03:00:00', $utc->format('Y-m-d H:i:s'));
        $this->assertSame('22 Jul 2026 10:00', $service->forDisplay($utc));
        $this->assertSame('16:59:59', $service->fromUserInput('2026-07-22', true)->format('H:i:s'));
    }
}
