<?php

namespace App\Http\Requests;

use App\Rules\JordanPhone;
use Illuminate\Foundation\Http\FormRequest;

class BookingRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => JordanPhone::normalize((string) $this->input('phone', ''))]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['service_id' => ['required', 'integer', 'exists:services,id'], 'staff_id' => ['nullable', 'integer', 'exists:staff,id'], 'starts_at' => ['required', 'date', 'regex:/T.*(Z|[+-]\d{2}:\d{2})$/'], 'name' => ['required', 'string', 'max:120'], 'email' => ['nullable', 'email:rfc', 'max:254'], 'phone' => ['required', 'string', new JordanPhone], 'notes' => ['nullable', 'string', 'max:1000'], 'locale' => ['required', 'in:en,ar'], 'policy_accepted' => ['required', 'accepted'], 'marketing_consent' => ['nullable', 'boolean']];
    }
}
