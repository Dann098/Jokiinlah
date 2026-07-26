<?php

namespace App\Http\Requests;

use App\Models\Revision;
use App\Rules\SafePrivateUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project && $this->user()?->can('create', [Revision::class, $project]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'description' => trim((string) $this->input('description')),
            'section_reference' => trim((string) $this->input('section_reference')) ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'section_reference' => ['nullable', 'string', 'max:255'],
            'attachment' => [
                'nullable',
                File::types(config('jokiinlah.allowed_file_extensions'))->max((int) config('jokiinlah.upload_max_size')),
                new SafePrivateUpload,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul revisi wajib diisi.',
            'title.min' => 'Judul revisi minimal 3 karakter.',
            'description.required' => 'Deskripsi revisi wajib diisi.',
            'description.min' => 'Deskripsi revisi minimal 10 karakter.',
            'attachment.extensions' => 'Ekstensi lampiran tidak diizinkan.',
            'attachment.mimetypes' => 'Isi lampiran tidak sesuai dengan format yang dipilih.',
            'attachment.max' => 'Ukuran lampiran melampaui batas yang diizinkan.',
        ];
    }
}
