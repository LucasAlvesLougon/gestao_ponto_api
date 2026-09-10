<?php

namespace App\Services;

use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;

class ReportExportService
{
    public function __construct(
        protected HourCalculationService $hourCalculationService,
        protected TimeTrackingService $timeTrackingService
    ) {}

    /**
     * Generate CSV string for monthly report with UTF-8 BOM for Excel compatibility.
     */
    public function generateMonthlyCsv(User $user, string $month): string
    {
        $timezone = $user->timezone ?? 'America/Sao_Paulo';
        $summary = $this->hourCalculationService->calculateMonthlySummary($user, $month);

        $fp = fopen('php://temp', 'r+');

        // Adiciona BOM UTF-8 para o Excel reconhecer acentos automaticamente no Windows
        fputs($fp, "\xEF\xBB\xBF");

        // Cabeçalho institucional
        fputcsv($fp, ['RELATÓRIO MENSAL DE PONTO / BANCO DE HORAS'], ';');
        fputcsv($fp, ['Colaborador:', $user->name], ';');
        fputcsv($fp, ['E-mail:', $user->email], ';');
        fputcsv($fp, ['Mês de Referência:', $summary['month_formatted']], ';');
        fputcsv($fp, ['Fuso Horário:', $timezone], ';');
        fputcsv($fp, [], ';');

        // Cabeçalho da tabela
        fputcsv($fp, [
            'Data',
            'Entrada',
            'Início Intervalo',
            'Retorno Intervalo',
            'Saída',
            'Horas Trabalhadas',
            'Meta',
            'Saldo do Dia',
            'Status',
        ], ';');

        foreach ($summary['daily_summaries'] as $day) {
            $entries = $this->timeTrackingService->getEntriesForDate($user, $day['date']);

            $clockIn = $entries->firstWhere('type', 'CLOCK_IN');
            $breakStart = $entries->firstWhere('type', 'BREAK_START');
            $breakEnd = $entries->firstWhere('type', 'BREAK_END');
            $clockOut = $entries->firstWhere('type', 'CLOCK_OUT');

            $formatTime = fn (?TimeEntry $e) => $e
                ? Carbon::parse($e->registered_at)->setTimezone($timezone)->format('H:i')
                : '-';

            $formatDate = Carbon::parse($day['date'])->format('d/m/Y');

            fputcsv($fp, [
                $formatDate,
                $formatTime($clockIn),
                $formatTime($breakStart),
                $formatTime($breakEnd),
                $formatTime($clockOut),
                $day['total_worked_formatted'],
                $day['target_formatted'],
                $day['balance_formatted'],
                $day['is_complete'] ? 'Fechada' : 'Em aberto',
            ], ';');
        }

        fputcsv($fp, [], ';');
        fputcsv($fp, [
            'TOTAIS DO PERÍODO',
            '',
            '',
            '',
            '',
            $summary['total_worked_formatted'],
            $summary['total_target_formatted'],
            $summary['balance_formatted'],
            $summary['is_positive_balance'] ? 'Saldo Positivo' : 'Saldo Devedor',
        ], ';');

        rewind($fp);
        $csvContent = stream_get_contents($fp);
        fclose($fp);

        return $csvContent;
    }

