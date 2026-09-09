<?php

namespace App\Http\Requests\TimeEntry;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', Rule::in(['CLOCK_IN', 'BREAK_START', 'BREAK_END', 'CLOCK_OUT'])],
            'custom_time' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Tipo de batida inválido. Utilize CLOCK_IN, BREAK_START, BREAK_END ou CLOCK_OUT.',
            'custom_time.date' => 'Data/horário informado é inválido.',
        ];
    }
}
