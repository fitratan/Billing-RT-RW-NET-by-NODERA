<?php

namespace App\Listeners;

use App\Events\RegistrationApproved;
use App\Jobs\SendApprovalWhatsAppJob;

class SendApprovalWhatsApp
{
    public function handle(RegistrationApproved $event): void
    {
        if (! $event->registration->phone) return;

        SendApprovalWhatsAppJob::dispatch($event->registration, $event->password);
    }
}
