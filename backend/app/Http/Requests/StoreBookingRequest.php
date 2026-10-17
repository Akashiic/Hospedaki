<?php

namespace App\Http\Requests;

class StoreBookingRequest extends QuoteRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'guest_name' => ['required', 'string', 'max:120'],
            'guest_email' => ['nullable', 'email', 'max:180'],
            'guest_phone' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
