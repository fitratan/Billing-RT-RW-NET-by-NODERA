<?php

namespace App\Events;

use App\Models\Customer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerIsolatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Customer $customer;
    public string $routerName;
    public string $timestamp;
    public ?string $actorName;

    public function __construct(Customer $customer, ?string $actorName = 'Sistem Auto-Isolir')
    {
        $this->customer   = $customer;
        $this->routerName = $customer->router->name ?? 'Unknown Router';
        $this->timestamp  = now()->format('H:i:s');
        $this->actorName  = $actorName;
    }

    /**
     * Broadcast channel for admin dashboard.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new Channel('admin-notifications'),
        ];

        if (!empty($this->customer->tenant_id)) {
            $channels[] = new Channel("tenant.{$this->customer->tenant_id}");
        }

        return $channels;
    }

    /**
     * Event name for frontend listener (e.g. echo.listen('.customer.isolated')).
     */
    public function broadcastAs(): string
    {
        return 'customer.isolated';
    }

    /**
     * Lightweight payload delivered to real-time client.
     */
    public function broadcastWith(): array
    {
        $unpaidInvoice = $this->customer->invoices()
            ->where('paid', false)
            ->latest('due_date')
            ->first();

        $amountFormatted = $unpaidInvoice 
            ? 'Rp ' . number_format((float) $unpaidInvoice->amount, 0, ',', '.')
            : 'Rp ' . number_format((float) ($this->customer->package?->price ?? 0), 0, ',', '.');

        return [
            'customer_id'     => $this->customer->id,
            'customer_name'   => $this->customer->name,
            'pppoe_username'  => $this->customer->pppoe_username ?? $this->customer->ip_address ?? '-',
            'connection_type' => $this->customer->connection_type ?? 'pppoe',
            'router_id'       => $this->customer->router_id,
            'router_name'     => $this->routerName,
            'phone'           => $this->customer->phone,
            'amount'          => $amountFormatted,
            'time'            => $this->timestamp,
            'actor'           => $this->actorName,
            'message'         => "Pelanggan {$this->customer->name} ({$this->customer->pppoe_username}) berhasil diisolir pada router {$this->routerName}.",
        ];
    }
}
