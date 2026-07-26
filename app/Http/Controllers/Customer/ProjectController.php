<?php

namespace App\Http\Controllers\Customer;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\WhatsAppUrlBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Project::class);

        $search = mb_substr(trim((string) $request->query('q')), 0, 120);
        $status = ProjectStatus::tryFrom((string) $request->query('status'));
        $escapedSearch = addcslashes($search, '\\%_');

        $projects = Project::query()
            ->ownedBy($request->user())
            ->with(['service:id,name', 'assignedStaff:id,name'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($escapedSearch): void {
                $query->where('project_code', 'like', '%'.$escapedSearch.'%')
                    ->orWhere('title', 'like', '%'.$escapedSearch.'%');
            }))
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->latest('updated_at')
            ->paginate(9)
            ->withQueryString();

        return view('customer.projects.index', [
            'projects' => $projects,
            'statuses' => ProjectStatus::cases(),
            'search' => $search,
            'selectedStatus' => $status,
        ]);
    }

    public function show(Project $project, WhatsAppUrlBuilder $whatsApp): View
    {
        $this->authorize('view', $project);

        $customerId = request()->user()->id;
        $project->load([
            'service:id,name,category',
            'assignedStaff:id,name',
            'milestones' => fn ($query) => $query->orderBy('sort_order'),
            'files' => fn ($query) => $query->with('uploader:id,name')->latest(),
            'revisions' => fn ($query) => $query->with('submitter:id,name')->latest(),
            'reminders' => fn ($query) => $query->where('user_id', $customerId)->orderBy('reminder_date'),
            'appointments' => fn ($query) => $query->where('customer_id', $customerId)->orderBy('appointment_date'),
        ]);

        return view('customer.projects.show', [
            'project' => $project,
            'whatsAppUrl' => $whatsApp->forProject($project),
        ]);
    }
}
