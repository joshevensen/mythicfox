<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductPricingRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
