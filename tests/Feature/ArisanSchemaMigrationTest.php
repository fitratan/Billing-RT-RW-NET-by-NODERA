<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ArisanSchemaMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_arisan_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('arisan_subscriptions'));
        $this->assertTrue(Schema::hasTable('arisan_groups'));
        $this->assertTrue(Schema::hasTable('arisan_members'));
        $this->assertTrue(Schema::hasTable('arisan_group_members'));
        $this->assertTrue(Schema::hasTable('arisan_periods'));
        $this->assertTrue(Schema::hasTable('arisan_payments'));
        $this->assertTrue(Schema::hasTable('arisan_draws'));
        $this->assertTrue(Schema::hasTable('arisan_cashflows'));
        $this->assertTrue(Schema::hasTable('arisan_payment_settings'));
    }
}
