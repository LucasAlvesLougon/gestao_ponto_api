<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timezone = $request->user()?->timezone ?? 'America/Sao_Paulo';

        $localTime = $this->registered_at
            ? Carbon::parse($this->registered_at)->setTimezone($timezone)
            : null;

        $originalLocalTime = $this->original_registered_at
            ? Carbon::parse($this->original_registered_at)->setTimezone($timezone)
            : null;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => match ($this->type) {
                'CLOCK_IN' => 'Entrada',
                'BREAK_START' => 'Início Intervalo',
                'BREAK_END' => 'Retorno Intervalo',
                'CLOCK_OUT' => 'Saída',
                default => $this->type,
            },
            'registered_at' => $this->registered_at?->toISOString(),
            'time_formatted' => $localTime?->format('H:i:s'),
            'date_formatted' => $localTime?->format('d/m/Y'),
            'datetime_formatted' => $localTime?->format('d/m/Y H:i:s'),
            'is_edited' => $this->is_edited,
            'edit_reason' => $this->edit_reason,
            'original_registered_at' => $this->original_registered_at?->toISOString(),
            'original_time_formatted' => $originalLocalTime?->format('H:i:s'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
