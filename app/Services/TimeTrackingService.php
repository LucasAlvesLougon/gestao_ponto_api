<?php

namespace App\Services;

use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class TimeTrackingService
{
    /**
     * Get all entries for a specific day in the user's timezone.
     */
    public function getEntriesForDate(User $user, string $date): \Illuminate\Database\Eloquent\Collection
    {
        $timezone = $user->timezone ?? 'America/Sao_Paulo';

        $startOfDayUtc = Carbon::parse($date, $timezone)->startOfDay()->setTimezone('UTC');
        $endOfDayUtc = Carbon::parse($date, $timezone)->endOfDay()->setTimezone('UTC');

        return $user->timeEntries()
            ->whereBetween('registered_at', [$startOfDayUtc, $endOfDayUtc])
            ->orderBy('registered_at', 'asc')
            ->get();
    }

    /**
     * Determine the next expected time entry type for today.
     */
    public function determineNextExpectedType(User $user, ?Carbon $nowInUserTz = null): string
    {
        $timezone = $user->timezone ?? 'America/Sao_Paulo';
        $today = $nowInUserTz ?? Carbon::now($timezone);

        $entries = $this->getEntriesForDate($user, $today->toDateString());

        if ($entries->isEmpty()) {
            return 'CLOCK_IN';
        }

        $lastEntry = $entries->last();

        return match ($lastEntry->type) {
            'CLOCK_IN' => 'BREAK_START',
            'BREAK_START' => 'BREAK_END',
            'BREAK_END' => 'CLOCK_OUT',
            'CLOCK_OUT' => 'CLOCK_IN',
            default => 'CLOCK_IN',
        };
    }

    /**
     * Validate chronological state transition.
     */
    public function validateSequence(User $user, string $type, Carbon $timestampUtc): void
    {
        $timezone = $user->timezone ?? 'America/Sao_Paulo';
        $dateInUserTz = $timestampUtc->copy()->setTimezone($timezone)->toDateString();

        $entries = $this->getEntriesForDate($user, $dateInUserTz);

        if ($entries->isEmpty()) {
            if ($type !== 'CLOCK_IN') {
                throw ValidationException::withMessages([
                    'type' => ['O primeiro registro do dia deve ser a Entrada (CLOCK_IN).'],
                ]);
            }
            return;
        }

        $lastEntry = $entries->last();

        // Não permitir timestamp anterior ao último registro
        if ($timestampUtc->lessThanOrEqualTo($lastEntry->registered_at)) {
            throw ValidationException::withMessages([
                'registered_at' => ['O horário deve ser posterior ao último registro efetuado hoje.'],
            ]);
        }

        switch ($lastEntry->type) {
            case 'CLOCK_IN':
                if ($type === 'CLOCK_IN') {
                    throw ValidationException::withMessages([
                        'type' => ['Você já registrou a Entrada. O próximo registro deve ser Início de Intervalo ou Saída.'],
                    ]);
                }
                if ($type === 'BREAK_END') {
                    throw ValidationException::withMessages([
                        'type' => ['Não é possível registrar Retorno de Intervalo sem antes ter registrado o Início do Intervalo.'],
                    ]);
                }
                break;

            case 'BREAK_START':
                if ($type !== 'BREAK_END') {
                    throw ValidationException::withMessages([
                        'type' => ['Você está em intervalo. É obrigatório registrar o Retorno de Intervalo (BREAK_END) antes de qualquer outra marcação.'],
                    ]);
                }
                break;

            case 'BREAK_END':
                if ($type === 'BREAK_END') {
                    throw ValidationException::withMessages([
                        'type' => ['Você já retornou do intervalo. O próximo registro deve ser Início de Intervalo ou Saída.'],
                    ]);
                }
                if ($type === 'CLOCK_IN') {
                    throw ValidationException::withMessages([
                        'type' => ['A entrada já foi registrada hoje. O próximo registro deve ser Início de Intervalo ou Saída.'],
                    ]);
                }
                break;

            case 'CLOCK_OUT':
                if ($type === 'BREAK_START' || $type === 'BREAK_END') {
                    throw ValidationException::withMessages([
                        'type' => ['A jornada já foi encerrada com Saída. Não é possível registrar intervalos.'],
                    ]);
                }
                break;
        }
    }

    /**
     * Record a new time entry.
     */
    public function recordEntry(User $user, ?string $type = null, ?string $customTime = null): TimeEntry
    {
        $timezone = $user->timezone ?? 'America/Sao_Paulo';

        $timestampUtc = $customTime
            ? Carbon::parse($customTime, $timezone)->setTimezone('UTC')
            : Carbon::now('UTC');

        $resolvedType = $type ?: $this->determineNextExpectedType($user, $timestampUtc->copy()->setTimezone($timezone));

        $this->validateSequence($user, $resolvedType, $timestampUtc);

        return $user->timeEntries()->create([
            'type' => $resolvedType,
            'registered_at' => $timestampUtc,
            'is_edited' => false,
        ]);
    }

    /**
     * Update an existing time entry with audit reason.
     */
    public function updateEntry(TimeEntry $entry, User $user, string $newTime, string $reason): TimeEntry
    {
        if ($entry->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'auth' => ['Acesso não autorizado para modificar este registro.'],
            ]);
        }

        $timezone = $user->timezone ?? 'America/Sao_Paulo';
        $newTimestampUtc = Carbon::parse($newTime, $timezone)->setTimezone('UTC');

        $originalTime = $entry->original_registered_at ?: $entry->registered_at;

        $entry->update([
            'registered_at' => $newTimestampUtc,
            'original_registered_at' => $originalTime,
            'is_edited' => true,
            'edit_reason' => $reason,
        ]);

        return $entry->fresh();
    }

    /**
     * Delete a time entry safely.
     */
    public function deleteEntry(TimeEntry $entry, User $user): void
    {
        if ($entry->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'auth' => ['Acesso não autorizado para excluir este registro.'],
            ]);
        }

        $entry->delete();
    }
}
