<?php

namespace Tests\Feature;

use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TimeEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_clock_in_as_first_entry_of_the_day(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/time-entries', [
            'type' => 'CLOCK_IN',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'entry' => ['id', 'type', 'type_label', 'time_formatted', 'date_formatted'],
                'next_expected_type',
            ])
            ->assertJsonPath('entry.type', 'CLOCK_IN')
            ->assertJsonPath('entry.type_label', 'Entrada')
            ->assertJsonPath('next_expected_type', 'BREAK_START');

        $this->assertDatabaseHas('time_entries', [
            'user_id' => $user->id,
            'type' => 'CLOCK_IN',
        ]);
    }

    public function test_system_correctly_progresses_standard_daily_sequence(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Sao_Paulo']);
        Sanctum::actingAs($user);

        $date = Carbon::now('America/Sao_Paulo')->subDays(2)->toDateString();

        // 1. Entrada 08:00
        $res1 = $this->postJson('/api/v1/time-entries', [
            'type' => 'CLOCK_IN',
            'custom_time' => "{$date} 08:00:00",
        ]);
        $res1->assertStatus(201)->assertJsonPath('entry.type', 'CLOCK_IN');

        // 2. Almoço 12:00
        $res2 = $this->postJson('/api/v1/time-entries', [
            'type' => 'BREAK_START',
            'custom_time' => "{$date} 12:00:00",
        ]);
        $res2->assertStatus(201)->assertJsonPath('entry.type', 'BREAK_START');

        // 3. Retorno 13:00
        $res3 = $this->postJson('/api/v1/time-entries', [
            'type' => 'BREAK_END',
            'custom_time' => "{$date} 13:00:00",
        ]);
        $res3->assertStatus(201)->assertJsonPath('entry.type', 'BREAK_END');

        // 4. Saída 17:00
        $res4 = $this->postJson('/api/v1/time-entries', [
            'type' => 'CLOCK_OUT',
            'custom_time' => "{$date} 17:00:00",
        ]);
        $res4->assertStatus(201)->assertJsonPath('entry.type', 'CLOCK_OUT');

        $this->assertEquals(4, $user->timeEntries()->count());
    }

    public function test_user_cannot_clock_in_twice_consecutively(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Primeira entrada
        $this->postJson('/api/v1/time-entries', ['type' => 'CLOCK_IN']);

        // Tentativa de segunda entrada
        $response = $this->postJson('/api/v1/time-entries', ['type' => 'CLOCK_IN']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_user_cannot_start_break_without_clocking_in(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/time-entries', [
            'type' => 'BREAK_START',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_user_cannot_clock_out_while_in_break(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Sao_Paulo']);
        Sanctum::actingAs($user);

        $date = Carbon::now('America/Sao_Paulo')->subDays(2)->toDateString();

        $this->postJson('/api/v1/time-entries', [
            'type' => 'CLOCK_IN',
            'custom_time' => "{$date} 09:00:00",
        ]);

        $this->postJson('/api/v1/time-entries', [
            'type' => 'BREAK_START',
            'custom_time' => "{$date} 12:00:00",
        ]);

        // Tenta sair direto sem registrar BREAK_END
        $response = $this->postJson('/api/v1/time-entries', [
            'type' => 'CLOCK_OUT',
            'custom_time' => "{$date} 13:00:00",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_user_can_list_entries_for_date(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Sao_Paulo']);
        Sanctum::actingAs($user);

        $date = Carbon::now('America/Sao_Paulo')->toDateString();

        $this->postJson('/api/v1/time-entries', [
            'type' => 'CLOCK_IN',
            'custom_time' => "{$date} 08:30:00",
        ]);

        $response = $this->getJson("/api/v1/time-entries?date={$date}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'date',
                'timezone',
                'next_expected_type',
                'entries',
            ])
            ->assertJsonCount(1, 'entries');
    }

    public function test_user_can_update_time_entry_with_justification(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Sao_Paulo']);
        Sanctum::actingAs($user);

        $date = Carbon::now('America/Sao_Paulo')->toDateString();

        $createRes = $this->postJson('/api/v1/time-entries', [
            'type' => 'CLOCK_IN',
            'custom_time' => "{$date} 08:15:00",
        ]);

        $entryId = $createRes->json('entry.id');

        $updateRes = $this->putJson("/api/v1/time-entries/{$entryId}", [
            'time' => "{$date} 08:00:00",
            'reason' => 'Esqueci de registrar na chegada',
        ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('entry.is_edited', true)
            ->assertJsonPath('entry.edit_reason', 'Esqueci de registrar na chegada');

        $this->assertDatabaseHas('time_entries', [
            'id' => $entryId,
            'is_edited' => true,
            'edit_reason' => 'Esqueci de registrar na chegada',
        ]);
    }

    public function test_user_cannot_update_time_entry_without_reason(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Sao_Paulo']);
        Sanctum::actingAs($user);

        $date = Carbon::now('America/Sao_Paulo')->toDateString();

        $createRes = $this->postJson('/api/v1/time-entries', [
            'type' => 'CLOCK_IN',
            'custom_time' => "{$date} 08:15:00",
        ]);

        $entryId = $createRes->json('entry.id');

        $response = $this->putJson("/api/v1/time-entries/{$entryId}", [
            'time' => "{$date} 08:00:00",
            'reason' => '', // Vazio
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_user_cannot_edit_other_users_entry(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $entryA = TimeEntry::create([
            'user_id' => $userA->id,
            'type' => 'CLOCK_IN',
            'registered_at' => Carbon::now('UTC'),
        ]);

        Sanctum::actingAs($userB);

        $response = $this->putJson("/api/v1/time-entries/{$entryA->id}", [
            'time' => Carbon::now()->toDateTimeString(),
            'reason' => 'Tentativa indevida de alteração',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_own_entry(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $entry = TimeEntry::create([
            'user_id' => $user->id,
            'type' => 'CLOCK_IN',
            'registered_at' => Carbon::now('UTC'),
        ]);

        $response = $this->deleteJson("/api/v1/time-entries/{$entry->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('time_entries', [
            'id' => $entry->id,
        ]);
    }

    public function test_user_cannot_record_entry_in_future(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Sao_Paulo']);
        Sanctum::actingAs($user);

        $futureTime = Carbon::now('America/Sao_Paulo')->addHours(2)->toDateTimeString();

        $response = $this->postJson('/api/v1/time-entries', [
            'type' => 'CLOCK_IN',
            'custom_time' => $futureTime,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['custom_time']);
        $this->assertEquals(
            'Não é permitido registrar ponto para data ou horário futuro.',
            $response->json('errors.custom_time.0')
        );
    }

    public function test_user_cannot_update_entry_to_future_time(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Sao_Paulo']);
        Sanctum::actingAs($user);

        $date = Carbon::now('America/Sao_Paulo')->subDays(2)->toDateString();
        $createRes = $this->postJson('/api/v1/time-entries', [
            'type' => 'CLOCK_IN',
            'custom_time' => "{$date} 08:00:00",
        ]);
        $entryId = $createRes->json('entry.id');

        $futureTime = Carbon::now('America/Sao_Paulo')->addDay()->toDateTimeString();

        $response = $this->putJson("/api/v1/time-entries/{$entryId}", [
            'time' => $futureTime,
            'reason' => 'Tentando colocar para amanhã',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['time']);
        $this->assertEquals(
            'Não é permitido ajustar registro para data ou horário futuro.',
            $response->json('errors.time.0')
        );
    }
}
