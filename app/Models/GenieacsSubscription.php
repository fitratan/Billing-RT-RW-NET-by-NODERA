<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class GenieacsSubscription extends Model
{
    protected static function booted(): void
    {
        static::ensureTableExists();
    }

    public static function ensureTableExists(): void
    {
        try {
            if (!Schema::hasTable('genieacs_subscriptions')) {
                Schema::create('genieacs_subscriptions', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('vpn_user_id')->nullable()->constrained('vpn_users')->cascadeOnDelete();
                    $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                    $table->string('name')->comment('Nama Instance / Nama ISP');
                    $table->string('instance_code')->unique()->comment('Kode unik instance, misal acs-ispjaya-821');
                    $table->string('username')->default('admin');
                    $table->string('password')->nullable();
                    $table->string('cwmp_url')->nullable()->comment('URL CWMP untuk ONT (Port 7547)');
                    $table->string('nbi_url')->nullable()->comment('URL NBI API untuk Billing (Port 7557)');
                    $table->string('ui_url')->nullable()->comment('URL Web GUI GenieACS (Port 3000)');
                    $table->decimal('price', 12, 2)->default(25000)->comment('Harga langganan per bulan');
                    $table->decimal('saldo_deducted', 12, 2)->default(0);
                    $table->enum('status', ['PENDING', 'ACTIVE', 'EXPIRED', 'SUSPENDED'])->default('ACTIVE');
                    $table->timestamp('expires_at')->nullable();
                    $table->timestamp('order_date')->nullable();
                    $table->timestamp('last_billed_at')->nullable();
                    $table->boolean('auto_renew')->default(false);
                    $table->text('notes')->nullable();
                    $table->timestamps();
                });
            }
        } catch (\Throwable $e) {
            // Ignore if concurrently created
        }
    }
    protected $fillable = [
        'vpn_user_id',
        'tenant_id',
        'name',
        'instance_code',
        'username',
        'password',
        'cwmp_url',
        'nbi_url',
        'ui_url',
        'price',
        'saldo_deducted',
        'status',
        'expires_at',
        'order_date',
        'last_billed_at',
        'auto_renew',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'saldo_deducted' => 'float',
            'expires_at' => 'datetime',
            'order_date' => 'datetime',
            'last_billed_at' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(VpnUser::class, 'vpn_user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE' && !$this->isExpired();
    }
}
