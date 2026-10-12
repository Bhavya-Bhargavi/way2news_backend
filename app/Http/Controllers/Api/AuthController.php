<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Services\OtpSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class AuthController extends Controller
{
    public function requestOtp(Request $request, OtpSender $sender): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+?[1-9][0-9]{7,14}$/'],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        $localTestMode = config('services.otp.test_mode') && app()->environment(['local', 'testing']);
        $testPhone = (string) config('services.otp.test_phone');
        $authorizedProductionTestMode = config('services.otp.test_mode')
            && app()->environment('production')
            && $testPhone !== ''
            && hash_equals($testPhone, $validated['phone']);

        if ($authorizedProductionTestMode && User::where('phone', $testPhone)->where('is_admin', true)->exists()) {
            return response()->json(['message' => 'Test OTP is disabled for admin accounts.'], 403);
        }

        $testMode = $localTestMode || $authorizedProductionTestMode;
        $code = (string) random_int(100000, 999999);

        $challenge = OtpChallenge::updateOrCreate(
            ['phone' => $validated['phone']],
            [
                'name' => $validated['name'] ?? null,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes((int) config('services.otp.expiry_minutes', 5)),
                'attempts' => 0,
                'consumed_at' => null,
            ],
        );

        if (! $testMode) {
            try {
                $sender->send($challenge->phone, $code);
            } catch (Throwable $exception) {
                report($exception);
                $challenge->delete();

                return response()->json(['message' => 'OTP delivery is unavailable. Please try again later.'], 503);
            }
        }

        $response = ['message' => 'OTP requested.'];
        if ($localTestMode) {
            $response['debug_otp'] = $code;
        } elseif ($authorizedProductionTestMode) {
            $response['message'] = 'Test OTP generated; no SMS was sent.';
            $response['otp'] = $code;
        }

        return response()->json($response, 200);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+?[1-9][0-9]{7,14}$/'],
            'otp' => ['required', 'digits:6'],
        ]);

        return DB::transaction(function () use ($validated): JsonResponse {
            $challenge = OtpChallenge::where('phone', $validated['phone'])->lockForUpdate()->first();

            if (! $challenge || $challenge->consumed_at || $challenge->expires_at->isPast()) {
                return response()->json(['message' => 'The OTP is invalid or expired.'], 422);
            }

            if ($challenge->attempts >= 5) {
                return response()->json(['message' => 'Too many attempts. Request a new OTP.'], 422);
            }

            if (! Hash::check($validated['otp'], $challenge->code_hash)) {
                $challenge->increment('attempts');
                return response()->json(['message' => 'The OTP is invalid or expired.'], 422);
            }

            $challenge->update(['consumed_at' => now()]);
            $user = User::firstOrCreate(
                ['phone' => $challenge->phone],
                ['name' => $challenge->name ?: $challenge->phone],
            );
            $token = $user->createToken('android')->plainTextToken;

            return response()->json([
                'message' => 'Login successful.',
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => new UserResource($user),
            ]);
        });
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }
}