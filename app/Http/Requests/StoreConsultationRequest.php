<?php

namespace App\Http\Requests;

use App\Rules\SafePrivateUpload;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = preg_replace('/\D+/', '', (string) $this->input('phone')) ?? '';
        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        } elseif (str_starts_with($phone, '8')) {
            $phone = '62'.$phone;
        }

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'phone' => $phone,
            'project_title' => trim((string) $this->input('project_title')),
            'description' => trim((string) $this->input('description')),
            'technology' => trim((string) $this->input('technology')) ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['required', 'regex:/^62[0-9]{8,13}$/'],
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
            'project_title' => ['required', 'string', 'min:3', 'max:180'],
            'description' => ['required', 'string', 'min:30', 'max:5000'],
            'deadline' => ['nullable', 'date_format:Y-m-d', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value && CarbonImmutable::parse($value, config('jokiinlah.display_timezone'))->startOfDay()->isBefore(now(config('jokiinlah.display_timezone'))->startOfDay())) {
                    $fail('Deadline tidak boleh berada di masa lalu.');
                }
            }],
            'technology' => ['nullable', 'string', 'max:255'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'attachment' => [
                'nullable',
                File::types(config('jokiinlah.allowed_file_extensions'))->max((int) config('jokiinlah.upload_max_size')),
                new SafePrivateUpload,
            ],
            'privacy' => ['accepted'],
            'academic_integrity' => ['accepted'],
            'website' => ['nullable', 'prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':Attribute wajib diisi.',
            'string' => ':Attribute harus berupa teks.',
            'email' => 'Format :attribute tidak valid.',
            'integer' => ':Attribute tidak valid.',
            'numeric' => ':Attribute harus berupa angka.',
            'date_format' => 'Format :attribute harus berupa tanggal yang valid.',
            'min.string' => ':Attribute minimal :min karakter.',
            'max.string' => ':Attribute maksimal :max karakter.',
            'min.numeric' => ':Attribute minimal :min.',
            'max.numeric' => ':Attribute maksimal :max.',
            'name.required' => 'Nama lengkap wajib diisi.',
            'phone.regex' => 'Gunakan nomor WhatsApp Indonesia yang valid.',
            'service_id.exists' => 'Layanan yang dipilih tidak tersedia.',
            'description.min' => 'Jelaskan kebutuhan minimal 30 karakter.',
            'privacy.accepted' => 'Anda harus menyetujui kebijakan privasi.',
            'academic_integrity.accepted' => 'Anda harus menyetujui ketentuan integritas akademik.',
            'attachment.extensions' => 'Ekstensi lampiran tidak sesuai dengan format yang diizinkan.',
            'attachment.mimetypes' => 'Isi lampiran tidak sesuai dengan format file yang dipilih.',
            'website.prohibited' => 'Permintaan tidak dapat diproses. Silakan coba kembali.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama lengkap',
            'email' => 'email',
            'phone' => 'nomor WhatsApp',
            'service_id' => 'layanan',
            'project_title' => 'judul proyek',
            'description' => 'deskripsi kebutuhan',
            'deadline' => 'deadline',
            'technology' => 'teknologi',
            'budget' => 'estimasi anggaran',
            'attachment' => 'lampiran',
            'privacy' => 'persetujuan privasi',
            'academic_integrity' => 'persetujuan integritas akademik',
        ];
    }
}
