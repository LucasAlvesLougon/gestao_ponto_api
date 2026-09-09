<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReportExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    public function __construct(
        protected ReportExportService $reportExportService
    ) {}

    /**
     * Download monthly time sheet as CSV.
     */
    public function csv(Request $request): Response
    {
        $user = $request->user();
        $timezone = $user->timezone ?? 'America/Sao_Paulo';

        $month = $request->query('month', Carbon::now($timezone)->format('Y-m'));

        $csv = $this->reportExportService->generateMonthlyCsv($user, $month);

        $filename = "espelho_ponto_{$month}.csv";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }

    /**
     * View monthly time sheet formatted for PDF / Print.
     */
    public function print(Request $request): Response
    {
        $user = $request->user();
        $timezone = $user->timezone ?? 'America/Sao_Paulo';

        $month = $request->query('month', Carbon::now($timezone)->format('Y-m'));

        $html = $this->reportExportService->generatePrintableHtml($user, $month);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
