<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->create(['role' => 'superadmin']);
        $this->actingAs($this->superAdmin);
    }

    public function test_index_lists_invoices(): void
    {
        Invoice::factory()->count(3)->create(['tenant_id' => null]);
        $response = $this->get('/superadmin/invoices');
        $response->assertStatus(200);
    }

    public function test_can_mark_invoice_as_paid(): void
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => null,
            'paid' => false,
            'status' => 'pending',
        ]);

        $response = $this->post("/superadmin/invoices/pay/{$invoice->id}");
        $response->assertRedirect('/superadmin/invoices');

        $invoice->refresh();
        $this->assertTrue($invoice->paid);
        $this->assertEquals('paid', $invoice->status);
    }

    public function test_can_delete_invoice(): void
    {
        $invoice = Invoice::factory()->create(['tenant_id' => null]);

        $response = $this->post("/superadmin/invoices/delete/{$invoice->id}");
        $response->assertRedirect('/superadmin/invoices');

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }
}
