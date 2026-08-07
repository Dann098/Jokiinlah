<?php

namespace App\Http\Requests;

use App\Rules\ValidWordDocument;
use Illuminate\Foundation\Http\FormRequest;

final class ConvertWordToPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document' => [
                'bail',
                'required',
                'file',
                'max:'.((int) config('converter.word_to_pdf_max_mb') * 1024),
                new ValidWordDocument,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'document.required' => 'Pilih dokumen Word yang akan dikonversi.',
            'document.file' => 'Dokumen yang diunggah tidak valid.',
            'document.max' => 'Ukuran dokumen maksimal '.((int) config('converter.word_to_pdf_max_mb')).' MB.',
        ];
    }
}
