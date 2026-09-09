<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_export_monthly_csv(): void
    {
        $user = User::factory()->create(['name' => 'Lucas Dev', 'timezone' => 'America/Sao_Paulo']);
        Sanctum::actingAs($user);

        // Registra pontos
        $this->postJson('/api/v1/time-entries', [
            'type' => 'CLOCK_IN',
            'custom_time' => '2026-09-01 08:00:00',
        ]);
        $this->postJson('/api/v1/time-entries', [
            'type' => 'CLOCK_OUT',
            'custom_time' => '2026-09-01 17:00:00',
        ]);

        $response = $this->get('/api/v1/reports/csv?month=2026-09');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename="espelho_ponto_2026-09.csv"', $response->headers->get('Content-Disposition'));

        $content = $response->getContent();
        $this->assertStringContainsString('Lucas Dev', $content);
        $this->assertStringContainsString('TOTAIS DO PERÍODO', $content);
    }

    public function test_user_can_view_printable_report(): void
    {
        $user = User::factory()->create(['name' => 'Lucas Dev', 'timezone' => 'America/Sao_Paulo']);
        Sanctum::actingAs($user);

        $response = $this->get('/api/v1/reports/print?month=2026-09');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $content = $response->getContent();
        $this->assertStringContainsString('ESPELHO DE PONTO MENSAL', $content);
        $this->assertStringContainsString('Lucas Dev', $content);
    }

    public function test_unauthenticated_user_cannot_export_reports(): void
    {
        $response = $this->getJson('/api/v1/reports/csv?month=2026-09');
        $response->assertStatus(401);
    }
}
