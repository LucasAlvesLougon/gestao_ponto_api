<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HourCalcTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_summary_calculates_correct_worked_hours_and_positive_balance(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Sao_Paulo']);
        Sanctum::actingAs($user);

        $date = '2026-09-01';

        // 1. Entrada 08:00
        $this->postJson('/api/v1/time-entries', [
            'type' => 'CLOCK_IN',
            'custom_time' => "{$date} 08:00:00",
        ]);

        // 2. Almoço 12:00 (4h trabalhadas = 240m)
        $this->postJson('/api/v1/time-entries', [
            'type' => 'BREAK_START',
            'custom_time' => "{$date} 12:00:00",
        ]);

        // 3. Retorno 13:00 (1h de pausa)
        $this->postJson('/api/v1/time-entries', [
            'type' => 'BREAK_END',
            'custom_time' => "{$date} 13:00:00",
        ]);

        // 4. Saída 17:30 (4h30 trabalhadas = 270m)
        // Total trabalhado: 240 + 270 = 510m (08h 30m)
        // Meta: 8h (480m). Saldo: +30m
        $this->postJson('/api/v1/time-entries', [
            'type' => 'CLOCK_OUT',
            'custom_time' => "{$date} 17:30:00",
        ]);

        $response = $this->getJson("/api/v1/summary/daily?date={$date}");

        $response->assertStatus(200)
            ->assertJsonPath('summary.total_worked_minutes', 510)
            ->assertJsonPath('summary.total_worked_formatted', '08h 30m')
            ->assertJsonPath('summary.target_minutes', 480)
            ->assertJsonPath('summary.balance_minutes', 30)
            ->assertJsonPath('summary.balance_formatted', '+00h 30m')
            ->assertJsonPath('summary.is_positive_balance', true)
            ->assertJsonPath('summary.is_complete', true);
    }

    public function test_daily_summary_calculates_negative_balance(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Sao_Paulo']);
        Sanctum::actingAs($user);

        $date = '2026-09-02';

        // Entrada 09:00 e Saída 16:00 (7 horas = 420m). Meta: 8h (480m) -> Saldo: -60m (-01h 00m)
        $this->postJson('/api/v1/time-entries', [
            'type' => 'CLOCK_IN',
            'custom_time' => "{$date} 09:00:00",
        ]);

        $this->postJson('/api/v1/time-entries', [
            'type' => 'CLOCK_OUT',
            'custom_time' => "{$date} 16:00:00",
        ]);

        $response = $this->getJson("/api/v1/summary/daily?date={$date}");

        $response->assertStatus(200)
            ->assertJsonPath('summary.total_worked_minutes', 420)
            ->assertJsonPath('summary.balance_minutes', -60)
            ->assertJsonPath('summary.balance_formatted', '-01h 00m')
            ->assertJsonPath('summary.is_positive_balance', false);
    }

    public function test_monthly_summary_aggregates_total_worked_hours_and_balance(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Sao_Paulo']);
        Sanctum::actingAs($user);

        $month = '2026-08';

        // Dia 1: 8 horas exatas (480m)
        $this->postJson('/api/v1/time-entries', ['type' => 'CLOCK_IN', 'custom_time' => '2026-08-10 08:00:00']);
        $this->postJson('/api/v1/time-entries', ['type' => 'CLOCK_OUT', 'custom_time' => '2026-08-10 16:00:00']);

        // Dia 2: 9 horas (540m)
        $this->postJson('/api/v1/time-entries', ['type' => 'CLOCK_IN', 'custom_time' => '2026-08-11 08:00:00']);
        $this->postJson('/api/v1/time-entries', ['type' => 'CLOCK_OUT', 'custom_time' => '2026-08-11 17:00:00']);

        $response = $this->getJson("/api/v1/summary/monthly?month={$month}");

        $response->assertStatus(200)
            ->assertJsonPath('summary.month', $month)
            ->assertJsonPath('summary.days_worked_count', 2)
            ->assertJsonPath('summary.total_worked_minutes', 1020) // 480 + 540
            ->assertJsonPath('summary.total_target_minutes', 960)  // 2 * 480
            ->assertJsonPath('summary.balance_minutes', 60)       // +60m (+01h 00m)
            ->assertJsonPath('summary.balance_formatted', '+01h 00m')
            ->assertJsonPath('summary.is_positive_balance', true);
    }

    public function test_user_can_customize_daily_target_hours(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Sao_Paulo']);
        Sanctum::actingAs($user);

        // Salvar meta personalizada de 6 horas diárias
        $scheduleRes = $this->postJson('/api/v1/work-schedules', [
            'daily_target_hours' => 6.0,
            'break_duration' => '00:30',
        ]);

        $scheduleRes->assertStatus(201)
            ->assertJsonPath('schedule.daily_target_hours', 6);

        // Verificar no summary
        $date = '2026-09-03';
        $this->postJson('/api/v1/time-entries', ['type' => 'CLOCK_IN', 'custom_time' => "{$date} 09:00:00"]);
        $this->postJson('/api/v1/time-entries', ['type' => 'CLOCK_OUT', 'custom_time' => "{$date} 15:00:00"]); // 6 horas

        $response = $this->getJson("/api/v1/summary/daily?date={$date}");

        $response->assertStatus(200)
            ->assertJsonPath('summary.target_minutes', 360) // 6 * 60
            ->assertJsonPath('summary.total_worked_minutes', 360)
            ->assertJsonPath('summary.balance_minutes', 0)
            ->assertJsonPath('summary.is_positive_balance', true);
    }
}
