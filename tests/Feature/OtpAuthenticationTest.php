<?php

namespace Tests\Feature;

use App\Models\OtpChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_login_creates_a_user_and_persists_failed_attempts(): void
    {
        config(['services.otp.test_mode' => true, 'services.infinito.enabled' => false]);

        $request = $this->postJson('/api/auth/otp/request', [
            'phone' => '+15551234567',
            'name' => 'Test User',
        ])->assertOk();

        $otp = $request->json('debug_otp');
        $this->assertNotEmpty($otp);

        $this->postJson('/api/auth/otp/verify', [
            'phone' => '+15551234567',
            'otp' => '000000',
        ])->assertUnprocessable();

        $this->assertSame(1, OtpChallenge::where('phone', '+15551234567')->value('attempts'));

        $this->postJson('/api/auth/otp/verify', [
            'phone' => '+15551234567',
            'otp' => $otp,
        ])->assertOk()
            ->assertJsonPath('user.phone', '+15551234567')
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'phone']]);
    }
}