<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateCustomerPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isCustomer();
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'current_password.current_password' => 'Kata sandi saat ini tidak sesuai.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak sesuai.',
        ];
    }
}
