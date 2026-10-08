<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * List all agents
     */
    public function index()
    {
        $agents = Agent::orderBy('created_at', 'desc')->get();
        return view('admin.agents.index', compact('agents'));
    }

    /**
     * Add new agent
     */
    public function add(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:agents,username',
            'password' => 'required|string|min:4',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'commission_type' => 'required|in:percent,fixed',
            'commission_value' => 'required|numeric|min:0',
        ]);

        Agent::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'password' => bcrypt($data['password']),
            'phone' => $data['phone'] ?? '',
            'email' => $data['email'] ?? '',
            'commission_type' => $data['commission_type'],
            'commission_value' => $data['commission_value'],
            'balance' => 0,
            'is_active' => true,
            'tenant_id' => session('tenant_id'),
        ]);

        return redirect()->to('/admin/agents')->with('msg', 'Agen berhasil ditambahkan');
    }

    /**
     * Edit agent
     */
    public function edit(Request $request, $id)
    {
        $agent = Agent::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:agents,username,' . $id,
            'password' => 'nullable|string|min:4',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'commission_type' => 'required|in:percent,fixed',
            'commission_value' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);

        $update = [
            'name' => $data['name'],
            'username' => $data['username'],
            'phone' => $data['phone'] ?? '',
            'email' => $data['email'] ?? '',
            'commission_type' => $data['commission_type'],
            'commission_value' => $data['commission_value'],
            'is_active' => $data['is_active'],
        ];

        if (!empty($data['password'])) {
            $update['password'] = bcrypt($data['password']);
        }

        $agent->update($update);

        return redirect()->to('/admin/agents')->with('msg', 'Data agen berhasil diperbarui');
    }

    /**
     * Delete agent
     */
    public function delete($id)
    {
        $agent = Agent::findOrFail($id);
        $agent->delete();

        return redirect()->to('/admin/agents')->with('msg', 'Agen berhasil dihapus');
    }

    /**
     * Topup balance for an agent
     */
    public function topup(Request $request, $id)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'note' => 'nullable|string|max:500',
        ]);

        $agent = Agent::findOrFail($id);
        $agent->increment('balance', $data['amount']);

        // Log the transaction
        DB::table('agent_transactions')->insert([
            'agent_id' => $agent->id,
            'type' => 'topup',
            'amount' => $data['amount'],
            'balance_before' => $agent->balance - $data['amount'],
            'balance_after' => $agent->balance,
            'note' => 'Admin: ' . ($data['note'] ?? 'Topup saldo'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->to('/admin/agents')->with('msg', 'Saldo agen berhasil ditambah: Rp ' . number_format($data['amount'], 0, ',', '.'));
    }
}
