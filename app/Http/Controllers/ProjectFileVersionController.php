<?php

namespace App\Http\Controllers;

use App\Actions\ProjectFiles\CreateProjectFileVersion;
use App\Http\Requests\StoreProjectFileVersionRequest;
use App\Models\ProjectFile;
use App\Services\PrivateProjectFileStorage;
use Illuminate\Http\RedirectResponse;
use Throwable;

class ProjectFileVersionController extends Controller
{
    public function store(
        StoreProjectFileVersionRequest $request,
        ProjectFile $projectFile,
        CreateProjectFileVersion $action,
        PrivateProjectFileStorage $storage,
    ): RedirectResponse {
        $metadata = $storage->store($request->file('file'));

        try {
            $action->execute($projectFile, $request->user(), array_merge($metadata, [
                'category' => $request->string('category')->toString(),
                'description' => $request->string('description')->toString() ?: null,
            ]));
        } catch (Throwable $exception) {
            $storage->delete($metadata['file_path']);
            throw $exception;
        }

        return back()->with('status', 'Versi berkas baru berhasil diunggah.');
    }
}
