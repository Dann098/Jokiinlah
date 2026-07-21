<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreProjectFileVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $file = $this->route('projectFile');

        return $file && $this->user()?->can('uploadVersion', $file);
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types(config('jokiinlah.allowed_file_extensions'))->max((int) config('jokiinlah.upload_max_size')),
            ],
            'category' => ['required', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Berkas versi baru wajib dipilih.',
            'file.mimes' => 'Tipe berkas tidak diizinkan.',
            'file.max' => 'Ukuran berkas melampaui batas yang diizinkan.',
        ];
    }
}
