<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\HourCalculationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SummaryController extends Controller
{
    public function __construct(
        protected HourCalculationService $hourCalculationService
    ) {}

    /**
     * Get daily work summary and balance.
     */
    public function daily(Request $request): JsonResponse
    {
        $user = $request->user();
        $timezone = $user->timezone ?? 'America/Sao_Paulo';

        $date = $request->query('date', Carbon::now($timezone)->toDateString());

        $summary = $this->hourCalculationService->calculateDailySummary($user, $date);

        return response()->json([
            'summary' => $summary,
        ]);
    }

    /**
     * Get monthly accumulated summary and daily breakdown.
     */
    public function monthly(Request $request): JsonResponse
    {
        $user = $request->user();
        $timezone = $user->timezone ?? 'America/Sao_Paulo';

        $month = $request->query('month', Carbon::now($timezone)->format('Y-m'));

        $summary = $this->hourCalculationService->calculateMonthlySummary($user, $month);

        return response()->json([
            'summary' => $summary,
        ]);
    }
}
