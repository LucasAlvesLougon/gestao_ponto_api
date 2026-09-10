<?php

namespace App\Services;

use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;

class HourCalculationService
{
    public function __construct(
        protected TimeTrackingService $timeTrackingService
    ) {}

    /**
     * Calculate hours and balance for a specific day.
     */
    public function calculateDailySummary(User $user, string $date, ?\Illuminate\Database\Eloquent\Collection $preloadedEntries = null): array
    {
        $timezone = $user->timezone ?? 'America/Sao_Paulo';
        $entries = $preloadedEntries ?? $this->timeTrackingService->getEntriesForDate($user, $date);

        $targetHours = $user->getDailyTargetHours();
        $targetMinutes = (int) round($targetHours * 60);

        if ($entries->isEmpty()) {
            return [
                'date' => $date,
                'total_worked_minutes' => 0,
                'total_worked_formatted' => '00h 00m',
                'target_minutes' => $targetMinutes,
                'target_formatted' => $this->formatMinutes($targetMinutes),
                'balance_minutes' => 0,
                'balance_formatted' => '00h 00m',
                'is_positive_balance' => true,
                'entries_count' => 0,
                'is_complete' => false,
                'has_entries' => false,
            ];
        }

        $workedMinutes = 0;
        $workStartTime = null;
        $isComplete = false;

        $nowInUserTz = Carbon::now($timezone);
        $isToday = ($date === $nowInUserTz->toDateString());

        foreach ($entries as $entry) {
            $entryTime = $entry->registered_at;

            switch ($entry->type) {
                case 'CLOCK_IN':
                case 'BREAK_END':
                    $workStartTime = $entryTime;
                    break;

                case 'BREAK_START':
                case 'CLOCK_OUT':
                    if ($workStartTime) {
                        $workedMinutes += (int) $workStartTime->diffInMinutes($entryTime);
                        $workStartTime = null;
                    }
                    if ($entry->type === 'CLOCK_OUT') {
                        $isComplete = true;
                    }
                    break;
            }
        }

        // Se a jornada de hoje ainda estiver em aberto, conta até o minuto atual
        if ($isToday && $workStartTime !== null) {
            $nowUtc = Carbon::now('UTC');
            if ($nowUtc->greaterThan($workStartTime)) {
                $workedMinutes += (int) $workStartTime->diffInMinutes($nowUtc);
            }
        }

        $balanceMinutes = $workedMinutes - $targetMinutes;

        return [
            'date' => $date,
            'total_worked_minutes' => $workedMinutes,
            'total_worked_formatted' => $this->formatMinutes($workedMinutes),
            'target_minutes' => $targetMinutes,
            'target_formatted' => $this->formatMinutes($targetMinutes),
            'balance_minutes' => $balanceMinutes,
            'balance_formatted' => $this->formatMinutes($balanceMinutes, true),
            'is_positive_balance' => $balanceMinutes >= 0,
            'entries_count' => $entries->count(),
            'is_complete' => $isComplete,
            'has_entries' => true,
        ];
    }

    /**
     * Calculate monthly accumulated hours and balance.
     */
    public function calculateMonthlySummary(User $user, string $month): array
    {
        $timezone = $user->timezone ?? 'America/Sao_Paulo';

        // Parse month (YYYY-MM)
        $firstDayOfMonth = Carbon::parse($month.'-01', $timezone)->startOfMonth();
        $lastDayOfMonth = $firstDayOfMonth->copy()->endOfMonth();

        $startUtc = $firstDayOfMonth->copy()->setTimezone('UTC');
        $endUtc = $lastDayOfMonth->copy()->setTimezone('UTC');

        // Busca todas as entradas do mês em uma única query
        $allEntries = $user->timeEntries()
            ->whereBetween('registered_at', [$startUtc, $endUtc])
            ->orderBy('registered_at', 'asc')
            ->get();

        // Agrupa por data local
        $entriesByDay = $allEntries->groupBy(function (TimeEntry $entry) use ($timezone) {
            return Carbon::parse($entry->registered_at)->setTimezone($timezone)->toDateString();
        });

        $totalWorkedMinutes = 0;
        $totalTargetMinutes = 0;
        $dailySummaries = [];
        $daysWorkedCount = 0;

        $targetHours = $user->getDailyTargetHours();
        $targetMinutesPerDay = (int) round($targetHours * 60);

        // Itera sobre todos os dias do mês
        $currentDay = $firstDayOfMonth->copy();
        while ($currentDay->lessThanOrEqualTo($lastDayOfMonth)) {
            $dateString = $currentDay->toDateString();
            $dayEntries = $entriesByDay->get($dateString);

            if ($dayEntries && $dayEntries->isNotEmpty()) {
                $daySummary = $this->calculateDailySummary($user, $dateString);
                $dailySummaries[] = $daySummary;

                $totalWorkedMinutes += $daySummary['total_worked_minutes'];
                $totalTargetMinutes += $daySummary['target_minutes'];
                $daysWorkedCount++;
            }

            $currentDay->addDay();
        }

        $balanceMinutes = $totalWorkedMinutes - $totalTargetMinutes;

        return [
            'month' => $month,
            'month_formatted' => $firstDayOfMonth->translatedFormat('F \\de Y'),
            'total_worked_minutes' => $totalWorkedMinutes,
            'total_worked_formatted' => $this->formatMinutes($totalWorkedMinutes),
            'total_target_minutes' => $totalTargetMinutes,
            'total_target_formatted' => $this->formatMinutes($totalTargetMinutes),
            'balance_minutes' => $balanceMinutes,
            'balance_formatted' => $this->formatMinutes($balanceMinutes, true),
            'is_positive_balance' => $balanceMinutes >= 0,
            'days_worked_count' => $daysWorkedCount,
            'daily_summaries' => $dailySummaries,
        ];
    }

    /**
     * Format minutes into readable HHh MMm representation.
     */
    public function formatMinutes(int $minutes, bool $withSign = false): string
    {
        $isNegative = $minutes < 0;
        $absMinutes = abs($minutes);

        $hours = (int) floor($absMinutes / 60);
        $remainingMinutes = $absMinutes % 60;

        $formatted = sprintf('%02dh %02dm', $hours, $remainingMinutes);

        if ($withSign) {
            return $isNegative ? "-{$formatted}" : "+{$formatted}";
        }

        return $formatted;
    }
}
