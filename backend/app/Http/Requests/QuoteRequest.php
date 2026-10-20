<?php

namespace App\Http\Requests;

use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;

class QuoteRequest extends FormRequest
{
    public function rules(): array
    {
        // "Hoje" é o dia no fuso da pousada, o mesmo que o hóspede vê no calendário.
        $property = $this->route('property');
        $today = $property instanceof Property ? $property->today() : now(Property::DEFAULT_TIMEZONE)->startOfDay();

        return [
            'accommodation_ids' => ['required', 'array', 'min:1', 'max:10'],
            'accommodation_ids.*' => ['integer', 'distinct'],
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today->toDateString()],
            // Reserva por hora (day use, eventos): um dia só, com horário de início e de fim.
            'check_out' => ['required_without:start_time', 'nullable', 'date_format:Y-m-d', 'after:check_in', 'before:'.$today->addYears(2)->toDateString()],
            'start_time' => ['nullable', 'required_with:end_time', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_with:start_time', 'date_format:H:i'],
            'guests' => ['required', 'integer', 'min:1', 'max:200'],
        ];
    }
}
