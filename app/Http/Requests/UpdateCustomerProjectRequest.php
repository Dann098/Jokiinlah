<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $consultation = $this->route('consultation');

        return $consultation && ($this->user()?->can('updateRequest', $consultation) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['description' => trim((string) $this->input('description'))]);
    }

    public function rules(): array
    {
        return ['description' => ['required', 'string', 'min:30', 'max:5000']];
    }
}
