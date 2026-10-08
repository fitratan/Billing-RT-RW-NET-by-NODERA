<?php

namespace App\Services;

use App\Models\Olt;
use App\Models\Onu;
use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SSH2;

class OnuProvisionService
{
    /**
     * Connect to OLT via SSH
     */
    public function connect(Olt $olt): ?SSH2
    {
        try {
            $ssh = new SSH2($olt->host, $olt->port ?? 22, 10);
            if (!$ssh->login($olt->username, $olt->password)) {
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
     * Scan for unconfigured ONUs based on OLT model
     */
    public function scanUnconfigured(Olt $olt, string $pon = '1/1/1'): array
    {
        $ssh = $this->connect($olt);
        if (!$ssh) return [];

        $onus = [];
        try {
            switch ($olt->model) {
                case 'zte':
                    $onus = $this->zteScan($ssh, $pon);
                    break;
                case 'huawei':
                    $onus = $this->huaweiScan($ssh, $pon);
                    break;
                case 'vsol':
                    $onus = $this->vsolScan($ssh, $pon);
                    break;
                case 'hioso':
                    $onus = $this->hiosoScan($ssh, $pon);
                    break;
                case 'hsgq':
                    $onus = $this->hsgqScan($ssh, $pon);
                    break;
                case 'cdata':
                    $onus = $this->cdataScan($ssh, $pon);
                    break;
                case 'fiberhome':
                    $onus = $this->fiberhomeScan($ssh, $pon);
                    break;
                default:
                    $onus = $this->genericScan($ssh, $pon);
            }
        } catch (\Exception $e) {
            Log::error("ONUProvision: Scan error on {$olt->name}: {$e->getMessage()}");
        }

        $ssh->disconnect();
        return $onus;
    }

    /**
     * Provision an ONU on the OLT
     */
    public function provision(Olt $olt, array $params): array
    {
        $ssh = $this->connect($olt);
        if (!$ssh) {
            return ['success' => false, 'message' => 'Gagal konek ke OLT'];
        }

        $result = ['success' => false, 'message' => ''];

        try {
            switch ($olt->model) {
                case 'zte':
                    $result = $this->zteProvision($ssh, $olt, $params);
                    break;
                case 'huawei':
                    $result = $this->huaweiProvision($ssh, $olt, $params);
                    break;
                case 'vsol':
                    $result = $this->vsolProvision($ssh, $olt, $params);
                    break;
                case 'hioso':
                    $result = $this->hiosoProvision($ssh, $olt, $params);
                    break;
                case 'hsgq':
                    $result = $this->hsgqProvision($ssh, $olt, $params);
                    break;
                case 'cdata':
                    $result = $this->cdataProvision($ssh, $olt, $params);
                    break;
                case 'fiberhome':
                    $result = $this->fiberhomeProvision($ssh, $olt, $params);
                    break;
                case 'bdcom':
                    $result = $this->bdcomProvision($ssh, $olt, $params);
                    break;
                default:
                    $result = ['success' => false, 'message' => 'Model OLT tidak didukung'];
            }
        } catch (\Exception $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
            Log::error("ONUProvision: Provision error: {$e->getMessage()}");
        }

        $ssh->disconnect();
        return $result;
    }

    // ===== ZTE Commands =====
    private function zteScan(SSH2 $ssh, string $pon): array
    {
        $ssh->write("configure terminal\n");
        $ssh->read();
        $ssh->write("show gpon onu uncfg\n");
        $output = $ssh->read();

        $onus = [];
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            if (preg_match('/(\d+\/\d+\/\d+)\s+(\w{4}\w+)/', $line, $m)) {
                $onus[] = [
                    'pon' => $m[1],
                    'serial' => $m[2],
                    'status' => 'unconfigured',
                ];
            }
        }

        return $onus;
    }

    private function zteProvision(SSH2 $ssh, Olt $olt, array $params): array
    {
        $pon = $params['pon'] ?? '1/1/1';
        $serial = $params['serial'];
        $name = $params['name'] ?? "ONU-{$serial}";
        $vlan = $params['vlan'] ?? 10;
        $svlan = $params['svlan'] ?? 100;
        $onuIndex = $params['onu_index'] ?? 1;

        $cmds = [
            "configure terminal",
            "interface gpon-onu_{$pon}",
            "onu {$onuIndex} type smartax sn {$serial}",
            "name {$name}",
            "quit",
            "interface gpon-onu_{$pon}:{$onuIndex}",
            "service-port 1 vlan {$vlan} svlan {$svlan}",
            "admin-state enable",
            "quit",
            "exit",
        ];

        foreach ($cmds as $cmd) {
            $ssh->write($cmd . "\n");
            usleep(150000);
            $ssh->read();
        }

        Onu::create([
            'serial_number' => $serial,
            'olt_id' => $olt->id,
            'pon_port' => $pon,
            'onu_index' => $onuIndex,
            'name' => $name,
            'status' => 'active',
            'tenant_id' => $olt->tenant_id,
        ]);

        return ['success' => true, 'message' => "ONU {$name} berhasil di-provision pada ZTE"];
    }

    // ===== Huawei Commands =====
    private function huaweiScan(SSH2 $ssh, string $pon): array
    {
        $ssh->write("display ont unconfigured\n");
        $output = $ssh->read();

        $onus = [];
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            if (preg_match('/(\d+\/\d+\/\d+)\s+(\w{4}\w+)/', $line, $m)) {
                $onus[] = [
                    'pon' => $m[1],
                    'serial' => $m[2],
                    'status' => 'unconfigured',
                ];
            }
        }

        return $onus;
    }

    private function huaweiProvision(SSH2 $ssh, Olt $olt, array $params): array
    {
        $serial = $params['serial'];
        $name = $params['name'] ?? "ONU-{$serial}";
        $vlan = $params['vlan'] ?? 10;
        $pon = $params['pon'] ?? '0/0/0';
        $onuIndex = $params['onu_index'] ?? 1;

        $cmds = [
            "interface gpon 0/0",
            "ont add {$pon} {$onuIndex} sn-auth {$serial} desc {$name}",
            "ont port native-vlan {$pon} {$onuIndex} eth 0 vlan {$vlan}",
            "ont port native-vlan {$pon} {$onuIndex} eth 1 vlan {$vlan}",
            "quit",
        ];

        foreach ($cmds as $cmd) {
            $ssh->write($cmd . "\n");
            usleep(150000);
            $ssh->read();
        }

        Onu::create([
            'serial_number' => $serial,
            'olt_id' => $olt->id,
            'pon_port' => $pon,
            'onu_index' => $onuIndex,
            'name' => $name,
            'status' => 'active',
            'tenant_id' => $olt->tenant_id,
        ]);

        return ['success' => true, 'message' => "ONU {$name} berhasil di-provision pada Huawei"];
    }

    // ===== VSOL Commands =====
    private function vsolScan(SSH2 $ssh, string $pon): array
    {
        $ssh->write("enable\n");
        $ssh->read();
        $ssh->write("show onu unauth\n");
        $output = $ssh->read();

        $onus = [];
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            if (preg_match('/(\d+)\s+([0-9a-fA-F:\.-]{12,17}|[A-Z0-9]{12,16})/i', $line, $m)) {
                $onus[] = [
                    'pon' => "PON {$m[1]}",
                    'serial' => strtoupper($m[2]),
                    'status' => 'unconfigured',
                ];
            }
        }
        return $onus;
    }

