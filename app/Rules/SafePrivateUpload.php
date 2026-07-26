<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class SafePrivateUpload implements ValidationRule
{
    private const DANGEROUS_INTERMEDIATE_EXTENSIONS = [
        'bat', 'cmd', 'com', 'exe', 'hta', 'jar', 'js', 'msi', 'phtml',
        'phar', 'php', 'ps1', 'scr', 'sh', 'vbs',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $extension = mb_strtolower($value->getClientOriginalExtension());
        $allowedExtensions = config('jokiinlah.allowed_file_extensions', []);
        $nameParts = explode('.', mb_strtolower($value->getClientOriginalName()));

        if ($extension === '' || ! in_array($extension, $allowedExtensions, true)) {
            $fail('Berkas harus memiliki ekstensi yang diizinkan.');

            return;
        }

        array_pop($nameParts);

        if (array_intersect($nameParts, self::DANGEROUS_INTERMEDIATE_EXTENSIONS) !== []) {
            $fail('Nama berkas mengandung ekstensi ganda yang tidak aman.');
        }
    }
}
