<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductPricingRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $displayName = $this->input('display_name');

        if (is_string($displayName)) {
            $trimmed = trim($displayName);

            $this->merge(['display_name' => $trimmed === '' ? null : $trimmed]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'base_price' => ['required', 'integer', 'min:0', 'lte:high_price'],
            'high_price' => ['required', 'integer', 'min:0'],
            'market_offset' => ['required', 'integer', 'min:0'],
            'high_offset' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'base_price.lte' => 'Base price must be less than or equal to high price.',
        ];
    }
}
