<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->create(['role' => 'superadmin']);
        $this->actingAs($this->superAdmin);
    }

    public function test_index_lists_expenses(): void
    {
        Expense::factory()->count(3)->create(['tenant_id' => null]);
        $response = $this->get('/superadmin/expenses');
        $response->assertStatus(200);
    }

    public function test_can_create_expense(): void
    {
        $response = $this->post('/superadmin/expenses/store', [
            'description' => 'Listrik bulan ini',
            'amount' => 1500000,
            'category' => 'Operasional',
            'date' => now()->format('Y-m-d'),
        ]);
        $response->assertRedirect('/superadmin/expenses');
        $this->assertDatabaseHas('expenses', ['description' => 'Listrik bulan ini']);
    }

    public function test_can_delete_expense(): void
    {
        $expense = Expense::factory()->create(['tenant_id' => null]);

        $response = $this->post("/superadmin/expenses/{$expense->id}/delete");
        $response->assertRedirect('/superadmin/expenses');

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }
}
