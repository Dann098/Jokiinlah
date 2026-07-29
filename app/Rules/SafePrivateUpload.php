<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class SafePrivateUpload implements ValidationRule
{
    private const DANGEROUS_INTERMEDIATE_EXTENSIONS = [
        'bat', 'cgi', 'cmd', 'com', 'dll', 'exe', 'hta', 'htaccess', 'jar',
        'js', 'msi', 'phtml', 'phar', 'php', 'pl', 'ps1', 'py', 'scr', 'sh', 'vbs',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $extension = mb_strtolower($value->getClientOriginalExtension());
        $allowedExtensions = config('jokiinlah.allowed_file_extensions', []);
        $originalName = $value->getClientOriginalName();
        $nameParts = explode('.', mb_strtolower($originalName));

        if (str_contains($originalName, "\0")) {
            $fail('Nama berkas mengandung null byte yang tidak aman.');

            return;
        }

        if ($extension === '' || ! in_array($extension, $allowedExtensions, true)) {
            $fail('Berkas harus memiliki ekstensi yang diizinkan.');

            return;
        }

        array_pop($nameParts);

        if (array_intersect($nameParts, self::DANGEROUS_INTERMEDIATE_EXTENSIONS) !== []) {
            $fail('Nama berkas mengandung ekstensi ganda yang tidak aman.');

            return;
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)
            && @getimagesize($value->getRealPath()) === false) {
            $fail('Isi berkas gambar tidak dapat dikenali sebagai gambar yang valid.');
        }
    }
}
