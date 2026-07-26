<?php

namespace App\Http\Controllers\Customer;

use App\Enums\AppointmentStatus;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectMilestone;
use App\Models\Reminder;
use App\Models\Revision;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $projects = Project::query()->ownedBy($user);

        $summary = [
            'active' => (clone $projects)->whereNotIn('status', [
                ProjectStatus::Completed->value,
                ProjectStatus::Cancelled->value,
            ])->count(),
            'completed' => (clone $projects)->where('status', ProjectStatus::Completed->value)->count(),
            'waiting_data' => (clone $projects)->where('status', ProjectStatus::WaitingData->value)->count(),
            'customer_review' => (clone $projects)->where('status', ProjectStatus::CustomerReview->value)->count(),
        ];

        $recentProjects = (clone $projects)
            ->with('service:id,name')
            ->latest('updated_at')
            ->limit(4)
            ->get();

        $actionProjects = (clone $projects)
            ->whereIn('status', [
                ProjectStatus::WaitingData->value,
                ProjectStatus::CustomerReview->value,
                ProjectStatus::Revision->value,
            ])
            ->with('service:id,name')
            ->latest('updated_at')
            ->limit(4)
            ->get();

        $upcomingAppointments = Appointment::query()
            ->where('customer_id', $user->id)
            ->whereHas('project', fn ($query) => $query->where('customer_id', $user->id))
            ->whereIn('status', [AppointmentStatus::Scheduled->value, AppointmentStatus::Confirmed->value])
            ->where('appointment_date', '>=', now())
            ->with('project:id,project_code,title')
            ->orderBy('appointment_date')
            ->limit(3)
            ->get();

        $activeReminders = Reminder::query()
            ->where('user_id', $user->id)
            ->where('is_completed', false)
            ->where(function ($query) use ($user): void {
                $query->whereNull('project_id')
                    ->orWhereHas('project', fn ($project) => $project->where('customer_id', $user->id));
            })
            ->with('project:id,project_code,title')
            ->orderBy('reminder_date')
            ->limit(4)
            ->get();

        $latestFiles = ProjectFile::query()
            ->whereHas('project', fn ($query) => $query->where('customer_id', $user->id))
            ->with(['project:id,project_code,title', 'uploader:id,name'])
            ->latest()
            ->limit(5)
            ->get();

        $latestRevisions = Revision::query()
            ->whereHas('project', fn ($query) => $query->where('customer_id', $user->id))
            ->with('project:id,project_code,title')
            ->latest()
            ->limit(4)
            ->get();

        $upcomingMilestone = ProjectMilestone::query()
            ->whereHas('project', fn ($query) => $query->where('customer_id', $user->id))
            ->whereNotNull('due_date')
            ->where('due_date', '>=', now())
            ->with('project:id,project_code,title')
            ->orderBy('due_date')
            ->first();

        return view('customer.dashboard', compact(
            'summary',
            'recentProjects',
            'actionProjects',
            'upcomingAppointments',
            'activeReminders',
            'latestFiles',
            'latestRevisions',
            'upcomingMilestone',
        ));
    }
}
