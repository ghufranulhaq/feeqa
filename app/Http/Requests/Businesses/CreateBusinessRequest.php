<?php

namespace App\Http\Requests\Businesses;

use App\Models\Category;
use App\Rules\BusinessName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * FR-002-08: by domain, or by name + country + city when there's no
 * website — never both, never neither.
 */
class CreateBusinessRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->domain === '') {
            $this->merge(['domain' => null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'domain' => ['nullable', 'string', 'max:255'],
            'name' => ['required_without:domain', 'nullable', new BusinessName],
            'country' => ['required_without:domain', 'nullable', 'string', 'size:2', Rule::in(array_keys(config('countries')))],
            'city' => ['required_without:domain', 'nullable', 'string', 'max:255'],
            'category_id' => ['nullable', Rule::exists(Category::class, 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('domain') && $this->filled('name')) {
                $validator->errors()->add('domain', 'Give either a website domain or a name, city, and country — not both.');
            }
        });
    }
}
