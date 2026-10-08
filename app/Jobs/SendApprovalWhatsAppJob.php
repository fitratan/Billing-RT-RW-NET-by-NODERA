<?php

namespace App\Jobs;

use App\Models\RegistrationRequest;
use App\Services\WhatsappService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendApprovalWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(
        public RegistrationRequest $registration,
        public string $password,
    ) {}

    public function handle(): void
    {
        if (! $this->registration->phone) return;

        $wa = WhatsappService::forSuperadmin();
        if (! $wa->isEnabled()) return;

        $wa->sendRegistrationApproved($this->registration, null, null, $this->password);

        Log::info('WhatsApp approval sent', ['phone' => $this->registration->phone, 'slug' => $this->registration->slug]);
    }
}
