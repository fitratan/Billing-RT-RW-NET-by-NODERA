<?php

namespace App\Events;

use App\Models\RegistrationRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RegistrationApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public RegistrationRequest $registration,
        public Tenant $tenant,
        public User $user,
        public string $password,
    ) {}
}
