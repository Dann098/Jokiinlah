<?php

namespace App\Http\Controllers\Customer;

use App\Actions\ProjectFiles\CreateProjectFileRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectFileRequest;
use App\Models\Project;
use App\Services\PrivateProjectFileStorage;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Throwable;

class ProjectFileController extends Controller
{
    use AuthorizesRequests;

    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        $files = $project->files()
            ->with('uploader:id,name')
            ->orderBy('document_uuid')
            ->orderByDesc('version')
            ->get()
            ->groupBy('document_uuid');

        return view('customer.projects.files', compact('project', 'files'));
    }

    public function store(
        StoreProjectFileRequest $request,
        Project $project,
        CreateProjectFileRecord $action,
        PrivateProjectFileStorage $storage,
    ): RedirectResponse {
        $metadata = $storage->store($request->file('file'));

        try {
            $action->execute($project, $request->user(), array_merge($metadata, [
                'category' => $request->string('category')->toString(),
                'description' => $request->string('description')->toString() ?: null,
            ]));
        } catch (Throwable $exception) {
            $storage->delete($metadata['file_path']);
            throw $exception;
        }

        return back()->with('status', 'Berkas baru berhasil diunggah.');
    }
}
