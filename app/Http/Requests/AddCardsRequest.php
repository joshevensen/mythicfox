<?php

namespace App\Http\Requests;

use App\Http\Controllers\AddCardsController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddCardsRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'set_id' => ['required', 'integer', 'exists:sets,id'],
            'condition' => ['required', 'string', Rule::in(AddCardsController::CONDITIONS)],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.card_id' => ['required', 'integer', 'exists:cards,id'],
            'entries.*.qty' => ['required', 'integer', 'min:0'],
        ];
    }
}