    /**
     * Generate standalone Printable HTML document for PDF generation.
     */
    public function generatePrintableHtml(User $user, string $month): string
    {
        $timezone = $user->timezone ?? 'America/Sao_Paulo';
        $summary = $this->hourCalculationService->calculateMonthlySummary($user, $month);

        $rowsHtml = '';
        foreach ($summary['daily_summaries'] as $day) {
            $entries = $this->timeTrackingService->getEntriesForDate($user, $day['date']);

            $clockIn = $entries->firstWhere('type', 'CLOCK_IN');
            $breakStart = $entries->firstWhere('type', 'BREAK_START');
            $breakEnd = $entries->firstWhere('type', 'BREAK_END');
            $clockOut = $entries->firstWhere('type', 'CLOCK_OUT');

            $formatTime = fn (?TimeEntry $e) => $e
                ? Carbon::parse($e->registered_at)->setTimezone($timezone)->format('H:i')
                : '-';

            $formatDate = Carbon::parse($day['date'])->format('d/m/Y');
            $balanceColor = $day['is_positive_balance'] ? '#059669' : '#e11d48';

            $rowsHtml .= "
                <tr>
                    <td style='padding: 8px; border-bottom: 1px solid #e2e8f0; font-family: monospace;'>{$formatDate}</td>
                    <td style='padding: 8px; border-bottom: 1px solid #e2e8f0; font-family: monospace;'>{$formatTime($clockIn)}</td>
                    <td style='padding: 8px; border-bottom: 1px solid #e2e8f0; font-family: monospace;'>{$formatTime($breakStart)}</td>
                    <td style='padding: 8px; border-bottom: 1px solid #e2e8f0; font-family: monospace;'>{$formatTime($breakEnd)}</td>
                    <td style='padding: 8px; border-bottom: 1px solid #e2e8f0; font-family: monospace;'>{$formatTime($clockOut)}</td>
                    <td style='padding: 8px; border-bottom: 1px solid #e2e8f0; font-family: monospace; font-weight: bold;'>{$day['total_worked_formatted']}</td>
                    <td style='padding: 8px; border-bottom: 1px solid #e2e8f0; font-family: monospace;'>{$day['target_formatted']}</td>
                    <td style='padding: 8px; border-bottom: 1px solid #e2e8f0; font-family: monospace; font-weight: bold; color: {$balanceColor};'>{$day['balance_formatted']}</td>
                </tr>
            ";
        }

        $overallBalanceColor = $summary['is_positive_balance'] ? '#059669' : '#e11d48';

        return "
<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <title>Espelho de Ponto - {$user->name} ({$summary['month']})</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #0f172a; margin: 40px; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #0f172a; padding-bottom: 16px; margin-bottom: 24px; }
        .title { font-size: 20px; font-weight: bold; }
        .subtitle { font-size: 13px; color: #64748b; margin-top: 4px; }
        .cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .card { padding: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; }
        .card-label { font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: bold; }
        .card-value { font-size: 20px; font-weight: bold; font-family: monospace; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; }
        th { padding: 10px 8px; background: #f1f5f9; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #cbd5e1; color: #475569; }
        @media print {
            body { margin: 15mm; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class='no-print' style='margin-bottom: 20px;'>
        <button onclick='window.print()' style='background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer;'>
            🖨️ Imprimir / Salvar como PDF
        </button>
    </div>

    <div class='header'>
        <div>
            <div class='title'>ESPELHO DE PONTO MENSAL</div>
            <div class='subtitle'>Colaborador: <strong>{$user->name}</strong> ({$user->email})</div>
        </div>
        <div style='text-align: right;'>
            <div class='title'>{$summary['month_formatted']}</div>
            <div class='subtitle'>Fuso: {$timezone}</div>
        </div>
    </div>

    <div class='cards'>
        <div class='card'>
            <div class='card-label'>Total Trabalhado</div>
            <div class='card-value'>{$summary['total_worked_formatted']}</div>
        </div>
        <div class='card'>
            <div class='card-label'>Meta do Período</div>
            <div class='card-value'>{$summary['total_target_formatted']}</div>
        </div>
        <div class='card'>
            <div class='card-label'>Saldo Acumulado</div>
            <div class='card-value' style='color: {$overallBalanceColor};'>{$summary['balance_formatted']}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Entrada</th>
                <th>Início Intervalo</th>
                <th>Retorno Intervalo</th>
                <th>Saída</th>
                <th>Trabalhado</th>
                <th>Meta</th>
                <th>Saldo</th>
            </tr>
        </thead>
        <tbody>
            {$rowsHtml}
        </tbody>
    </table>

    <div style='margin-top: 55px; display: flex; justify-content: space-around; gap: 40px; page-break-inside: avoid;'>
        <div style='border-top: 1px solid #334155; width: 280px; text-align: center; padding-top: 8px;'>
            <div style='font-size: 12px; font-weight: bold; color: #0f172a;'>Assinatura do Colaborador</div>
            <div style='font-size: 11px; color: #64748b; margin-top: 2px;'>{$user->name}</div>
        </div>
        <div style='border-top: 1px solid #334155; width: 280px; text-align: center; padding-top: 8px;'>
            <div style='font-size: 12px; font-weight: bold; color: #0f172a;'>Assinatura do Gestor</div>
            <div style='font-size: 11px; color: #64748b; margin-top: 2px;'>Responsável / Gestor Imediato</div>
        </div>
    </div>

    <div style='margin-top: 35px; display: flex; justify-content: space-between; font-size: 11px; color: #64748b; page-break-inside: avoid;'>
        <div>Emitido via Gestão de Ponto em: " . Carbon::now($timezone)->format('d/m/Y H:i') . "</div>
        <div>Documento para fins de controle e conformidade de jornada</div>
    </div>
</body>
</html>
        ";
    }
}
