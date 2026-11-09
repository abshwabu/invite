<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\PhoneNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare inputs before validation runs.
     */
    protected function prepareForValidation(): void
    {
        $merges = [];

        if ($this->filled('phone')) {
            $normalized = PhoneNormalizer::normalize($this->phone);
            $merges['phone'] = $normalized ?? $this->phone;
        }

        if (! $this->has('locale') || empty($this->locale)) {
            $merges['locale'] = $this->user()?->locale ?? 'en';
        }

        if (! empty($merges)) {
            $this->merge($merges);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:25',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'locale' => ['required', 'string', 'in:en,am'],
        ];
    }
}
