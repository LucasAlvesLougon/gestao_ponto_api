<?php

namespace App\Http\Requests\TimeEntry;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'time' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'time.required' => 'O novo horário é obrigatório.',
            'time.date' => 'Formato de horário inválido.',
            'reason.required' => 'A justificativa para a alteração é obrigatória.',
            'reason.min' => 'A justificativa deve conter no mínimo 5 caracteres.',
        ];
    }
}
