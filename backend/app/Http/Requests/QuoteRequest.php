<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuoteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'accommodation_ids' => ['required', 'array', 'min:1', 'max:10'],
            'accommodation_ids.*' => ['integer', 'distinct'],
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in', 'before:'.now()->addYears(2)->toDateString()],
            'guests' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
