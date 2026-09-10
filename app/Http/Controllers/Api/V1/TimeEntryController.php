<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimeEntry\StoreTimeEntryRequest;
use App\Http\Requests\TimeEntry\UpdateTimeEntryRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\TimeEntry;
use App\Services\TimeTrackingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeEntryController extends Controller
{
    public function __construct(
        protected TimeTrackingService $timeTrackingService
    ) {}

    /**
     * List time entries for a given date and return next expected action.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $timezone = $user->timezone ?? 'America/Sao_Paulo';

        $date = $request->query('date', Carbon::now($timezone)->toDateString());

        $entries = $this->timeTrackingService->getEntriesForDate($user, $date);
        $nextExpectedType = $this->timeTrackingService->determineNextExpectedType($user);

        return response()->json([
            'date' => $date,
            'timezone' => $timezone,
            'next_expected_type' => $nextExpectedType,
            'entries' => TimeEntryResource::collection($entries),
        ]);
    }

    /**
     * Record a new clock in / pause / return / clock out entry.
     */
    public function store(StoreTimeEntryRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $entry = $this->timeTrackingService->recordEntry(
            $user,
            $validated['type'] ?? null,
            $validated['custom_time'] ?? null
        );

        $nextExpectedType = $this->timeTrackingService->determineNextExpectedType($user);

        return response()->json([
            'message' => 'Ponto registrado com sucesso.',
            'entry' => new TimeEntryResource($entry),
            'next_expected_type' => $nextExpectedType,
        ], 201);
    }

    /**
     * Update an existing time entry with audit justification.
     */
    public function update(UpdateTimeEntryRequest $request, TimeEntry $timeEntry): JsonResponse
    {
        $user = $request->user();

        // Isolamento de segurança: impedir IDOR se o registro não for do usuário
        if ($timeEntry->user_id !== $user->id) {
            return response()->json([
                'message' => 'Você não tem permissão para editar este registro.',
            ], 403);
        }

        $validated = $request->validated();

        $updatedEntry = $this->timeTrackingService->updateEntry(
            $timeEntry,
            $user,
            $validated['time'],
            $validated['reason'] ?? null
        );

        return response()->json([
            'message' => 'Registro de ponto atualizado com sucesso.',
            'entry' => new TimeEntryResource($updatedEntry),
        ]);
    }

    /**
     * Delete a time entry.
     */
    public function destroy(Request $request, TimeEntry $timeEntry): JsonResponse
    {
        $user = $request->user();

        if ($timeEntry->user_id !== $user->id) {
            return response()->json([
                'message' => 'Você não tem permissão para remover este registro.',
            ], 403);
        }

        $this->timeTrackingService->deleteEntry($timeEntry, $user);

        return response()->json([
            'message' => 'Registro de ponto removido com sucesso.',
        ]);
    }
}
