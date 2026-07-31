<?php

namespace App\Http\Requests;

use App\Models\Consultation;
use App\Rules\SafePrivateUpload;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreCustomerProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCustomer()
            && $this->user()->can('create', Consultation::class);
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone') ?: $this->user()?->phone;
        $phone = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        } elseif (str_starts_with($phone, '8')) {
            $phone = '62'.$phone;
        }

        $this->merge([
            'phone' => $phone,
            'project_title' => trim((string) $this->input('project_title')),
            'description' => trim((string) $this->input('description')),
            'technology' => trim((string) $this->input('technology')) ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
            'project_title' => ['required', 'string', 'min:3', 'max:180'],
            'description' => ['required', 'string', 'min:30', 'max:5000'],
            'phone' => ['required', 'regex:/^62[0-9]{8,13}$/'],
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
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':Attribute wajib diisi.',
            'service_id.exists' => 'Layanan yang dipilih tidak tersedia.',
            'description.min' => 'Jelaskan kebutuhan minimal 30 karakter.',
            'phone.regex' => 'Gunakan nomor WhatsApp Indonesia yang valid.',
            'privacy.accepted' => 'Anda harus menyetujui kebijakan privasi.',
            'academic_integrity.accepted' => 'Anda harus menyetujui ketentuan integritas akademik.',
        ];
    }

    public function attributes(): array
    {
        return [
            'service_id' => 'layanan',
            'project_title' => 'judul proyek',
            'description' => 'deskripsi kebutuhan',
            'phone' => 'nomor WhatsApp',
            'privacy' => 'persetujuan privasi',
            'academic_integrity' => 'persetujuan integritas akademik',
        ];
    }
}
