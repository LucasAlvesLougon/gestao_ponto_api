<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkScheduleController extends Controller
{
    /**
     * Get active work schedule for authenticated user.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $schedule = $user->workSchedules()
            ->orderBy('effective_from', 'desc')
            ->first();

        return response()->json([
            'daily_target_hours' => $schedule ? (float) $schedule->daily_target_hours : 8.0,
            'break_duration' => $schedule ? $schedule->break_duration : '01:00',
            'effective_from' => $schedule ? $schedule->effective_from?->toDateString() : Carbon::today()->toDateString(),
        ]);
    }

    /**
     * Create or update work schedule configuration.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'daily_target_hours' => ['required', 'numeric', 'min:1', 'max:24'],
            'break_duration' => ['nullable', 'string'],
            'effective_from' => ['nullable', 'date'],
        ], [
            'daily_target_hours.required' => 'A meta diária de horas é obrigatória.',
            'daily_target_hours.min' => 'A meta diária mínima é de 1 hora.',
            'daily_target_hours.max' => 'A meta diária máxima é de 24 horas.',
        ]);

        $schedule = $user->workSchedules()->create([
            'daily_target_hours' => $validated['daily_target_hours'],
            'break_duration' => $validated['break_duration'] ?? '01:00',
            'effective_from' => $validated['effective_from'] ?? Carbon::today()->toDateString(),
        ]);

        return response()->json([
            'message' => 'Configuração de jornada salva com sucesso.',
            'schedule' => [
                'daily_target_hours' => (float) $schedule->daily_target_hours,
                'break_duration' => $schedule->break_duration,
                'effective_from' => $schedule->effective_from?->toDateString(),
            ],
        ], 201);
    }
}
