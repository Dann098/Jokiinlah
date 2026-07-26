<?php

namespace App\Http\Controllers\Customer;

use App\Actions\Revisions\CreateCustomerRevision;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRevisionRequest;
use App\Models\Project;
use App\Models\Revision;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

class RevisionController extends Controller
{
    use AuthorizesRequests;

    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        $revisions = $project->revisions()
            ->with('submitter:id,name')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('customer.revisions.index', compact('project', 'revisions'));
    }

    public function store(
        StoreRevisionRequest $request,
        Project $project,
        CreateCustomerRevision $action,
    ): RedirectResponse {
        $revision = $action->execute(
            $project,
            $request->user(),
            $request->safe()->except('attachment'),
            $request->file('attachment'),
        );

        return redirect()
            ->route('customer.projects.revisions.show', [$project, $revision])
            ->with('status', 'Permintaan revisi berhasil dikirim.');
    }

    public function show(Project $project, Revision $revision): View
    {
        $this->authorize('view', $revision);
        $revision->load('submitter:id,name');

        return view('customer.revisions.show', compact('project', 'revision'));
    }
}
