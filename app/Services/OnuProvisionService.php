<?php

namespace App\Services;

use App\Models\Olt;
use App\Models\Onu;
use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SSH2;

class OnuProvisionService
{
    /**
     * Backward-compatible SSH connect method.
     */
    public function connect(Olt $olt): ?SSH2
    {
        try {
            $ssh = new SSH2($olt->host, $olt->port ?? 22, 10);
            if (!$ssh->login($olt->username ?: 'admin', $olt->password ?: '')) {
                Log::error("ONUProvision: SSH login failed for {$olt->name}");
                return null;
            }
            $ssh->setTimeout(10);
            return $ssh;
        } catch (\Exception $e) {
            Log::error("ONUProvision: Connection error to {$olt->name}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Open a unified CLI session (Telnet / SSH)
     */
    public function openCliSession(Olt $olt): ?OltCliSession
    {
        $session = new OltCliSession($olt);
        if ($session->connect()) {
            return $session;
        }
        Log::warning("ONUProvision: Failed to establish Telnet or SSH CLI session for OLT {$olt->name} ({$olt->host})");
        return null;
    }

    /**
     * Scan for unconfigured ONUs on the OLT (Primary method invoked by OltController)
     */
    public function findUnconfiguredOnus(Olt $olt, string $pon = '1/1/1'): array
    {
        return $this->scanUnconfigured($olt, $pon);
    }

    /**
     * Scan for unconfigured ONUs based on OLT model (Alias method)
     */
    public function scanUnconfigured(Olt $olt, string $pon = '1/1/1'): array
    {
        $cli = $this->openCliSession($olt);
        if (!$cli) {
            return [];
        }

        $onus = [];
        $model = strtolower($olt->model ?? 'generic');

        try {
            switch ($model) {
                case 'zte':
                    $onus = $this->zteScan($cli, $pon);
                    break;
                case 'huawei':
                    $onus = $this->huaweiScan($cli, $pon);
                    break;
                case 'vsol':
                    $onus = $this->vsolScan($cli, $pon);
                    break;
                case 'hioso':
                    $onus = $this->hiosoScan($cli, $pon);
                    break;
                case 'hsgq':
                    $onus = $this->hsgqScan($cli, $pon);
                    break;
                case 'cdata':
                case 'c-data':
                    $onus = $this->cdataScan($cli, $pon);
                    break;
                case 'fiberhome':
                    $onus = $this->fiberhomeScan($cli, $pon);
                    break;
                case 'bdcom':
                    $onus = $this->bdcomScan($cli, $pon);
                    break;
                default:
                    $onus = $this->genericScan($cli, $pon);
                    break;
            }
        } catch (\Exception $e) {
            Log::error("ONUProvision: Scan error on {$olt->name}: {$e->getMessage()}");
        } finally {
            $cli->disconnect();
        }

        // Filter ghost MAC addresses and normalize structure
        $filtered = [];
        $seen = [];
        foreach ($onus as $item) {
            $sn = strtoupper(trim((string)($item['serial'] ?? $item['serial_number'] ?? '')));
            if ($this->isGhostMacOrSerial($sn)) {
                continue;
            }
            if (isset($seen[$sn])) {
                continue;
            }
            $seen[$sn] = true;

            $item['serial'] = $sn;
            $item['serial_number'] = $sn;
            $item['model'] = $model;
            $item['status'] = $item['status'] ?? 'unconfigured';
            $filtered[] = $item;
        }

        return $filtered;
    }

    /**
     * Register / Provision an ONU on the OLT (Primary method invoked by OltController)
     */
    public function registerOnu(Olt $olt, array $params): array
    {
        return $this->provision($olt, $params);
    }

    /**
     * Provision an ONU on the OLT (Alias method)
     */
    public function provision(Olt $olt, array $params): array
    {
        $cli = $this->openCliSession($olt);
        if (!$cli) {
            return [
                'success' => false,
                'message' => 'Gagal konek ke CLI OLT (Telnet/SSH ditolak atau timeout)',
            ];
        }

        $result = ['success' => false, 'message' => ''];
        $model = strtolower($olt->model ?? 'generic');

        try {
            switch ($model) {
                case 'zte':
                    $result = $this->zteProvision($cli, $olt, $params);
                    break;
                case 'huawei':
                    $result = $this->huaweiProvision($cli, $olt, $params);
                    break;
                case 'vsol':
                    $result = $this->vsolProvision($cli, $olt, $params);
                    break;
                case 'hioso':
                    $result = $this->hiosoProvision($cli, $olt, $params);
                    break;
                case 'hsgq':
                    $result = $this->hsgqProvision($cli, $olt, $params);
                    break;
                case 'cdata':
                case 'c-data':
                    $result = $this->cdataProvision($cli, $olt, $params);
                    break;
                case 'fiberhome':
                    $result = $this->fiberhomeProvision($cli, $olt, $params);
                    break;
                case 'bdcom':
                    $result = $this->bdcomProvision($cli, $olt, $params);
                    break;
                default:
                    $result = $this->genericProvision($cli, $olt, $params);
                    break;
            }
        } catch (\Exception $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
            Log::error("ONUProvision: Provision error on {$olt->name}: {$e->getMessage()}");
        } finally {
            $cli->disconnect();
        }

        return $result;
    }

    // ===== ZTE Commands =====
    private function zteScan(OltCliSession $cli, string $pon): array
    {
        $cli->command("terminal length 0");
        $output = $cli->command("show gpon onu uncfg", 10);

        $onus = [];
        $lines = preg_split('/\r?\n/', $output) ?: [];

        foreach ($lines as $line) {
            if (str_starts_with(ltrim($line), '>') || str_starts_with(ltrim($line), '#') || stripos($line, 'show gpon') !== false) {
                continue;
            }

            // Matches e.g.: gpon-onu_1/2/2:1   ZTEGCD7D2FD6   unknown
            // or 1/2/2:1   ZTEGCD7D2FD6
            if (preg_match('/(?:gpon[-_]onu[-_])?(\d+\/\d+\/\d+)(?::(\d+))?\s+([0-9A-Za-z]{8,16})(?:\s+(\S+))?/i', $line, $m)) {
                $rawPon = $m[1];
                $seq = !empty($m[2]) ? (int) $m[2] : null;
                $sn = strtoupper($m[3]);
                $state = $m[4] ?? 'unconfigured';

                $parts = explode('/', $rawPon);
                $slot = (int) ($parts[1] ?? 1);
                $port = (int) ($parts[2] ?? 1);

                $onus[] = [
                    'pon' => $rawPon,
                    'slot' => $slot,
                    'port' => $port,
                    'seq' => $seq,
                    'serial' => $sn,
                    'serial_number' => $sn,
                    'status' => $state,
                    'interface' => "gpon-onu_{$rawPon}:" . ($seq ?: 1),
                ];
            }
        }

        return $onus;
    }

    private function zteProvision(OltCliSession $cli, Olt $olt, array $params): array
    {
        $pon = $params['pon'] ?? '1/1/1';
        $serial = strtoupper(trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $params['serial'] ?? $params['serial_number'] ?? '')));
        $name = trim(preg_replace('/[^\w\s\.-]/', '', $params['name'] ?? "ONU-{$serial}"));
        $vlan = (int) ($params['vlan'] ?? 10);
        $svlan = (int) ($params['svlan'] ?? 100);
        $onuIndex = (int) ($params['onu_index'] ?? $params['onu_id'] ?? 1);

        $cmds = [
            "configure terminal",
            "interface gpon-onu_{$pon}",
            "onu {$onuIndex} type smartax sn {$serial}",
            "name {$name}",
            "exit",
            "interface gpon-onu_{$pon}:{$onuIndex}",
            "service-port 1 vlan {$vlan} svlan {$svlan}",
            "admin-state enable",
            "exit",
            "exit",
            "write",
        ];

        $cli->commands($cmds);

        $onu = Onu::withoutGlobalScopes()->updateOrCreate(
            [
                'olt_id' => $olt->id,
                'serial_number' => $serial,
            ],
            [
                'tenant_id' => $olt->tenant_id,
                'pon_port' => str_starts_with($pon, 'PON') ? $pon : "PON {$pon}",
                'onu_index' => $onuIndex,
                'name' => $name,
                'status' => 'online',
                'customer_id' => $params['customer_id'] ?? null,
                'last_online_at' => now(),
                'last_sync_at' => now(),
            ]
        );

        return [
            'success' => true,
            'message' => "ONU {$name} ({$serial}) berhasil di-provision pada ZTE OLT",
            'onu' => $onu,
        ];
    }

    // ===== Huawei Commands =====
    private function huaweiScan(OltCliSession $cli, string $pon): array
    {
        $cli->command("undo smart");
        $output = $cli->command("display ont autofind all", 8);
        if (empty($output) || stripos($output, 'Unknown') !== false) {
            $output = $cli->command("display ont unconfigured all", 8);
        }

        $onus = [];
        $lines = preg_split('/\r?\n/', $output) ?: [];
        foreach ($lines as $line) {
            if (preg_match('/(\d+\/\d+\/\d+)\s+([0-9a-fA-F]{12,16}|[A-Z0-9]{12,16})/i', $line, $m)) {
                $onus[] = [
                    'pon' => $m[1],
                    'serial' => strtoupper($m[2]),
                    'serial_number' => strtoupper($m[2]),
                    'status' => 'unconfigured',
                ];
            }
        }

        return $onus;
    }

    private function huaweiProvision(OltCliSession $cli, Olt $olt, array $params): array
    {
        $serial = strtoupper(trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $params['serial'] ?? $params['serial_number'] ?? '')));
        $name = trim(preg_replace('/[^\w\s\.-]/', '', $params['name'] ?? "ONU-{$serial}"));
        $vlan = (int) ($params['vlan'] ?? 10);
        $pon = $params['pon'] ?? '0/0/0';
        $onuIndex = (int) ($params['onu_index'] ?? 1);

