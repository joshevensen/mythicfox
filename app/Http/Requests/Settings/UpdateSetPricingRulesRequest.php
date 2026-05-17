<?php

namespace App\Http\Requests\Settings;

use App\Models\CardSet;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSetPricingRulesRequest extends FormRequest
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
            'base_price' => ['nullable', 'integer', 'min:0'],
            'high_price' => ['nullable', 'integer', 'min:0'],
            'market_offset' => ['nullable', 'integer', 'min:0'],
            'high_offset' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var CardSet $set */
            $set = $this->route('set');
            $product = $set->product;

            $effectiveBase = $this->input('base_price') ?? $product->base_price;
            $effectiveHigh = $this->input('high_price') ?? $product->high_price;

            if ($effectiveBase > $effectiveHigh) {
                $validator->errors()->add(
                    'base_price',
                    'Base price must be less than or equal to high price (after inheritance).',
                );
            }
        });
    }
}
