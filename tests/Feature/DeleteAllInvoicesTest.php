<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteAllInvoicesTest extends TestCase
{
    use RefreshDatabase;

    private ?Tenant $t = null;

    private function tenant(): Tenant
    {
        if ($this->t) return $this->t;
        $tenant = Tenant::create(['name' => 'My ISP', 'slug' => 'myisp', 'is_active' => true, 'expired_at' => now()->addYear()]);
        $admin = User::create([
            'name' => 'Tenant Admin', 'username' => 'admin', 'email' => 'a@myisp.local',
            'password' => bcrypt('secret'), 'role' => 'admin', 'tenant_id' => $tenant->id, 'is_active' => true,
        ]);
        session(['tenant_id' => $tenant->id]);
        $this->actingAs($admin);
        $this->t = $tenant;
        return $tenant;
    }

    private function invoice(string $status): Invoice
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant()->id,
            'name' => 'Budi',
            'code' => 'C-' . uniqid(),
            'isolation_date' => '20',
            'status' => 'active',
        ]);
        return Invoice::create([
            'tenant_id' => $this->tenant()->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-' . uniqid(),
            'customer_name' => 'Budi',
            'amount' => 100000,
            'status' => $status,
            'paid' => $status === 'paid',
            'due_date' => now()->addDays(30),
            'period' => now()->format('Y-m'),
        ]);
    }

    public function test_delete_all_removes_only_pending_and_cancelled(): void
    {
        $pending = $this->invoice('pending');
        $cancelled = $this->invoice('cancelled');
        $paid = $this->invoice('paid');

        $this->post('/admin/billing/delete-all-invoices')
            ->assertSessionHasNoErrors();

        $this->assertNull($pending->fresh());
        $this->assertNull($cancelled->fresh());
        $this->assertNotNull($paid->fresh(), 'Invoice lunas tidak boleh terhapus');
    }
}
