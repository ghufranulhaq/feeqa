<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use App\Rules\DisplayName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * An empty-string "no selection" from a <select> is not the same as
     * the field being absent — normalise it to null so `nullable` applies.
     */
    protected function prepareForValidation(): void
    {
        if ($this->country === '') {
            $this->merge(['country' => null]);
        }

        if ($this->locale === '') {
            $this->merge(['locale' => null]);
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
            'name' => [new DisplayName],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],

            // FR-001-05, FR-001-08: nullable because sign-up flows other
            // than the email+password form (T8, T9) may not have collected
            // these yet — see the profile-fields migration.
            'country' => ['sometimes', 'nullable', 'string', 'size:2', Rule::in(array_keys(config('countries')))],
            'locale' => ['sometimes', 'nullable', 'string', Rule::in(config('platform.locales.supported'))],
        ];
    }
}
