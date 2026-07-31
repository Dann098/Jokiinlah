<?php

namespace App\Http\Controllers\Customer;

use App\Actions\Consultations\CreateConsultation;
use App\Enums\ConsultationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerProjectRequest;
use App\Http\Requests\UpdateCustomerProjectRequest;
use App\Models\Consultation;
use App\Models\Service;
use App\Services\ActivityLogger;
use App\Services\ProjectRequestNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectRequestController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('q')), 0, 120);
        $status = ConsultationStatus::tryFrom((string) $request->query('status'));
        $escapedSearch = addcslashes($search, '\\%_');

        $consultations = Consultation::query()
            ->where('user_id', $request->user()->id)
            ->with(['service:id,name', 'project:id,consultation_id,project_code,title'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($escapedSearch): void {
                $query->where('request_code', 'like', '%'.$escapedSearch.'%')
                    ->orWhere('project_title', 'like', '%'.$escapedSearch.'%');
            }))
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('customer.project-requests.index', [
            'consultations' => $consultations,
            'search' => $search,
            'selectedStatus' => $status,
            'statuses' => ConsultationStatus::cases(),
        ]);
    }

    public function create(): View
    {
        return view('customer.project-requests.create', [
            'services' => Service::query()->active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(
        StoreCustomerProjectRequest $request,
        CreateConsultation $action,
        ProjectRequestNotifier $notifier,
    ): RedirectResponse {
        $user = $request->user();
        $data = array_merge($request->validated(), [
            'name' => $user->name,
            'email' => mb_strtolower($user->email),
        ]);

        $consultation = $action->execute(
            $data,
            $request->file('attachment'),
            $user,
            'customer_portal',
        );

        if ($consultation->wasRecentlyCreated) {
            $notifier->notifyAdmins($consultation);
        }

        return redirect()
            ->route('customer.project-requests.show', $consultation)
            ->with('status', 'Permintaan proyek berhasil dikirim.');
    }

    public function show(Consultation $consultation): View
    {
        $this->authorize('viewRequest', $consultation);
        $consultation->load(['service:id,name', 'project:id,consultation_id,project_code,title']);

        return view('customer.project-requests.show', compact('consultation'));
    }

    public function update(
        UpdateCustomerProjectRequest $request,
        Consultation $consultation,
        ActivityLogger $logger,
        ProjectRequestNotifier $notifier,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $consultation, $logger): void {
            $consultation->forceFill([
                'description' => $request->validated('description'),
                'status' => ConsultationStatus::New,
                'responded_at' => now(),
            ])->save();

            $logger->log(
                'consultation.customer_information_submitted',
                'Customer melengkapi informasi permintaan proyek.',
                $request->user(),
                $consultation,
            );
        });

        $notifier->notifyAdmins($consultation->refresh());

        return redirect()
            ->route('customer.project-requests.show', $consultation)
            ->with('status', 'Informasi tambahan berhasil dikirim.');
    }
}
