<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\TimeEntryResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\HourCalculationService;
use App\Services\TimeTrackingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected TimeTrackingService $timeTrackingService,
        protected HourCalculationService $hourCalculationService
    ) {}

    /**
     * Handle user registration.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'timezone' => $validated['timezone'] ?? 'America/Sao_Paulo',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Usuário cadastrado com sucesso.',
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    /**
     * Handle user login.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais fornecidas estão incorretas.'],
            ]);
        }

        $deviceName = $validated['device_name'] ?? 'web_browser';
        // Mantém a tabela personal_access_tokens limpa e rápida
        $user->tokens()->where('name', $deviceName)->delete();
        $token = $user->createToken($deviceName)->plainTextToken;

        // Pré-carrega dados do dia para o frontend inicializar o dashboard em 0ms
        $timezone = $user->timezone ?? 'America/Sao_Paulo';
        $today = Carbon::now($timezone)->toDateString();
        $entries = $this->timeTrackingService->getEntriesForDate($user, $today);
        $nextExpectedType = $this->timeTrackingService->determineNextExpectedType($user);
        $dailySummary = $this->hourCalculationService->calculateDailySummary($user, $today);

        return response()->json([
            'message' => 'Login realizado com sucesso.',
            'user' => new UserResource($user),
            'token' => $token,
            'initial_data' => [
                'date' => $today,
                'timezone' => $timezone,
                'next_expected_type' => $nextExpectedType,
                'entries' => TimeEntryResource::collection($entries),
                'summary' => $dailySummary,
            ],
        ]);
    }

    /**
     * Handle Google authentication (login or register).
     */
    public function googleLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'name' => ['nullable', 'string', 'max:255'],
            'google_id' => ['nullable', 'string'],
            'device_name' => ['nullable', 'string'],
        ]);

        $user = User::firstOrCreate(
            ['email' => $validated['email']],
            [
                'name' => $validated['name'] ?? explode('@', $validated['email'])[0],
                'password' => Hash::make(\Illuminate\Support\Str::random(32)),
                'timezone' => 'America/Sao_Paulo',
            ]
        );

        if (empty($user->name) && !empty($validated['name'])) {
            $user->update(['name' => $validated['name']]);
        }

        $deviceName = $validated['device_name'] ?? 'google_auth';
        $user->tokens()->where('name', $deviceName)->delete();
        $token = $user->createToken($deviceName)->plainTextToken;

        $timezone = $user->timezone ?? 'America/Sao_Paulo';
        $today = Carbon::now($timezone)->toDateString();
        $entries = $this->timeTrackingService->getEntriesForDate($user, $today);
        $nextExpectedType = $this->timeTrackingService->determineNextExpectedType($user);
        $dailySummary = $this->hourCalculationService->calculateDailySummary($user, $today);

        return response()->json([
            'message' => 'Login com Google realizado com sucesso.',
            'user' => new UserResource($user),
            'token' => $token,
            'initial_data' => [
                'date' => $today,
                'timezone' => $timezone,
                'next_expected_type' => $nextExpectedType,
                'entries' => TimeEntryResource::collection($entries),
                'summary' => $dailySummary,
            ],
        ]);
    }

    /**
     * Handle user logout (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sessão encerrada com sucesso.',
        ]);
    }

    /**
     * Get authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    /**
     * Update authenticated user profile.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Perfil atualizado com sucesso.',
            'user' => new UserResource($user->fresh()),
        ]);
    }
}
