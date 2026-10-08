<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\VpnUser;
use App\Services\TurnstileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthAndTurnstileTest extends TestCase
{
    use RefreshDatabase;

    public function test_turnstile_service_bypasses_in_testing_env(): void
    {
        $service = new TurnstileService();
        $this->assertTrue($service->verify('any_token'));
    }

    public function test_turnstile_service_real_mock_verification(): void
    {
        config(['app.env' => 'production']);
        config(['services.turnstile.enabled' => true]);
        config(['services.turnstile.secret_key' => '1x0000000000000000000000000000000AA']);

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
                'challenge_ts' => now()->toISOString(),
                'hostname' => 'localhost',
            ], 200),
        ]);

        $service = new TurnstileService();
        $this->assertTrue($service->verify('valid_token'));

        // Reset env back
        config(['app.env' => 'testing']);
    }

    public function test_google_auth_redirect_endpoint(): void
    {
        $response = $this->get('/auth/google?context=tenant&intent=login');
        $this->assertTrue($response->isRedirect());
        $location = $response->headers->get('Location') ?? '';
        $this->assertStringContainsString('accounts.google.com', $location);
        $this->assertStringContainsString('client_id=', $location);
        $this->assertStringContainsString('75378718089', $location);
    }

    public function test_google_auth_callback_for_vpn_user_creates_and_logs_in(): void
    {
        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getId')->andReturn('google-vpn-12345');
        $abstractUser->shouldReceive('getEmail')->andReturn('vpnclient@gmail.com');
        $abstractUser->shouldReceive('getName')->andReturn('VPN Client');
        $abstractUser->shouldReceive('getNickname')->andReturn('vpnclient');
        $abstractUser->shouldReceive('getAvatar')->andReturn('https://avatar.google.com/vpnclient');

        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->withSession([
            'google_auth_state' => [
                'context' => 'vpn',
                'intent' => 'register',
            ],
        ])->get('/auth/google/callback');

        $response->assertStatus(302);
        $response->assertRedirect('/dashboard');

        $this->assertDatabaseHas('vpn_users', [
            'email' => 'vpnclient@gmail.com',
            'google_id' => 'google-vpn-12345',
            'name' => 'VPN Client',
        ]);

        $this->assertAuthenticatedAs(VpnUser::where('email', 'vpnclient@gmail.com')->first(), 'vpn');
    }

    public function test_google_auth_callback_for_existing_tenant_user_logs_in(): void
    {
        $tenant = Tenant::create([
            'name' => 'ISP Media',
            'slug' => 'ispmedia',
            'email' => 'isp@media.com',
            'phone' => '08123456789',
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Admin ISP',
            'username' => 'adminisp',
            'email' => 'admin@ispmedia.com',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getId')->andReturn('google-tenant-67890');
        $abstractUser->shouldReceive('getEmail')->andReturn('admin@ispmedia.com');
        $abstractUser->shouldReceive('getName')->andReturn('Admin ISP');
        $abstractUser->shouldReceive('getNickname')->andReturn('adminisp');
        $abstractUser->shouldReceive('getAvatar')->andReturn('https://avatar.google.com/adminisp');

        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->withSession([
            'google_auth_state' => [
                'context' => 'tenant',
                'intent' => 'login',
            ],
        ])->get('/auth/google/callback');

        $response->assertStatus(302);
        $response->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertEquals($tenant->id, session('tenant_id'));
    }

    public function test_google_auth_callback_for_new_tenant_user_redirects_to_register_with_prefill(): void
    {
        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getId')->andReturn('google-new-99999');
        $abstractUser->shouldReceive('getEmail')->andReturn('newtenant@gmail.com');
        $abstractUser->shouldReceive('getName')->andReturn('New Tenant Candidate');
        $abstractUser->shouldReceive('getNickname')->andReturn('newtenant');
        $abstractUser->shouldReceive('getAvatar')->andReturn('https://avatar.google.com/newtenant');

        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->withSession([
            'google_auth_state' => [
                'context' => 'tenant',
                'intent' => 'register',
            ],
        ])->get('/auth/google/callback');

        $response->assertStatus(302);
        $response->assertRedirect('/register');

        $sessionData = session('google_register_data');
        $this->assertNotNull($sessionData);
        $this->assertEquals('newtenant@gmail.com', $sessionData['email']);
        $this->assertEquals('New Tenant Candidate', $sessionData['name']);
        $this->assertEquals('google-new-99999', $sessionData['google_id']);
    }
}
