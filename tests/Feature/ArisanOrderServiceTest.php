<?php

namespace Tests\Feature;

use App\Models\ArisanSubscription;
use App\Models\VpnUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ArisanOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_order_page(): void
    {
        $user = VpnUser::factory()->create(['saldo' => 50000]);
        $this->actingAs($user);

        $response = $this->get(route('member.arisan.order'));
        $response->assertStatus(200);
        $response->assertSee('Pembukuan Arisan');
    }

    public function test_user_can_view_index_page(): void
    {
        $user = VpnUser::factory()->create(['saldo' => 50000]);
        $sub = ArisanSubscription::factory()->create([
            'vpn_user_id' => $user->id,
            'subdomain' => 'arisan-keluarga',
            'business_name' => 'Arisan Keluarga',
        ]);
        $this->actingAs($user);

        $response = $this->get(route('member.arisan.index'));
        $response->assertStatus(200);
        $response->assertSee('arisan-keluarga');
        $response->assertSee('Arisan Keluarga');
    }

    public function test_user_with_insufficient_balance_cannot_order(): void
    {
        $user = VpnUser::factory()->create(['saldo' => 5000]);
        $this->actingAs($user);

        $response = $this->post(route('member.arisan.order.submit'), [
            'business_name' => 'Arisan RT 05',
            'subdomain' => 'arisan-rt05',
            'admin_password' => 'secret123',
            'auto_renew' => true,
        ]);

        $response->assertSessionHasErrors(['saldo']);
        $this->assertDatabaseMissing('arisan_subscriptions', [
            'subdomain' => 'arisan-rt05',
        ]);
        $this->assertEquals(5000, $user->fresh()->saldo);
    }

    public function test_user_can_order_arisan_subscription(): void
    {
        $user = VpnUser::factory()->create(['saldo' => 50000]);
        $this->actingAs($user);

        $response = $this->post(route('member.arisan.order.submit'), [
            'business_name' => 'Arisan Mawar',
            'subdomain' => 'arisan-mawar',
            'admin_password' => 'secret123',
            'auto_renew' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('arisan_subscriptions', [
            'subdomain' => 'arisan-mawar',
            'business_name' => 'Arisan Mawar',
            'status' => 'ACTIVE',
            'auto_renew' => true,
        ]);

        $sub = ArisanSubscription::where('subdomain', 'arisan-mawar')->first();
        $this->assertNotNull($sub);
        $this->assertTrue(Hash::check('secret123', $sub->admin_password_hash));
        $this->assertEquals(40000, $user->fresh()->saldo);
    }

    public function test_user_can_toggle_auto_renew(): void
    {
        $user = VpnUser::factory()->create(['saldo' => 50000]);
        $sub = ArisanSubscription::factory()->create([
            'vpn_user_id' => $user->id,
            'auto_renew' => false,
        ]);
        $this->actingAs($user);

        $response = $this->post(route('member.arisan.toggle-auto-renew', $sub));
        $response->assertRedirect();
        $this->assertTrue($sub->fresh()->auto_renew);

        $response = $this->post(route('member.arisan.toggle-auto-renew', $sub));
        $response->assertRedirect();
        $this->assertFalse($sub->fresh()->auto_renew);
    }

    public function test_user_can_manually_renew(): void
    {
        $user = VpnUser::factory()->create(['saldo' => 50000]);
        $sub = ArisanSubscription::factory()->create([
            'vpn_user_id' => $user->id,
            'price' => 10000,
            'expires_at' => now()->addDays(5),
            'status' => 'ACTIVE',
        ]);
        $this->actingAs($user);

        $response = $this->post(route('member.arisan.renew', $sub));
        $response->assertRedirect();

        $this->assertEquals(40000, $user->fresh()->saldo);
        $this->assertTrue($sub->fresh()->expires_at->gt(now()->addDays(30)));
    }
}