    private function vsolProvision(SSH2 $ssh, Olt $olt, array $params): array
    {
        $serial = $params['serial'];
        $name = $params['name'] ?? "ONU-{$serial}";
        $vlan = $params['vlan'] ?? 10;
        $pon = $params['pon'] ?? '1';
        $onuId = $params['onu_index'] ?? 1;

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

        foreach ($cmds as $cmd) {
            $ssh->write($cmd . "\n");
            usleep(150000);
            $ssh->read();
        }

        Onu::create([
            'serial_number' => $serial,
            'olt_id' => $olt->id,
            'pon_port' => "PON {$pon}",
            'onu_index' => $onuId,
            'name' => $name,
            'status' => 'active',
            'tenant_id' => $olt->tenant_id,
        ]);

        return ['success' => true, 'message' => "ONU {$name} berhasil di-provision pada V-SOL"];
    }

    // ===== Hioso Commands =====
    private function hiosoScan(SSH2 $ssh, string $pon): array
    {
        $ssh->write("enable\n");
        $ssh->read();
        $ssh->write("show onu unauth\n");
        $output = $ssh->read();

        $onus = [];
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            if (preg_match('/(\d+)\s+([0-9a-fA-F:\.-]{12,17})/i', $line, $m)) {
                $onus[] = [
                    'pon' => "PON {$m[1]}",
                    'serial' => strtoupper($m[2]),
                    'status' => 'unconfigured',
                ];
            }
        }
        return $onus;
    }

    private function hiosoProvision(SSH2 $ssh, Olt $olt, array $params): array
    {
        $serial = $params['serial'];
        $name = $params['name'] ?? "ONU-{$serial}";
        $vlan = $params['vlan'] ?? 10;
        $pon = $params['pon'] ?? '1';
        $onuId = $params['onu_index'] ?? 1;

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

        foreach ($cmds as $cmd) {
            $ssh->write($cmd . "\n");
            usleep(150000);
            $ssh->read();
        }

        Onu::create([
            'serial_number' => $serial,
            'olt_id' => $olt->id,
            'pon_port' => "PON {$pon}",
            'onu_index' => $onuId,
            'name' => $name,
            'status' => 'active',
            'tenant_id' => $olt->tenant_id,
        ]);

        return ['success' => true, 'message' => "ONU {$name} berhasil di-provision pada HIOSO"];
    }

    // ===== HSGQ Commands =====
    private function hsgqScan(SSH2 $ssh, string $pon): array
    {
        $ssh->write("enable\n");
        $ssh->read();
        $ssh->write("show epon onu unauth\n");
        $output = $ssh->read();

        $onus = [];
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            if (preg_match('/(\d+)\s+([0-9a-fA-F:\.-]{12,17})/i', $line, $m)) {
                $onus[] = [
                    'pon' => "PON {$m[1]}",
                    'serial' => strtoupper($m[2]),
                    'status' => 'unconfigured',
                ];
            }
        }
        return $onus;
    }

    private function hsgqProvision(SSH2 $ssh, Olt $olt, array $params): array
    {
        $serial = $params['serial'];
        $name = $params['name'] ?? "ONU-{$serial}";
        $vlan = $params['vlan'] ?? 10;
        $pon = $params['pon'] ?? '1';
        $onuId = $params['onu_index'] ?? 1;

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

        foreach ($cmds as $cmd) {
            $ssh->write($cmd . "\n");
            usleep(150000);
            $ssh->read();
        }

        Onu::create([
            'serial_number' => $serial,
            'olt_id' => $olt->id,
            'pon_port' => "PON {$pon}",
            'onu_index' => $onuId,
            'name' => $name,
            'status' => 'active',
            'tenant_id' => $olt->tenant_id,
        ]);

        return ['success' => true, 'message' => "ONU {$name} berhasil di-provision pada HSGQ"];
    }

    // ===== C-Data Commands =====
    private function cdataScan(SSH2 $ssh, string $pon): array
    {
        $ssh->write("enable\n");
        $ssh->read();
        $ssh->write("show onu unauth\n");
        $output = $ssh->read();

        $onus = [];
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            if (preg_match('/(\d+)\s+([0-9a-fA-F:\.-]{12,17})/i', $line, $m)) {
                $onus[] = [
                    'pon' => "PON {$m[1]}",
                    'serial' => strtoupper($m[2]),
                    'status' => 'unconfigured',
                ];
            }
        }
        return $onus;
    }

    private function cdataProvision(SSH2 $ssh, Olt $olt, array $params): array
    {
        $serial = $params['serial'];
        $name = $params['name'] ?? "ONU-{$serial}";
        $vlan = $params['vlan'] ?? 10;
        $pon = $params['pon'] ?? '1';
        $onuId = $params['onu_index'] ?? 1;

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

        foreach ($cmds as $cmd) {
            $ssh->write($cmd . "\n");
            usleep(150000);
            $ssh->read();
        }

        Onu::create([
            'serial_number' => $serial,
            'olt_id' => $olt->id,
            'pon_port' => "PON {$pon}",
            'onu_index' => $onuId,
            'name' => $name,
            'status' => 'active',
            'tenant_id' => $olt->tenant_id,
        ]);

        return ['success' => true, 'message' => "ONU {$name} berhasil di-provision pada C-Data"];
    }

    // ===== FiberHome Commands =====
    private function fiberhomeScan(SSH2 $ssh, string $pon): array
    {
        $ssh->write("show onu autofind\n");
        $output = $ssh->read();

        $onus = [];
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            if (preg_match('/(\d+\/\d+\/\d+)\s+([0-9a-fA-F]{12,16})/i', $line, $m)) {
                $onus[] = [
                    'pon' => $m[1],
                    'serial' => strtoupper($m[2]),
                    'status' => 'unconfigured',
                ];
            }
        }
        return $onus;
    }

    private function fiberhomeProvision(SSH2 $ssh, Olt $olt, array $params): array
    {
        $serial = $params['serial'];
        $name = $params['name'] ?? "ONU-{$serial}";
        $vlan = $params['vlan'] ?? 10;
        $pon = $params['pon'] ?? '1/1/1';
        $onuId = $params['onu_index'] ?? 1;

        $cmds = [
            "configure terminal",
            "interface pon {$pon}",
            "onu add {$onuId} auth-type sn {$serial} desc {$name}",
            "onu port 1 vlan {$vlan}",
            "exit",
        ];

        foreach ($cmds as $cmd) {
            $ssh->write($cmd . "\n");
            usleep(150000);
            $ssh->read();
        }

        Onu::create([
            'serial_number' => $serial,
            'olt_id' => $olt->id,
            'pon_port' => $pon,
            'onu_index' => $onuId,
            'name' => $name,
            'status' => 'active',
            'tenant_id' => $olt->tenant_id,
        ]);

        return ['success' => true, 'message' => "ONU {$name} berhasil di-provision pada FiberHome"];
    }

    // ===== BDCOM Commands =====
    private function bdcomProvision(SSH2 $ssh, Olt $olt, array $params): array
    {
        $serial = $params['serial'];
        $name = $params['name'] ?? "ONU-{$serial}";
        $vlan = $params['vlan'] ?? 10;
        $pon = $params['pon'] ?? '1';
        $onuId = $params['onu_index'] ?? 1;

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

        foreach ($cmds as $cmd) {
            $ssh->write($cmd . "\n");
            usleep(150000);
            $ssh->read();
        }

        Onu::create([
            'serial_number' => $serial,
            'olt_id' => $olt->id,
            'pon_port' => "PON {$pon}",
            'onu_index' => $onuId,
            'name' => $name,
            'status' => 'active',
            'tenant_id' => $olt->tenant_id,
        ]);

        return ['success' => true, 'message' => "ONU {$name} berhasil di-provision pada BDCOM"];
    }

    private function genericScan(SSH2 $ssh, string $pon): array
    {
        $onus = [];
        foreach (["show gpon onu uncfg\n", "display ont unconfigured\n", "show onu unauth\n"] as $cmd) {
            $ssh->write($cmd);
            $output = $ssh->read();
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                if (preg_match('/(\d+\/\d+\/\d+|\d+)\s+([0-9a-fA-F:\.-]{12,17}|[A-Z0-9]{12,16})/i', $line, $m)) {
                    $onus[] = [
                        'pon' => $m[1],
                        'serial' => strtoupper($m[2]),
                        'status' => 'unconfigured',
                    ];
                }
            }
            if (!empty($onus)) break;
        }
        return $onus;
    }
}
