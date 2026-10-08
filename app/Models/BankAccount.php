<?php
namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    use TenantAware;

    protected $fillable = ['bank_name', 'account_number', 'account_name', 'is_active', 'sort_order', 'tenant_id'];
    protected function casts(): array { return ['is_active' => 'boolean', 'sort_order' => 'integer']; }
}
