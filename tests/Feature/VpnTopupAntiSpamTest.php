<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\VpnTopupRequest;
use App\Models\VpnUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VpnTopupAntiSpamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        BankAccount::create([
            'tenant_id' => null,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_name' => 'Admin Super',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_user_can_create_topup_when_no_pending_request_exists(): void
    {
        $user = VpnUser::create([
            'name' => 'Test Member',
            'username' => 'testmember',
            'email' => 'testmember@example.com',
            'password' => Hash::make('password'),
            'saldo' => 0,
        ]);

        $response = $this->actingAs($user, 'vpn')->get('http://billing.example.com/topup/create');
        $response->assertStatus(200);

        $storeResponse = $this->actingAs($user, 'vpn')->post('http://billing.example.com/topup/store', [
            'amount' => 50000,
            'bank' => 'BCA',
        ]);

        $storeResponse->assertRedirectContains('/topup/confirm/');
        $this->assertDatabaseHas('vpn_topup_requests', [
            'vpn_user_id' => $user->id,
            'amount' => 50000,
            'status' => 'pending',
        ]);
    }

    public function test_user_is_blocked_from_creating_new_topup_when_pending_request_exists(): void
    {
        $user = VpnUser::create([
            'name' => 'Spam Tester',
            'username' => 'spamtester',
            'email' => 'spamtester@example.com',
            'password' => Hash::make('password'),
            'saldo' => 0,
        ]);

        $existingPending = VpnTopupRequest::create([
            'vpn_user_id' => $user->id,
            'invoice_number' => 'TOP/202609/0001/1234',
            'amount' => 50000,
            'bank_destination' => 'BCA',
            'status' => 'pending',
        ]);

        // Visiting /topup/create should redirect to confirm page with error
        $createResponse = $this->actingAs($user, 'vpn')->get('http://billing.example.com/topup/create');
        $createResponse->assertRedirect('/topup/confirm/' . $existingPending->id);
        $createResponse->assertSessionHas('error');

        // Submitting /topup/store should also redirect to confirm page and not create new record
        $storeResponse = $this->actingAs($user, 'vpn')->post('http://billing.example.com/topup/store', [
            'amount' => 100000,
            'bank' => 'BCA',
        ]);

        $storeResponse->assertRedirect('/topup/confirm/' . $existingPending->id);
        $storeResponse->assertSessionHas('error');

        $this->assertEquals(1, VpnTopupRequest::where('vpn_user_id', $user->id)->count());
    }

    public function test_user_can_create_new_topup_after_previous_pending_request_is_resolved(): void
    {
        $user = VpnUser::create([
            'name' => 'Resolved User',
            'username' => 'resolveduser',
            'email' => 'resolved@example.com',
            'password' => Hash::make('password'),
            'saldo' => 0,
        ]);

        $topup = VpnTopupRequest::create([
            'vpn_user_id' => $user->id,
            'invoice_number' => 'TOP/202609/0002/5678',
            'amount' => 50000,
            'bank_destination' => 'BCA',
            'status' => 'pending',
        ]);

        // Simulate admin rejection
        $topup->update(['status' => 'rejected']);

        // Now user should be allowed to create a new topup
        $createResponse = $this->actingAs($user, 'vpn')->get('http://billing.example.com/topup/create');
        $createResponse->assertStatus(200);

        $storeResponse = $this->actingAs($user, 'vpn')->post('http://billing.example.com/topup/store', [
            'amount' => 100000,
            'bank' => 'BCA',
        ]);

        $storeResponse->assertRedirectContains('/topup/confirm/');
        $this->assertEquals(2, VpnTopupRequest::where('vpn_user_id', $user->id)->count());
        $this->assertEquals(1, VpnTopupRequest::where('vpn_user_id', $user->id)->where('status', 'pending')->count());
    }
}
