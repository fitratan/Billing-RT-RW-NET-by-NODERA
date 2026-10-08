<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Olt;
use App\Services\OltNmsService;
use FreeDSx\Snmp\SnmpClient;

class OltDiagnoseCommand extends Command
{
    protected $signature = 'olt:diagnose {id? : ID, Host, atau Nama OLT}';
    protected $description = 'Diagnostik mendalam koneksi SNMP & Telnet CLI OLT, scan MIB tables, dan uji live ONU discovery';

    public function handle(OltNmsService $nms): int
    {
        $target = $this->argument('id');

        $olt = null;
        if ($target) {
            $olt = is_numeric($target)
                ? Olt::find($target)
                : Olt::where('host', $target)->orWhere('name', 'like', "%{$target}%")->first();

            if (!$olt) {
                $this->error("❌ OLT dengan ID / Host / Nama '{$target}' tidak ditemukan!");
                return 1;
            }
        } else {
            $olts = Olt::all();
            if ($olts->isEmpty()) {
                $this->warn("⚠️ Belum ada OLT terdaftar di sistem.");
                return 0;
            }

            $this->info("Daftar OLT yang tersedia:");
            $this->table(
                ['ID', 'Nama OLT', 'Host IP', 'Model', 'Mode', 'SNMP Port', 'Telnet Port', 'Total ONU'],
                $olts->map(fn($o) => [
                    $o->id,
                    $o->name,
                    $o->host,
                    strtoupper($o->model ?? 'other'),
                    strtoupper($o->connection_mode ?? 'snmp'),
                    $o->snmp_port ?? 161,
                    $o->telnet_port ?? 23,
                    $o->onus()->count(),
                ])
            );

            $id = $this->ask('Masukkan ID OLT yang ingin didiagnosis');
            $olt = Olt::find($id);
            if (!$olt) {
                $this->error("❌ OLT #{$id} tidak ditemukan!");
                return 1;
            }
        }

        $this->info("\n=======================================================");
        $this->info("   🚀 MEMULAI DIAGNOSTIK MENDALAM OLT #{$olt->id} ({$olt->name})");
        $this->info("=======================================================\n");

        $host = trim((string)$olt->host);
        if (str_contains($host, ':')) {
            [$h, $p] = explode(':', $host, 2);
            $host = $h;
        }

        $snmpPort = (int) ($olt->snmp_port ?: ($olt->port ?: 161));
        $telnetPort = (int) ($olt->telnet_port ?: 23);
        $community = $olt->snmp_community ?: 'public';
        $model = strtolower($olt->model ?? 'other');
        $mode = strtolower($olt->connection_mode ?? 'snmp');

        $this->line("📍 Host IP          : <comment>{$host}</comment>");
        $this->line("🔧 Model            : <comment>" . strtoupper($model) . "</comment>");
        $this->line("🔗 Connection Mode  : <comment>" . strtoupper($mode) . "</comment>");
        $this->line("📡 SNMP Community   : <comment>{$community}</comment> (Port: {$snmpPort})");
        $this->line("💻 Telnet Login     : <comment>" . ($olt->username ?: '-') . " / " . ($olt->password ? '***' : '-') . "</comment> (Port: {$telnetPort})");
        $this->line("📊 Database ONUs    : <comment>" . $olt->onus()->count() . " ONUs terdaftar</comment>\n");

        // STEP 1: NETWORK & PORT REACHABILITY
        $this->info("--- [STEP 1] Uji Jangkauan Jaringan & Port ---");
        
        // Test UDP SNMP
        $t1 = microtime(true);
        $fpSnmp = @fsockopen("udp://{$host}", $snmpPort, $errno, $errstr, 2);
        if ($fpSnmp) {
            @fclose($fpSnmp);
            $this->line("  ✅ UDP Port {$snmpPort} (SNMP)  : Socket Ready (" . round((microtime(true) - $t1) * 1000, 1) . " ms)");
        } else {
            $this->line("  ❌ UDP Port {$snmpPort} (SNMP)  : Tidak dapat dihubungi ({$errstr})");
        }

        // Test TCP Telnet
        $t2 = microtime(true);
        $fpTelnet = @fsockopen($host, $telnetPort, $errno, $errstr, 3);
        if ($fpTelnet) {
            @fclose($fpTelnet);
            $this->line("  ✅ TCP Port {$telnetPort} (Telnet): Terbuka (" . round((microtime(true) - $t2) * 1000, 1) . " ms)");
        } else {
            $this->line("  ⚠️ TCP Port {$telnetPort} (Telnet): Tertutup / Tidak Merespon ({$errstr})");
        }

        // STEP 2: SNMP DEEP PROBE
        $this->info("\n--- [STEP 2] Uji SNMP Walk & MIB Tables ---");
        $snmpClient = null;
        $snmpWorkingVer = null;

        foreach ([2, 1] as $ver) {
            try {
                $client = new SnmpClient([
                    'host' => $host,
                    'port' => $snmpPort,
                    'version' => $ver,
                    'community' => $community,
                    'timeout_connect' => 3,
                    'timeout_read' => 4,
                ]);

                $sysDescr = $client->getValue('1.3.6.1.2.1.1.1.0');
                if ($sysDescr !== null) {
                    $snmpClient = $client;
                    $snmpWorkingVer = $ver === 2 ? 'v2c' : 'v1';
                    $sysName = $client->getValue('1.3.6.1.2.1.1.5.0');
                    $rawDescr = (is_object($sysDescr) && method_exists($sysDescr, 'getValue')) ? $sysDescr->getValue() : (string)$sysDescr;
                    $rawName = (is_object($sysName) && method_exists($sysName, 'getValue')) ? $sysName->getValue() : (string)$sysName;

                    $this->line("  ✅ SNMP Version     : Terhubung via SNMP {$snmpWorkingVer}");
                    $this->line("  📋 sysName          : {$rawName}");
                    $this->line("  📋 sysDescr         : " . trim(substr($rawDescr, 0, 100)) . (strlen($rawDescr) > 100 ? '...' : ''));
                    break;
                }
            } catch (\Throwable $e) {
                $this->line("  ⚠️ SNMP v{$ver} probe gagal: " . $e->getMessage());
            }
        }

        if ($snmpClient) {
            $profiles = OltNmsService::BRAND_PROFILES[$model] ?? (OltNmsService::BRAND_PROFILES['hioso'] ?? []);
            $this->line("\n  Memindai MIB Profiles untuk model [<comment>{$model}</comment>]:");

            $tableResults = [];
            foreach ($profiles as $p) {
                $pName = $p['name'];
                $statusCount = 0;
                $snCount = 0;
                $rxCount = 0;

                try {
                    $w = $snmpClient->walk($p['status_table']);
                    $items = is_array($w) ? $w : iterator_to_array($w);
                    $statusCount = count($items);
                } catch (\Throwable $e) {}

                try {
                    $w = $snmpClient->walk($p['sn_table']);
                    $items = is_array($w) ? $w : iterator_to_array($w);
                    $snCount = count($items);
                } catch (\Throwable $e) {}

                try {
                    if (!empty($p['rx_power_table'])) {
                        $w = $snmpClient->walk($p['rx_power_table']);
                        $items = is_array($w) ? $w : iterator_to_array($w);
                        $rxCount = count($items);
                    }
                } catch (\Throwable $e) {}

                $matchStatus = ($statusCount > 0 || $snCount > 0) ? '✅ MATCH' : '❌ Kosong';
                $tableResults[] = [
                    $pName,
                    $p['status_table'],
                    $statusCount,
                    $snCount,
                    $rxCount,
                    $matchStatus,
                ];
            }

            $this->table(
                ['Profile Name', 'Status Table OID', 'Status Rows', 'SN Rows', 'Rx Rows', 'Hasil'],
                $tableResults
            );
        } else {
            $this->warn("  ❌ SNMP tidak dapat merespon request. Periksa IP Host dan Community string.");
        }

        // STEP 3: TELNET CLI DEEP PROBE
        $this->info("\n--- [STEP 3] Uji Telnet CLI & Perintah Hardcoded ---");
        if ($olt->username && $olt->password) {
            try {
                $tAuth = $nms->testTelnetAuth($host, $telnetPort, $olt->username, $olt->password, $olt->enable_password);
                if ($tAuth['success']) {
                    $this->line("  ✅ Telnet Login Berhasil: {$tAuth['message']}");
                } else {
                    $this->line("  ❌ Telnet Login Gagal: {$tAuth['message']}");
                }
            } catch (\Throwable $e) {
                $this->line("  ❌ Telnet Error: " . $e->getMessage());
            }
        } else {
            $this->line("  ⚠️ Username / Password Telnet belum diatur pada OLT ini.");
        }

        // STEP 4: LIVE DISCOVERY & SYNC EXECUTION
        $this->info("\n--- [STEP 4] Eksekusi Full Sync (pollOlt) ---");
        $pollStart = microtime(true);
        $result = $nms->pollOlt($olt);
        $pollDuration = round((microtime(true) - $pollStart) * 1000, 1);

        $this->line("  ⏱️ Waktu Eksekusi   : {$pollDuration} ms");
        $this->line("  📌 Status Poll      : " . ($result['success'] ? '<info>SUKSES</info>' : '<error>GAGAL</error>'));
        $this->line("  🔢 Total Ditemukan  : <comment>" . ($result['count'] ?? 0) . " ONUs</comment>");
        $this->line("  💬 Pesan Service    : " . ($result['message'] ?? '-'));

        $onus = $olt->onus()->orderBy('pon_port')->orderBy('onu_index')->get();
        if ($onus->isNotEmpty()) {
            $onlineCount = $onus->where('status', 'online')->count();
            $offlineCount = $onus->where('status', 'offline')->count();
            $this->line("  🟢 Online: <info>{$onlineCount}</info> ONUs | 🔴 Offline: <fg=red>{$offlineCount}</> ONUs");
            $this->info("\n--- [HASIL] Data ONU yang Tersimpan di Database (" . $onus->count() . " ONUs) ---");
            $this->table(
                ['PON Port', 'Index', 'Nama ONU', 'Serial Number / MAC', 'Status', 'Rx Power', 'Tx Power', 'Pelanggan'],
                $onus->map(fn($o) => [
                    $o->pon_port,
                    $o->onu_index,
                    $o->name,
                    $o->serial_number,
                    $o->status === 'online' ? '<info>ONLINE</info>' : '<fg=red>OFFLINE</>',
                    $o->rx_power !== null ? "{$o->rx_power} dBm" : '-',
                    $o->tx_power !== null ? "{$o->tx_power} dBm" : '-',
                    $o->customer?->name ?: '-',
                ])
            );
        } else {
            $this->warn("\n  ⚠️ Tidak ada ONU yang berhasil tersimpan ke database.");
        }

        $this->info("\n=======================================================");
        $this->info("   ✅ DIAGNOSTIK SELESAI");
        $this->info("=======================================================\n");

        return 0;
    }
}
