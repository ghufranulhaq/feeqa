<?php

namespace App\Http\Requests\Businesses;

use App\Models\Business;
use App\Models\Category;
use App\Rules\BusinessDescription;
use App\Rules\BusinessName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FR-002-03: every field here is optional — a caller only sends the
 * fields it wants to change (App\Actions\Businesses\UpdateBusinessProfile
 * only touches keys actually present in the data).
 */
class UpdateBusinessProfileRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $business = $this->route('business');
        $ownDomain = $business instanceof Business ? $business->primary_domain : null;

        return [
            'name' => ['sometimes', 'nullable', new BusinessName],
            'domain' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category_id' => ['sometimes', 'nullable', Rule::exists(Category::class, 'id')],
            'description' => ['sometimes', 'nullable', new BusinessDescription($ownDomain)],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'address' => ['sometimes', 'nullable', 'array'],
            'social_links' => ['sometimes', 'nullable', 'array'],
            'secondary_category_ids' => ['sometimes', 'nullable', 'array', 'max:5'],
            'secondary_category_ids.*' => [Rule::exists(Category::class, 'id')],
        ];
    }
}
