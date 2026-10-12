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

    public function test_production_random_otp_is_limited_to_the_configured_non_admin_phone(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.env' => 'production',
            'services.otp.test_mode' => true,
            'services.otp.test_phone' => '+15550002222',
        ]);

        $this->postJson('/api/auth/otp/request', [
            'phone' => '+15550003333',
            'name' => 'Other User',
        ])
            ->assertStatus(503)
            ->assertJsonMissingPath('debug_otp');

        $otpResponse = $this->postJson('/api/auth/otp/request', [
            'phone' => '+15550002222',
            'name' => 'Allowed Test User',
        ])
            ->assertOk()
            ->assertJsonMissingPath('debug_otp')
            ->assertJsonPath('message', 'Test OTP generated; no SMS was sent.')
            ->assertJsonStructure(['otp']);

        $otp = $otpResponse->json('otp');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);

        $this->postJson('/api/auth/otp/verify', [
            'phone' => '+15550002222',
            'otp' => $otp,
        ])->assertOk()
            ->assertJsonPath('user.phone', '+15550002222')
            ->assertJsonStructure(['token', 'token_type', 'user']);

        $this->assertFalse((bool) \App\Models\User::where('phone', '+15550002222')->value('is_admin'));
    }

    public function test_production_static_otp_cannot_be_used_for_admin_accounts(): void
    {
        $this->app['env'] = 'production';
        config([
            'services.otp.test_mode' => true,
            'services.otp.test_phone' => '+15550002222',
        ]);
        \App\Models\User::create([
            'name' => 'Admin',
            'phone' => '+15550002222',
            'is_admin' => true,
        ]);

        $this->postJson('/api/auth/otp/request', [
            'phone' => '+15550002222',
        ])->assertForbidden()
            ->assertJsonMissingPath('debug_otp');
    }
}