<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProductIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:App\\Models\\Category,id'],
            'min_price' => ['nullable', 'decimal:0,2', 'min:0'],
            'max_price' => ['nullable', 'decimal:0,2', 'gte:min_price'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
