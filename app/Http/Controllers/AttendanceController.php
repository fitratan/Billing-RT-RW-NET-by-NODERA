<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show attendance report with filters
     */
    public function index(Request $request)
    {
        $date = $request->get('date', now()->format('Y-m-d'));

        $attendances = Attendance::with('user')
            ->where('date', $date)
            ->orderBy('check_in', 'desc')
            ->get();

        $stats = [
            'total' => $attendances->count(),
            'checked_in' => $attendances->whereNull('check_out')->count(),
            'checked_out' => $attendances->whereNotNull('check_out')->count(),
        ];

        $tenantId = \App\Models\Scopes\TenantScope::currentTenantId() ?? session('tenant_id') ?? auth()->user()?->tenant_id;
        $users = User::whereIn('role', ['admin', 'technician'])->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->orderBy('name')->get();

        return Inertia::render('Admin/Attendance', [
            'date' => $date,
            'stats' => $stats,
            'users' => $users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'role' => $u->role]),
            'attendances' => $attendances->map(fn ($a) => [
                'id' => $a->id, 'user' => $a->user?->name, 'role' => $a->user?->role,
                'check_in' => $a->check_in?->toIso8601String(), 'check_out' => $a->check_out?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Check-in a user (admin can do it for any user)
     */
    public function checkin(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'notes' => 'nullable|string|max:500',
            'date' => 'required|date',
        ]);

        $user = User::where('tenant_id', session('tenant_id'))->find($data['user_id']);
        if (!$user) {
            return redirect()->to('/admin/attendance?date=' . $data['date'])
                ->with('error', 'User tidak ditemukan dalam tenant ini.');
        }

        // Check if already checked in today
        $existing = Attendance::where('user_id', $data['user_id'])
            ->where('date', $data['date'])
            ->first();

        if ($existing) {
            return redirect()->to('/admin/attendance?date=' . $data['date'])
                ->with('error', 'User ini sudah check-in hari ini.');
        }

        Attendance::create([
            'user_id' => $data['user_id'],
            'check_in' => now(),
            'date' => $data['date'],
            'notes' => $data['notes'] ?? '',
            'tenant_id' => session('tenant_id'),
        ]);

        return redirect()->to('/admin/attendance?date=' . $data['date'])
            ->with('msg', 'Check-in berhasil dicatat');
    }

    /**
     * Check-out a user
     */
    public function checkout(Request $request, $id)
    {
        $data = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $attendance = Attendance::findOrFail($id);

        if ($attendance->check_out) {
            return redirect()->back()->with('error', 'User ini sudah check-out.');
        }

        $attendance->update([
            'check_out' => now(),
            'notes' => $data['notes'] ?? $attendance->notes,
        ]);

        return redirect()->back()->with('msg', 'Check-out berhasil dicatat');
    }

    /**
     * Get weekly/monthly report
     */
    public function report(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $attendances = Attendance::with('user')
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->orderBy('date', 'desc')
            ->orderBy('check_in', 'desc')
            ->get()
            ->groupBy('user_id');

        $users = User::whereIn('role', ['admin', 'technician'])->where('tenant_id', session('tenant_id'))->orderBy('name')->get();

        return view('admin.attendance.report', compact('attendances', 'users', 'startDate', 'endDate'));
    }
}
