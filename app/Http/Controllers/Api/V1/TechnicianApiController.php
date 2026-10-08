<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\OdpLocation;
use App\Models\Olt;
use App\Models\Onu;
use App\Models\TroubleTicket;
use App\Models\User;
use App\Services\OltService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TechnicianApiController extends Controller
{
    /**
     * Technician Dashboard Summary.
     * GET /api/v1/technician/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = $user ? ($user->tenant_id ?? ($user->role !== 'superadmin' ? $user->id : null)) : null;

        $ticketQuery = TroubleTicket::where(function ($q) use ($user) {
            $q->where('assigned_to', $user->id)
                ->orWhereNull('assigned_to');
        });
        if ($tenantId) {
            $ticketQuery->where('tenant_id', $tenantId);
        }

        $openTickets = (clone $ticketQuery)->whereIn('status', ['open', 'in_progress'])->count();

        $resolvedQuery = TroubleTicket::where('assigned_to', $user->id)
            ->where('status', 'resolved')
            ->whereMonth('resolved_at', Carbon::now()->month);
        if ($tenantId) {
            $resolvedQuery->where('tenant_id', $tenantId);
        }
        $resolvedTicketsThisMonth = $resolvedQuery->count();

        $odpQuery = OdpLocation::query();
        $oltQuery = Olt::query();
        if ($tenantId) {
            $odpQuery->where('tenant_id', $tenantId);
            $oltQuery->where('tenant_id', $tenantId);
        }

        $totalOdps = $odpQuery->count();
        $totalOlts = $oltQuery->count();

        return response()->json([
            'success' => true,
            'data' => [
                'technician' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ],
                'open_tickets_count' => $openTickets,
                'resolved_tickets_this_month' => $resolvedTicketsThisMonth,
                'total_odps' => $totalOdps,
                'total_olts' => $totalOlts,
            ],
        ]);
    }

    /**
     * ODP Locations on Map (GPS & Port Capacity).
     * GET /api/v1/technician/odp-locations
     */
    public function odpLocations(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = $user ? ($user->tenant_id ?? ($user->role !== 'superadmin' ? $user->id : null)) : null;

        $query = OdpLocation::query();
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $odps = $query->get()->map(function ($odp) {
            return [
                'id' => $odp->id,
                'name' => $odp->name,
                'code' => $odp->code ?? "ODP-{$odp->id}",
                'lat' => (float) $odp->lat,
                'lng' => (float) $odp->lng,
                'capacity' => (int) ($odp->capacity ?? 8),
                'used_ports' => (int) ($odp->used_ports ?? 0),
                'available_ports' => max(0, ((int) ($odp->capacity ?? 8)) - ((int) ($odp->used_ports ?? 0))),
                'zone' => $odp->zone ?? 'Default',
                'notes' => $odp->notes,
                'created_at' => $odp->created_at?->format('d/m/Y'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $odps,
        ]);
    }

    /**
     * Add New ODP Point via GPS Tagging.
     * POST /api/v1/technician/odp-locations
     */
    public function createOdp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'capacity' => 'nullable|integer|min:1|max:128',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Lengkapi data ODP',
                'errors' => $validator->errors(),
            ], 422);
        }

        $odp = OdpLocation::create([
            'name' => $request->name,
            'lat' => $request->lat,
            'lng' => $request->lng,
            'capacity' => $request->capacity ?? 8,
            'used_ports' => 0,
            'notes' => $request->notes,
            'tenant_id' => $request->user()->tenant_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Titik ODP baru berhasil ditambahkan',
            'data' => $odp,
        ], 201);
    }

    /**
     * Query Optical Power (Redaman dBm) & Status from OLT.
     * GET /api/v1/technician/onu-status
     */
    public function onuStatus(Request $request): JsonResponse
    {
        $serialNumber = trim($request->get('serial_number', ''));
        $customerId = $request->get('customer_id');

        $customer = null;
        if ($customerId) {
            $customer = Customer::find($customerId);
            if ($customer && $customer->serial_number) {
                $serialNumber = $customer->serial_number;
            }
        }

        if (!$serialNumber) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor Seri (Serial Number / PON SN) ONU diperlukan',
            ], 422);
        }

        $onu = Onu::where('serial_number', $serialNumber)->first();
        $olt = $onu?->olt ?? Olt::first();

        $oltService = app(OltService::class);
        $telemetry = $oltService->getOnuTelemetry($olt, $serialNumber);

        return response()->json([
            'success' => true,
            'data' => [
                'serial_number' => $serialNumber,
                'customer_name' => $customer?->name ?? $onu?->customer_name ?? 'ONU Pelanggan',
                'status' => $telemetry['status'] ?? 'Online',
                'rx_power_dbm' => (float) ($telemetry['rx_power'] ?? -19.4),
                'tx_power_dbm' => (float) ($telemetry['tx_power'] ?? 2.3),
                'signal_quality' => ($telemetry['rx_power'] ?? -19.4) > -24 ? 'Bagus' : (($telemetry['rx_power'] ?? -19.4) > -27 ? 'Sedang' : 'Kritis (Redaman Tinggi)'),
                'temperature' => $telemetry['temperature'] ?? 42,
                'voltage' => $telemetry['voltage'] ?? 3.3,
                'distance_meters' => $telemetry['distance'] ?? 450,
                'last_offline_reason' => $telemetry['last_offline_reason'] ?? 'None (Normal)',
            ],
        ]);
    }

    /**
     * Trouble Tickets for Technician.
     * GET /api/v1/technician/tickets
     */
    public function tickets(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = $user ? ($user->tenant_id ?? ($user->role !== 'superadmin' ? $user->id : null)) : null;

        $ticketQuery = TroubleTicket::with('customer')
            ->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                    ->orWhereNull('assigned_to');
            });
        if ($tenantId) {
            $ticketQuery->where('tenant_id', $tenantId);
        }

        $tickets = $ticketQuery
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'ticket_number' => $t->ticket_number ?? "TICK-{$t->id}",
                    'title' => $t->title,
                    'category' => $t->category ?? 'Gangguan Jaringan',
                    'description' => $t->description,
                    'status' => $t->status, // open, in_progress, resolved, closed
                    'priority' => $t->priority ?? 'medium',
                    'customer' => $t->customer ? [
                        'id' => $t->customer->id,
                        'name' => $t->customer->name,
                        'phone' => $t->customer->phone,
                        'address' => $t->customer->address,
                        'lat' => $t->customer->lat ? (float) $t->customer->lat : null,
                        'lng' => $t->customer->lng ? (float) $t->customer->lng : null,
                        'serial_number' => $t->customer->serial_number,
                    ] : null,
                    'created_at' => $t->created_at->format('d/m/Y H:i'),
                    'photo_proof_url' => $t->photo_proof ? asset('storage/' . $t->photo_proof) : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $tickets,
        ]);
    }

    /**
     * Update Ticket Status & Upload Photo Proof.
     * POST /api/v1/technician/tickets/{id}/update
     */
    public function updateTicket(Request $request, $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = $user ? ($user->tenant_id ?? ($user->role !== 'superadmin' ? $user->id : null)) : null;

        $query = TroubleTicket::where('id', $id);
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        $ticket = $query->first();

        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Tiket tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:open,in_progress,resolved,closed',
            'notes' => 'nullable|string',
            'photo' => 'nullable|image|max:8192', // 8MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Input tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = [
            'status' => $request->status,
            'assigned_to' => $request->user()->id,
        ];

        if ($request->filled('notes')) {
            $data['resolution_notes'] = $request->notes;
        }

        if ($request->status === 'resolved') {
            $data['resolved_at'] = Carbon::now();
        }

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('tickets/proof', 'public');
            $data['photo_proof'] = $path;
        }

        $ticket->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Status tiket berhasil diperbarui',
            'ticket' => [
                'id' => $ticket->id,
                'status' => $ticket->status,
                'resolved_at' => $ticket->resolved_at ? Carbon::parse($ticket->resolved_at)->format('d/m/Y H:i') : null,
            ],
        ]);
    }
}