        $cmds = [
            "config",
            "interface gpon 0/0",
            "ont add {$pon} {$onuIndex} sn-auth {$serial} desc \"{$name}\"",
            "ont port native-vlan {$pon} {$onuIndex} eth 1 vlan {$vlan}",
            "ont port native-vlan {$pon} {$onuIndex} eth 2 vlan {$vlan}",
            "quit",
            "save",
        ];

        $cli->commands($cmds);

        $onu = Onu::withoutGlobalScopes()->updateOrCreate(
            [
                'olt_id' => $olt->id,
                'serial_number' => $serial,
            ],
            [
                'tenant_id' => $olt->tenant_id,
                'pon_port' => str_starts_with($pon, 'PON') ? $pon : "PON {$pon}",
                'onu_index' => $onuIndex,
                'name' => $name,
                'status' => 'online',
                'customer_id' => $params['customer_id'] ?? null,
                'last_online_at' => now(),
                'last_sync_at' => now(),
            ]
        );

        return [
            'success' => true,
            'message' => "ONU {$name} ({$serial}) berhasil di-provision pada Huawei OLT",
            'onu' => $onu,
        ];
    }

    // ===== VSOL Commands =====
    private function vsolScan(OltCliSession $cli, string $pon): array
    {
        $cli->command("terminal length 0");
        $output = $cli->command("show onu unauth", 6);
        if (empty($output) || stripos($output, 'Unknown') !== false) {
            $output = $cli->command("show ont autofind", 6);
        }

        $onus = [];
        $lines = preg_split('/\r?\n/', $output) ?: [];
        foreach ($lines as $line) {
            if (preg_match('/(\d+)\s+([0-9a-fA-F:\.-]{12,17}|[A-Z0-9]{12,16})/i', $line, $m)) {
                $onus[] = [
                    'pon' => "PON {$m[1]}",
                    'serial' => strtoupper($m[2]),
                    'serial_number' => strtoupper($m[2]),
                    'status' => 'unconfigured',
                ];
            }
        }

        return $onus;
    }

    private function vsolProvision(OltCliSession $cli, Olt $olt, array $params): array
    {
        $serial = strtoupper(trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $params['serial'] ?? $params['serial_number'] ?? '')));
        $name = trim(preg_replace('/[^\w\s\.-]/', '', $params['name'] ?? "ONU-{$serial}"));
        $vlan = (int) ($params['vlan'] ?? 10);
        $pon = $params['pon'] ?? '1';
        $onuId = (int) ($params['onu_index'] ?? 1);

        $cmds = [
            "enable",
            "configure terminal",
            "interface gpon-olt_{$pon}",
            "onu {$onuId} type V2802RGW sn {$serial}",
            "exit",
            "interface gpon-onu_{$pon}:{$onuId}",
            "name {$name}",
            "tcont 1 profile default",
            "gemport 1 tcont 1",
            "exit",
            "pon-onu-mng gpon-onu_{$pon}:{$onuId}",
            "service 1 gemport 1 vlan {$vlan}",
            "vlan port eth_0/1 mode tag vlan {$vlan}",
            "exit",
            "write",
        ];

        $cli->commands($cmds);

        $onu = Onu::withoutGlobalScopes()->updateOrCreate(
            [
                'olt_id' => $olt->id,
                'serial_number' => $serial,
            ],
            [
                'tenant_id' => $olt->tenant_id,
                'pon_port' => "PON {$pon}",
                'onu_index' => $onuId,
                'name' => $name,
                'status' => 'online',
                'customer_id' => $params['customer_id'] ?? null,
                'last_online_at' => now(),
                'last_sync_at' => now(),
            ]
        );

        return [
            'success' => true,
            'message' => "ONU {$name} ({$serial}) berhasil di-provision pada V-SOL OLT",
            'onu' => $onu,
        ];
    }

    // ===== HiOSO Commands =====
    private function hiosoScan(OltCliSession $cli, string $pon): array
    {
        $output = $cli->command("show onu unauth", 6);
        if (empty($output) || stripos($output, 'Unknown') !== false) {
            $output = $cli->command("show epon onu unauth", 6);
        }
        if (empty($output) || stripos($output, 'Unknown') !== false) {
            $output = $cli->command("show onu autofind", 6);
        }

        $onus = [];
        $lines = preg_split('/\r?\n/', $output) ?: [];
        foreach ($lines as $line) {
            if (preg_match('/(?:0\/)?(\d+)[:\s]+([0-9a-fA-F:\.-]{12,17})/i', $line, $m)) {
                $mac = strtoupper($m[2]);
                if (!$this->isGhostMacOrSerial($mac)) {
                    $onus[] = [
                        'pon' => "PON {$m[1]}",
                        'serial' => $mac,
                        'serial_number' => $mac,
                        'mac' => $mac,
                        'status' => 'unconfigured',
                    ];
                }
            }
        }

        return $onus;
    }

    private function hiosoProvision(OltCliSession $cli, Olt $olt, array $params): array
    {
        $serial = strtoupper(trim(preg_replace('/[^a-zA-Z0-9_:-]/', '', $params['serial'] ?? $params['serial_number'] ?? '')));
        $name = trim(preg_replace('/[^\w\s\.-]/', '', $params['name'] ?? "ONU-{$serial}"));
        $vlan = (int) ($params['vlan'] ?? 10);
        $pon = $params['pon'] ?? '1';
        $onuId = (int) ($params['onu_index'] ?? 1);

        $cmds = [
            "enable",
            "config",
            "interface epon 0/{$pon}",
            "onu add {$onuId} mac {$serial}",
            "onu {$onuId} name {$name}",
            "onu {$onuId} vlan mode tag vlan {$vlan}",
            "exit",
            "write",
        ];

        $cli->commands($cmds);

        $onu = Onu::withoutGlobalScopes()->updateOrCreate(
            [
                'olt_id' => $olt->id,
                'serial_number' => $serial,
            ],
            [
                'tenant_id' => $olt->tenant_id,
                'pon_port' => "PON {$pon}",
                'onu_index' => $onuId,
                'name' => $name,
                'status' => 'online',
                'customer_id' => $params['customer_id'] ?? null,
                'last_online_at' => now(),
                'last_sync_at' => now(),
            ]
        );

        return [
            'success' => true,
            'message' => "ONU {$name} ({$serial}) berhasil di-provision pada HiOSO OLT",
            'onu' => $onu,
        ];
    }

    // ===== HSGQ Commands =====
    private function hsgqScan(OltCliSession $cli, string $pon): array
    {
        $output = $cli->command("show epon onu unauth", 6);
        if (empty($output) || stripos($output, 'Unknown') !== false) {
            $output = $cli->command("show gpon onu unauth", 6);
        }
        if (empty($output) || stripos($output, 'Unknown') !== false) {
            $output = $cli->command("show onu unauth", 6);
        }

        $onus = [];
        $lines = preg_split('/\r?\n/', $output) ?: [];
        foreach ($lines as $line) {
            if (preg_match('/(?:0\/)?(\d+)[:\s]+([0-9a-fA-F:\.-]{12,17}|[A-Z0-9]{12,16})/i', $line, $m)) {
                $onus[] = [
                    'pon' => "PON {$m[1]}",
                    'serial' => strtoupper($m[2]),
                    'serial_number' => strtoupper($m[2]),
                    'status' => 'unconfigured',
                ];
            }
        }

        return $onus;
    }

    private function hsgqProvision(OltCliSession $cli, Olt $olt, array $params): array
    {
        $serial = strtoupper(trim(preg_replace('/[^a-zA-Z0-9_:-]/', '', $params['serial'] ?? $params['serial_number'] ?? '')));
        $name = trim(preg_replace('/[^\w\s\.-]/', '', $params['name'] ?? "ONU-{$serial}"));
        $vlan = (int) ($params['vlan'] ?? 10);
        $pon = $params['pon'] ?? '1';
        $onuId = (int) ($params['onu_index'] ?? 1);

        $cmds = [
            "enable",
            "config",
            "interface epon 0/{$pon}",
            "onu add {$onuId} mac {$serial}",
            "onu {$onuId} name {$name}",
            "onu {$onuId} vlan mode tag vlan {$vlan}",
            "exit",
            "write",
        ];

        $cli->commands($cmds);

        $onu = Onu::withoutGlobalScopes()->updateOrCreate(
            [
                'olt_id' => $olt->id,
                'serial_number' => $serial,
            ],
            [
                'tenant_id' => $olt->tenant_id,
                'pon_port' => "PON {$pon}",
                'onu_index' => $onuId,
                'name' => $name,
                'status' => 'online',
                'customer_id' => $params['customer_id'] ?? null,
                'last_online_at' => now(),
                'last_sync_at' => now(),
            ]
        );

        return [
            'success' => true,
            'message' => "ONU {$name} ({$serial}) berhasil di-provision pada HSGQ OLT",
            'onu' => $onu,
        ];
    }

    // ===== C-Data Commands =====
    private function cdataScan(OltCliSession $cli, string $pon): array
    {
        $cli->command("terminal length 0");
        $output = $cli->command("show ont info uncfg all", 8);
        if (empty($output) || stripos($output, 'Unknown') !== false) {
            $output = $cli->command("show gpon onu uncfg", 8);
        }
        if (empty($output) || stripos($output, 'Unknown') !== false) {
            $output = $cli->command("show onu unauth", 8);
        }
        if (empty($output) || stripos($output, 'Unknown') !== false) {
            $output = $cli->command("show epon onu unauth", 8);
        }

        $onus = [];
        $lines = preg_split('/\r?\n/', $output) ?: [];

        foreach ($lines as $line) {
            // C-Data GPON table: 0/1  1  CDAT12345678 ...
            if (preg_match('/(\d+\/\d+|\d+)[:\s]+(\d+)?[:\s]*([0-9a-fA-F:\.-]{12,17}|[A-Za-z0-9]{12,16})/i', $line, $m)) {
                $rawPon = $m[1];
                $sn = strtoupper($m[3]);
                if (!$this->isGhostMacOrSerial($sn)) {
                    $onus[] = [
                        'pon' => str_starts_with($rawPon, 'PON') ? $rawPon : "PON {$rawPon}",
                        'serial' => $sn,
                        'serial_number' => $sn,
                        'status' => 'unconfigured',
                    ];
                }
            }
        }

        return $onus;
    }

    private function cdataProvision(OltCliSession $cli, Olt $olt, array $params): array
    {
        $serial = strtoupper(trim(preg_replace('/[^a-zA-Z0-9_:-]/', '', $params['serial'] ?? $params['serial_number'] ?? '')));
        $name = trim(preg_replace('/[^\w\s\.-]/', '', $params['name'] ?? "ONU-{$serial}"));
        $vlan = (int) ($params['vlan'] ?? 10);
        $pon = $params['pon'] ?? '1';
        $onuId = (int) ($params['onu_index'] ?? 1);

        $isGpon = (strlen($serial) >= 12 && !str_contains($serial, ':'));

        if ($isGpon) {
            $slot = 1;
            $port = (int) preg_replace('/[^0-9]/', '', $pon);
            $cmds = [
                "enable",
                "config",
                "interface gpon 0/{$slot}",
                "ont add {$port} {$onuId} sn {$serial}",
                "ont description {$port} {$onuId} {$name}",
                "exit",
                "write",
            ];
        } else {
            $cmds = [
                "enable",
                "config",
                "interface epon 0/{$pon}",
                "onu add {$onuId} mac {$serial}",
                "onu {$onuId} name {$name}",
                "onu {$onuId} vlan mode tag vlan {$vlan}",
                "exit",
                "write",
            ];
        }

        $cli->commands($cmds);

        $onu = Onu::withoutGlobalScopes()->updateOrCreate(
            [
                'olt_id' => $olt->id,
                'serial_number' => $serial,
            ],
            [
                'tenant_id' => $olt->tenant_id,
                'pon_port' => "PON {$pon}",
                'onu_index' => $onuId,
                'name' => $name,
                'status' => 'online',
                'customer_id' => $params['customer_id'] ?? null,
                'last_online_at' => now(),
                'last_sync_at' => now(),
            ]
        );

        return [
            'success' => true,
            'message' => "ONU {$name} ({$serial}) berhasil di-provision pada C-Data OLT",
            'onu' => $onu,
        ];
    }

    // ===== FiberHome Commands =====
    private function fiberhomeScan(OltCliSession $cli, string $pon): array
    {
        $output = $cli->command("show onu autofind", 6);
        $onus = [];
        $lines = preg_split('/\r?\n/', $output) ?: [];
        foreach ($lines as $line) {
            if (preg_match('/(\d+\/\d+\/\d+)\s+([0-9a-fA-F]{12,16})/i', $line, $m)) {
                $onus[] = [
                    'pon' => $m[1],
                    'serial' => strtoupper($m[2]),
                    'serial_number' => strtoupper($m[2]),
                    'status' => 'unconfigured',
                ];
            }
        }
        return $onus;
    }

    private function fiberhomeProvision(OltCliSession $cli, Olt $olt, array $params): array
    {
        $serial = strtoupper(trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $params['serial'] ?? $params['serial_number'] ?? '')));
        $name = trim(preg_replace('/[^\w\s\.-]/', '', $params['name'] ?? "ONU-{$serial}"));
        $vlan = (int) ($params['vlan'] ?? 10);
        $pon = $params['pon'] ?? '1/1/1';
        $onuId = (int) ($params['onu_index'] ?? 1);

        $cmds = [
            "configure terminal",
            "interface pon {$pon}",
            "onu add {$onuId} auth-type sn {$serial} desc {$name}",
            "onu port 1 vlan {$vlan}",
            "exit",
            "write",
        ];

        $cli->commands($cmds);

        $onu = Onu::withoutGlobalScopes()->updateOrCreate(
            [
                'olt_id' => $olt->id,
                'serial_number' => $serial,
            ],
            [
                'tenant_id' => $olt->tenant_id,
                'pon_port' => $pon,
                'onu_index' => $onuId,
                'name' => $name,
                'status' => 'online',
                'customer_id' => $params['customer_id'] ?? null,
                'last_online_at' => now(),
                'last_sync_at' => now(),
            ]
        );

        return [
            'success' => true,
            'message' => "ONU {$name} ({$serial}) berhasil di-provision pada FiberHome OLT",
            'onu' => $onu,
        ];
    }

    // ===== BDCOM Commands =====
    private function bdcomScan(OltCliSession $cli, string $pon): array
    {
        $output = $cli->command("show epon onu unauth", 6);
        if (empty($output) || stripos($output, 'Unknown') !== false) {
            $output = $cli->command("show onu unauth", 6);
        }

        $onus = [];
        $lines = preg_split('/\r?\n/', $output) ?: [];
        foreach ($lines as $line) {
            if (preg_match('/(?:EPON|epon)?\s*0?\/(\d+)[:\s]+([0-9a-fA-F:\.-]{12,17})/i', $line, $m)) {
                $mac = strtoupper($m[2]);
                if (!$this->isGhostMacOrSerial($mac)) {
                    $onus[] = [
                        'pon' => "PON {$m[1]}",
                        'serial' => $mac,
                        'serial_number' => $mac,
                        'status' => 'unconfigured',
                    ];
                }
            }
        }
        return $onus;
    }

    private function bdcomProvision(OltCliSession $cli, Olt $olt, array $params): array
    {
        $serial = strtoupper(trim(preg_replace('/[^a-zA-Z0-9_:-]/', '', $params['serial'] ?? $params['serial_number'] ?? '')));
        $name = trim(preg_replace('/[^\w\s\.-]/', '', $params['name'] ?? "ONU-{$serial}"));
        $vlan = (int) ($params['vlan'] ?? 10);
        $pon = $params['pon'] ?? '1';
        $onuId = (int) ($params['onu_index'] ?? 1);

        $cmds = [
            "enable",
            "config",
            "interface epon 0/{$pon}",
            "onu add {$onuId} mac {$serial}",
            "onu {$onuId} name {$name}",
            "onu {$onuId} vlan mode tag vlan {$vlan}",
            "exit",
            "write",
        ];

        $cli->commands($cmds);

        $onu = Onu::withoutGlobalScopes()->updateOrCreate(
            [
                'olt_id' => $olt->id,
                'serial_number' => $serial,
            ],
            [
                'tenant_id' => $olt->tenant_id,
                'pon_port' => "PON {$pon}",
                'onu_index' => $onuId,
                'name' => $name,
                'status' => 'online',
                'customer_id' => $params['customer_id'] ?? null,
                'last_online_at' => now(),
                'last_sync_at' => now(),
            ]
        );

        return [
            'success' => true,
            'message' => "ONU {$name} ({$serial}) berhasil di-provision pada BDCOM OLT",
            'onu' => $onu,
        ];
    }

    private function genericScan(OltCliSession $cli, string $pon): array
    {
        $commands = [
            "show gpon onu uncfg",
            "show ont info uncfg all",
            "display ont autofind all",
            "show onu unauth",
            "show epon onu unauth",
        ];

        $onus = [];
        foreach ($commands as $cmd) {
            $output = $cli->command($cmd, 6);
            if (empty($output) || stripos($output, 'unknown') !== false || stripos($output, 'invalid') !== false) {
                continue;
            }

            $lines = preg_split('/\r?\n/', $output) ?: [];
            foreach ($lines as $line) {
                if (preg_match('/(\d+\/\d+\/\d+|\d+)[:\s]+([0-9a-fA-F:\.-]{12,17}|[A-Z0-9]{12,16})/i', $line, $m)) {
                    $sn = strtoupper($m[2]);
                    if (!$this->isGhostMacOrSerial($sn)) {
                        $onus[] = [
                            'pon' => $m[1],
                            'serial' => $sn,
                            'serial_number' => $sn,
                            'status' => 'unconfigured',
                        ];
                    }
                }
            }
            if (!empty($onus)) {
                break;
            }
        }

        return $onus;
    }

    private function genericProvision(OltCliSession $cli, Olt $olt, array $params): array
    {
        $serial = strtoupper(trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $params['serial'] ?? $params['serial_number'] ?? '')));
        $name = trim(preg_replace('/[^\w\s\.-]/', '', $params['name'] ?? "ONU-{$serial}"));
        $pon = $params['pon'] ?? '1';
        $onuId = (int) ($params['onu_index'] ?? 1);

        $onu = Onu::withoutGlobalScopes()->updateOrCreate(
            [
                'olt_id' => $olt->id,
                'serial_number' => $serial,
            ],
            [
                'tenant_id' => $olt->tenant_id,
                'pon_port' => "PON {$pon}",
                'onu_index' => $onuId,
                'name' => $name,
                'status' => 'online',
                'customer_id' => $params['customer_id'] ?? null,
                'last_online_at' => now(),
                'last_sync_at' => now(),
            ]
        );

        return [
            'success' => true,
            'message' => "ONU {$name} ({$serial}) berhasil dicatat di sistem",
            'onu' => $onu,
        ];
    }

    /**
     * Check if serial number / MAC is a ghost or dummy slot.
     */
    public function isGhostMacOrSerial(?string $sn): bool
    {
        if ($sn === null || $sn === '') {
            return true;
        }
        $clean = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $sn));
        if ($clean === '' || $clean === '000000000000' || $clean === '0000000000000000' || $clean === 'FFFFFFFFFFFF' || $clean === 'FFFFFFFFFFFFFFFF') {
            return true;
        }
        if (in_array($clean, ['0', '00', 'NA', 'NULL', 'NONE', 'UNKNOWN', 'ERROR', 'GHOST', '--'], true)) {
            return true;
        }
        if (preg_match('/^0+$/', $clean)) {
            return true;
        }
        return false;
    }
}
