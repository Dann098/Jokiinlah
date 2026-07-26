<?php

namespace App\Http\Requests;

use App\Models\ProjectFile;
use App\Rules\SafePrivateUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreProjectFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project && $this->user()?->can('create', [ProjectFile::class, $project]);
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types(config('jokiinlah.allowed_file_extensions'))->max((int) config('jokiinlah.upload_max_size')),
                new SafePrivateUpload,
            ],
            'category' => ['required', 'string', Rule::in(array_keys(config('jokiinlah.project_file_categories', [])))],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Berkas wajib dipilih.',
            'file.extensions' => 'Ekstensi berkas tidak diizinkan.',
            'file.mimetypes' => 'Isi berkas tidak sesuai dengan format yang dipilih.',
            'file.max' => 'Ukuran berkas melampaui batas yang diizinkan.',
            'category.required' => 'Kategori berkas wajib dipilih.',
            'category.in' => 'Kategori berkas tidak valid.',
        ];
    }
}
