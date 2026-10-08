<?php

namespace App\Providers;

use App\Events\RegistrationApproved;
use App\Listeners\LogRegistrationAudit;
use App\Listeners\SendApprovalWhatsApp;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
