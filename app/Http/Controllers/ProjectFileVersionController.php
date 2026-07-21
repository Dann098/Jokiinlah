<?php

namespace App\Http\Controllers;

use App\Actions\ProjectFiles\CreateProjectFileVersion;
use App\Http\Requests\StoreProjectFileVersionRequest;
use App\Models\ProjectFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProjectFileVersionController extends Controller
{
    public function store(StoreProjectFileVersionRequest $request, ProjectFile $projectFile, CreateProjectFileVersion $action): RedirectResponse
    {
        $upload = $request->file('file');
        $storedName = (string) Str::uuid();
        $extension = mb_strtolower($upload->getClientOriginalExtension());
        $physicalName = $storedName.'.'.$extension;
        $directory = 'projects/'.$projectFile->project_id;
        $path = Storage::disk('local')->putFileAs($directory, $upload, $physicalName);

        try {
            $action->execute($projectFile, $request->user(), [
                'category' => $request->string('category')->toString(),
                'original_name' => basename($upload->getClientOriginalName()),
                'stored_name' => $storedName,
                'file_path' => $path,
                'file_type' => $upload->getMimeType() ?: 'application/octet-stream',
                'file_size' => $upload->getSize(),
                'checksum' => hash_file('sha256', $upload->getRealPath()),
                'description' => $request->string('description')->toString() ?: null,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('status', 'Versi berkas baru berhasil diunggah.');
    }
}
