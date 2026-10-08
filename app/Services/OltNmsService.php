<?php

namespace App\Services;

use App\Models\Olt;
use App\Models\Onu;
use FreeDSx\Snmp\SnmpClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OltNmsService
{
    /**
     * Acquire VTY session mutex lock to prevent concurrent Telnet/SSH CLI session exhaustion
     */
    public static function acquireVtyLock(int $oltId, int $seconds = 15)
    {
        return Cache::lock('olt_vty_session_' . $oltId, $seconds);
    }

    /**
     * Acquire PON port mutex lock to prevent concurrent ONU allocation conflicts on the same PON port
     */
    public static function acquirePonLock(int $oltId, string $slotPort, int $seconds = 20)
    {
        $cleanPort = preg_replace('/[^a-zA-Z0-9_-]/', '_', $slotPort);
        return Cache::lock("olt_{$oltId}_pon_{$cleanPort}", $seconds);
    }

    /**
     * Brand Profiles mapping matching field-tested OID database
     */
    public const BRAND_PROFILES = [
        'hioso' => [
            [
                'name' => 'HIOSO_EPON_C',
                'label' => 'Hioso EPON (HA7302CST / HA7302CS / HA7304V / HA7304C / HA7308)',
                'status_table' => '1.3.6.1.4.1.25355.3.2.6.3.2.1.39',
                'name_table'   => '1.3.6.1.4.1.25355.3.2.6.3.2.1.37',
                'sn_table'     => '1.3.6.1.4.1.25355.3.2.6.3.2.1.11',
                'tx_power_table' => '1.3.6.1.4.1.25355.3.2.6.14.2.1.4',
                'rx_power_table' => '1.3.6.1.4.1.25355.3.2.6.14.2.1.8',
                'distance_table' => '1.3.6.1.4.1.25355.3.2.6.3.2.1.25',
                'temp_table'     => '1.3.6.1.4.1.25355.3.2.6.14.2.1.7',
                'online_values'  => [1, 3, 4],
            ],
            [
                'name' => 'HIOSO_EPON_B',
                'label' => 'Hioso EPON (BDCOM/Huawei Based)',
                'status_table' => '1.3.6.1.4.1.3320.101.10.1.1.26',
                'name_table'   => '1.3.6.1.4.1.3320.101.10.1.1.79',
                'sn_table'     => '1.3.6.1.4.1.3320.101.10.1.1.3',
                'tx_power_table' => '1.3.6.1.4.1.3320.101.10.5.1.5',
                'rx_power_table' => '1.3.6.1.4.1.3320.101.10.5.1.6',
                'online_values'  => [1, 3, 4],
            ],
            [
                'name' => 'HIOSO_GPON',
                'label' => 'Hioso GPON (C-Data Based)',
                'status_table' => '1.3.6.1.4.1.25355.3.3.1.1.1.11',
                'name_table'   => '1.3.6.1.4.1.25355.3.3.1.1.1.2',
                'sn_table'     => '1.3.6.1.4.1.25355.3.3.1.1.1.5',
                'tx_power_table' => '1.3.6.1.4.1.25355.3.3.1.1.4.1.2',
                'rx_power_table' => '1.3.6.1.4.1.25355.3.3.1.1.4.1.1',
                'online_values'  => [2, 3, 4],
            ],
        ],
        'vsol' => [
            [
                'name' => 'VSOL_EPON',
                'label' => 'VSOL EPON (V1600D / V1600 Series)',
                'status_table' => '1.3.6.1.4.1.37950.1.1.5.13.1.1.4',
                'name_table'   => '1.3.6.1.4.1.37950.1.1.5.13.1.1.10',
                'sn_table'     => '1.3.6.1.4.1.37950.1.1.5.13.1.1.2',
                'rx_power_table' => '1.3.6.1.4.1.37950.1.1.5.13.1.1.21',
                'online_values'  => [1, 2, 3, 4],
            ],
            [
                'name' => 'VSOL_GPON',
                'label' => 'VSOL GPON (V1600G / V1600GS)',
                'status_table' => '1.3.6.1.4.1.37950.1.2.6.2.1.8',
                'name_table'   => '1.3.6.1.4.1.37950.1.2.6.2.1.4',
                'sn_table'     => '1.3.6.1.4.1.37950.1.2.6.2.1.2',
                'rx_power_table' => '1.3.6.1.4.1.37950.1.2.6.3.1.1',
                'distance_table' => '1.3.6.1.4.1.37950.1.2.6.3.1.3',
                'online_values'  => [1, 2, 3, 4, 5],
            ],
            [
                'name' => 'VSOL_GPON_ALT',
                'label' => 'VSOL GPON (V1600GH / MIB v2)',
                'status_table' => '1.3.6.1.4.1.37950.1.1.5.12.1.1.8',
                'name_table'   => '1.3.6.1.4.1.37950.1.1.5.12.1.1.4',
                'sn_table'     => '1.3.6.1.4.1.37950.1.1.5.12.1.1.2',
                'rx_power_table' => '1.3.6.1.4.1.37950.1.1.5.12.1.1.25',
                'online_values'  => [1, 2, 3, 4, 5],
            ],
            [
                'name' => 'VSOL_GPON_V3',
                'label' => 'VSOL GPON (V1600GS MIB v3)',
                'status_table' => '1.3.6.1.4.1.37950.1.2.5.1.1.8',
                'name_table'   => '1.3.6.1.4.1.37950.1.2.5.1.1.4',
                'sn_table'     => '1.3.6.1.4.1.37950.1.2.5.1.1.2',
                'rx_power_table' => '1.3.6.1.4.1.37950.1.2.5.1.1.25',
                'online_values'  => [1, 2, 3, 4, 5],
            ],
            [
                'name' => 'VSOL_CDATA_BASED',
                'label' => 'VSOL C-Data Based / V2800',
                'status_table' => '1.3.6.1.4.1.34592.1.3.100.12.1.1.1.15',
                'name_table'   => '1.3.6.1.4.1.34592.1.3.100.12.1.1.1.10',
                'sn_table'     => '1.3.6.1.4.1.34592.1.3.100.12.1.1.1.10',
                'rx_power_table' => '1.3.6.1.4.1.34592.1.3.100.12.1.1.1.21',
                'online_values'  => [1, 2, 3],
            ],
        ],
        'hsgq' => [
            [
                'name' => 'HSGQ_EPON',
                'label' => 'HSGQ EPON (E04M / G08)',
                'status_table' => '1.3.6.1.4.1.3320.101.10.1.1.26',
                'name_table'   => '1.3.6.1.4.1.3320.101.10.1.1.79',
                'sn_table'     => '1.3.6.1.4.1.3320.101.10.1.1.3',
                'tx_power_table' => '1.3.6.1.4.1.3320.101.10.5.1.5',
                'rx_power_table' => '1.3.6.1.4.1.3320.101.10.5.1.6',
                'online_values'  => [1, 3, 4],
            ],
            [
                'name' => 'HSGQ_GPON',
                'label' => 'HSGQ GPON (G04 / G08 / G16)',
                'status_table' => '1.3.6.1.4.1.3320.101.10.1.1.7',
                'name_table'   => '1.3.6.1.4.1.3320.101.10.1.1.3',
                'sn_table'     => '1.3.6.1.4.1.3320.101.10.1.1.4',
                'rx_power_table' => '1.3.6.1.4.1.3320.101.10.3.1.4',
                'online_values'  => [1, 2, 3, 4],
            ],
            [
                'name' => 'HSGQ_GPON_NATIVE',
                'label' => 'HSGQ GPON (Native MIB 50058)',
                'status_table' => '1.3.6.1.4.1.50058.101.10.1.1.7',
                'name_table'   => '1.3.6.1.4.1.50058.101.10.1.1.3',
                'sn_table'     => '1.3.6.1.4.1.50058.101.10.1.1.4',
                'rx_power_table' => '1.3.6.1.4.1.50058.101.10.3.1.4',
                'online_values'  => [1, 2, 3, 4],
            ],
        ],
        'zte' => [
            [
                'name' => 'ZTE_GPON_C300_AUTH',
                'label' => 'ZTE GPON C300 / C320 (Phase State MIB)',
                'status_table' => '1.3.6.1.4.1.3902.1082.500.10.2.3.8.1.4',
                'name_table'   => '1.3.6.1.4.1.3902.1082.500.10.2.3.3.1.2',
                'sn_table'     => '1.3.6.1.4.1.3902.1082.500.10.2.3.8.1.3',
                'rx_power_table' => '1.3.6.1.4.1.3902.1082.500.10.2.3.9.1.2',
                'tx_power_table' => '1.3.6.1.4.1.3902.1082.500.10.2.3.9.1.1',
                'distance_table' => '1.3.6.1.4.1.3902.1082.500.10.2.3.10.1.2',
                'online_values'  => [1, 2, 3, 4, 5, 'working', 'online', 'syncmib', 'logging', 'ready', 'active', 'authenticated', 'up', 'enable'],
            ],
            [
                'name' => 'ZTE_GPON_C300',
                'label' => 'ZTE GPON C300 / C320 (Service State MIB)',
                'status_table' => '1.3.6.1.4.1.3902.1082.500.10.2.3.3.1.9',
                'name_table'   => '1.3.6.1.4.1.3902.1082.500.10.2.3.3.1.2',
                'sn_table'     => '1.3.6.1.4.1.3902.1082.500.10.2.3.3.1.6',
                'rx_power_table' => '1.3.6.1.4.1.3902.1082.500.10.2.3.9.1.2',
                'tx_power_table' => '1.3.6.1.4.1.3902.1082.500.10.2.3.9.1.1',
                'distance_table' => '1.3.6.1.4.1.3902.1082.500.10.2.3.10.1.2',
                'online_values'  => [1, 2, 3, 4, 5, 'working', 'online', 'enable', 'active', 'up', 'authenticated'],
            ],
            [
                'name' => 'ZTE_GPON_OPTICAL_V2',
                'label' => 'ZTE GPON Optical V2 (MIB 20)',
                'status_table' => '1.3.6.1.4.1.3902.1082.500.10.2.3.8.1.4',
                'name_table'   => '1.3.6.1.4.1.3902.1082.500.10.2.3.3.1.2',
                'sn_table'     => '1.3.6.1.4.1.3902.1082.500.10.2.3.8.1.3',
                'rx_power_table' => '1.3.6.1.4.1.3902.1082.500.20.2.2.2.1.10',
                'tx_power_table' => '1.3.6.1.4.1.3902.1082.500.20.2.2.2.1.11',
                'distance_table' => '1.3.6.1.4.1.3902.1082.500.10.2.3.10.1.2',
                'online_values'  => [1, 2, 3, 4, 5, 'working', 'online', 'syncmib', 'logging', 'ready', 'active', 'authenticated', 'up', 'enable'],
            ],
            [
                'name' => 'ZTE_EPON_C300',
                'label' => 'ZTE EPON C300 / C320',
                'status_table' => '1.3.6.1.4.1.3902.1012.3.28.2.1.4',
                'name_table'   => '1.3.6.1.4.1.3902.1012.3.28.1.1.3',
                'sn_table'     => '1.3.6.1.4.1.3902.1012.3.28.1.1.2',
                'rx_power_table' => '1.3.6.1.4.1.3902.1015.1010.11.2.1.2',
                'tx_power_table' => '1.3.6.1.4.1.3902.1015.1010.11.2.1.1',
                'online_values'  => [1, 2, 3, 4, 'working', 'online', 'authenticated', 'up', 'enable'],
            ],
            [
                'name' => 'ZTE_GPON_C600',
                'label' => 'ZTE GPON C600 / C620 / C650',
                'status_table' => '1.3.6.1.4.1.3902.1082.500.12.2.3.3.1.10',
                'name_table'   => '1.3.6.1.4.1.3902.1082.500.12.2.3.3.1.2',
                'sn_table'     => '1.3.6.1.4.1.3902.1082.500.12.2.3.3.1.3',
                'rx_power_table' => '1.3.6.1.4.1.3902.1082.500.12.2.3.7.1.3',
                'tx_power_table' => '1.3.6.1.4.1.3902.1082.500.12.2.3.7.1.2',
                'online_values'  => [1, 2, 3, 4, 5, 'working', 'online', 'active', 'up'],
            ],
            [
                'name' => 'ZTE_GPON_OLD',
                'label' => 'ZTE GPON Legacy / V1 MIB',
                'status_table' => '1.3.6.1.4.1.3902.1012.3.28.2.1.4',
                'name_table'   => '1.3.6.1.4.1.3902.1082.500.10.2.3.3.1.2',
                'sn_table'     => '1.3.6.1.4.1.3902.1082.500.10.2.3.3.1.6',
                'rx_power_table' => '1.3.6.1.4.1.3902.1082.500.10.2.3.9.1.2',
                'tx_power_table' => '1.3.6.1.4.1.3902.1082.500.10.2.3.9.1.1',
                'distance_table' => '1.3.6.1.4.1.3902.1082.500.10.2.3.10.1.2',
                'online_values'  => [1, 2, 3, 4, 5, 'working', 'online', 'up', 'enable'],
            ],
        ],
        'huawei' => [
            [
                'name' => 'HUAWEI_GPON',
                'label' => 'Huawei GPON (MA5680T / MA5608T / MA5800)',
                'status_table' => '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.11',
                'name_table'   => '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.3',
                'sn_table'     => '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.9',
                'rx_power_table' => '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.4',
                'distance_table' => '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.20',
                'online_values'  => [1, 5, 'active', 'online'],
            ],
            [
                'name' => 'HUAWEI_GPON_ALT',
                'label' => 'Huawei GPON (MA5683T / MIB v2)',
                'status_table' => '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.9',
                'name_table'   => '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.3',
                'sn_table'     => '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.9',
                'rx_power_table' => '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.4',
                'online_values'  => [1, 2, 3, 4, 5],
            ],
        ],
        'bdcom' => [
            [
                'name' => 'BDCOM_EPON',
                'label' => 'BDCOM EPON (P3310B / P3600 / P3608 / P3616)',
                'status_table' => '1.3.6.1.4.1.3320.101.10.1.1.26',
                'name_table'   => '1.3.6.1.4.1.3320.101.11.1.1.4',
                'sn_table'     => '1.3.6.1.4.1.3320.101.10.1.1.3',
                'tx_power_table' => '1.3.6.1.4.1.3320.101.10.5.1.6',
                'rx_power_table' => '1.3.6.1.4.1.3320.101.10.5.1.5',
                'distance_table' => '1.3.6.1.4.1.3320.101.10.1.1.33',
                'temp_table'     => '1.3.6.1.4.1.3320.101.10.5.1.2',
                'port_table'     => '1.3.6.1.2.1.2.2.1.2',
                'online_values'  => [3, 'authenticated', 'working', 'online'],
            ],
            [
                'name' => 'BDCOM_EPON_ALT',
                'label' => 'BDCOM EPON (Alternate MIB / V10)',
                'status_table' => '1.3.6.1.4.1.3320.101.10.1.1.4',
                'name_table'   => '1.3.6.1.4.1.3320.101.11.1.1.4',
                'sn_table'     => '1.3.6.1.4.1.3320.101.10.1.1.1',
                'tx_power_table' => '1.3.6.1.4.1.3320.101.10.5.1.6',
                'rx_power_table' => '1.3.6.1.4.1.3320.101.10.5.1.5',
                'port_table'     => '1.3.6.1.2.1.2.2.1.2',
                'online_values'  => [3, 'authenticated', 'working', 'online'],
            ],
            [
                'name' => 'BDCOM_GPON',
                'label' => 'BDCOM GPON (GP3600 Series)',
                'status_table' => '1.3.6.1.4.1.3320.101.10.1.1.7',
                'name_table'   => '1.3.6.1.4.1.3320.101.10.1.1.3',
                'sn_table'     => '1.3.6.1.4.1.3320.101.10.1.1.4',
                'rx_power_table' => '1.3.6.1.4.1.3320.101.10.3.1.4',
                'online_values'  => [1, 2, 3, 4, 5],
            ],
        ],
        'cdata' => [
            [
                'name' => 'CDATA_EPON_17409',
                'label' => 'C-Data EPON (FD1104SN / FD1208S / FD1216S - Enterprise MIB 17409)',
                'status_table' => '1.3.6.1.4.1.17409.2.3.4.1.1.7',
                'name_table'   => '1.3.6.1.4.1.17409.2.3.4.1.1.2',
                'sn_table'     => '1.3.6.1.4.1.17409.2.3.4.1.1.4',
                'tx_power_table' => '1.3.6.1.4.1.17409.2.3.5.2.1.5',
                'rx_power_table' => '1.3.6.1.4.1.17409.2.3.5.2.1.4',
                'distance_table' => '1.3.6.1.4.1.17409.2.3.4.1.1.11',
                'temp_table'     => '1.3.6.1.4.1.17409.2.3.5.2.1.1',
                'online_values'  => [1, 2, 3, 4, 'working', 'online', 'authenticated', 'registered', 'up', 'enable'],
            ],
            [
                'name' => 'CDATA_GPON_17409',
                'label' => 'C-Data GPON (FD1608GS / FD1616GS - Enterprise MIB 17409)',
                'status_table' => '1.3.6.1.4.1.17409.2.8.4.1.1.7',
                'name_table'   => '1.3.6.1.4.1.17409.2.8.4.1.1.2',
                'sn_table'     => '1.3.6.1.4.1.17409.2.8.4.1.1.3',
                'tx_power_table' => '1.3.6.1.4.1.17409.2.8.5.1.1.5',
                'rx_power_table' => '1.3.6.1.4.1.17409.2.8.5.1.1.4',
                'distance_table' => '1.3.6.1.4.1.17409.2.8.4.1.1.10',
                'temp_table'     => '1.3.6.1.4.1.17409.2.8.5.1.1.1',
                'online_values'  => [1, 2, 3, 4, 5, 'working', 'online', 'authenticated', 'registered', 'up', 'enable'],
            ],
            [
                'name' => 'CDATA_EPON_AUTH_17409',
                'label' => 'C-Data EPON (Auth MIB 17409)',
                'status_table' => '1.3.6.1.4.1.17409.2.3.3.1.1.3',
                'name_table'   => '1.3.6.1.4.1.17409.2.3.3.1.1.2',
                'sn_table'     => '1.3.6.1.4.1.17409.2.3.3.1.1.1',
                'tx_power_table' => '1.3.6.1.4.1.17409.2.3.5.2.1.5',
                'rx_power_table' => '1.3.6.1.4.1.17409.2.3.5.2.1.4',
                'online_values'  => [1, 2, 3, 4, 'working', 'online', 'authenticated', 'registered', 'permit', 'bound'],
            ],
            [
                'name' => 'CDATA_GPON_AUTH_17409',
                'label' => 'C-Data GPON (Auth MIB 17409)',
                'status_table' => '1.3.6.1.4.1.17409.2.8.3.1.1.3',
                'name_table'   => '1.3.6.1.4.1.17409.2.8.3.1.1.2',
                'sn_table'     => '1.3.6.1.4.1.17409.2.8.3.1.1.1',
                'tx_power_table' => '1.3.6.1.4.1.17409.2.8.5.1.1.5',
                'rx_power_table' => '1.3.6.1.4.1.17409.2.8.5.1.1.4',
                'online_values'  => [1, 2, 3, 4, 5, 'working', 'online', 'authenticated', 'registered', 'permit', 'bound'],
            ],
            [
                'name' => 'CDATA_CORTINA',
                'label' => 'C-Data EPON (Cortina / BDCOM Based - MIB 3320)',
                'status_table' => '1.3.6.1.4.1.3320.101.10.1.1.26',
                'name_table'   => '1.3.6.1.4.1.3320.101.10.1.1.79',
                'sn_table'     => '1.3.6.1.4.1.3320.101.10.1.1.3',
                'tx_power_table' => '1.3.6.1.4.1.3320.101.10.5.1.5',
                'rx_power_table' => '1.3.6.1.4.1.3320.101.10.5.1.6',
                'distance_table' => '1.3.6.1.4.1.3320.101.10.1.1.33',
                'temp_table'     => '1.3.6.1.4.1.3320.101.10.5.1.7',
                'online_values'  => [1, 2, 3, 4, 'working', 'online', 'authenticated', 'registered'],
            ],
            [
                'name' => 'CDATA_EPON_34592',
                'label' => 'C-Data EPON (FD1104 / FD1208 - MIB 34592)',
                'status_table' => '1.3.6.1.4.1.34592.1.3.100.12.1.1.1.15',
                'name_table'   => '1.3.6.1.4.1.34592.1.3.100.12.1.1.1.10',
                'sn_table'     => '1.3.6.1.4.1.34592.1.3.100.12.1.1.1.10',
                'rx_power_table' => '1.3.6.1.4.1.34592.1.3.100.12.1.1.1.21',
                'online_values'  => [1, 2, 3, 4, 'working', 'online', 'authenticated'],
            ],
            [
                'name' => 'CDATA_GPON_34592',
                'label' => 'C-Data GPON (FD1608 / FD1616 - MIB 34592)',
                'status_table' => '1.3.6.1.4.1.34592.1.3.100.12.1.1.1.15',
                'name_table'   => '1.3.6.1.4.1.34592.1.3.100.12.1.1.1.10',
                'sn_table'     => '1.3.6.1.4.1.34592.1.3.100.12.1.1.1.10',
                'rx_power_table' => '1.3.6.1.4.1.34592.1.3.100.12.1.1.1.21',
                'online_values'  => [1, 2, 3, 4, 5, 'working', 'online', 'authenticated'],
            ],
        ],
        'fiberhome' => [
            [
                'name' => 'FIBERHOME_GPON',
                'label' => 'Fiberhome GPON (AN5516)',
                'status_table' => '1.3.6.1.4.1.27332.1.1.1.8.1.7',
                'name_table'   => '1.3.6.1.4.1.27332.1.1.1.8.1.3',
                'sn_table'     => '1.3.6.1.4.1.27332.1.1.1.8.1.4',
                'rx_power_table' => '1.3.6.1.4.1.27332.1.1.1.11.1.4',
                'online_values'  => [1, 2, 3],
            ],
        ],
        'other' => [
            [
                'name' => 'GENERIC_CORTINA_EPON',
                'label' => 'Generic EPON Cortina / BDCOM / HSGQ (MIB 3320)',
                'status_table' => '1.3.6.1.4.1.3320.101.10.1.1.26',
                'name_table'   => '1.3.6.1.4.1.3320.101.10.1.1.79',
                'sn_table'     => '1.3.6.1.4.1.3320.101.10.1.1.3',
                'tx_power_table' => '1.3.6.1.4.1.3320.101.10.5.1.5',
                'rx_power_table' => '1.3.6.1.4.1.3320.101.10.5.1.6',
                'distance_table' => '1.3.6.1.4.1.3320.101.10.1.1.33',
                'temp_table'     => '1.3.6.1.4.1.3320.101.10.5.1.7',
                'online_values'  => [1, 2, 3, 4],
            ],
            [
                'name' => 'GENERIC_VSOL_EPON',
                'label' => 'Generic EPON VSOL (MIB 37950)',
                'status_table' => '1.3.6.1.4.1.37950.1.1.5.13.1.1.4',
                'name_table'   => '1.3.6.1.4.1.37950.1.1.5.13.1.1.10',
                'sn_table'     => '1.3.6.1.4.1.37950.1.1.5.13.1.1.2',
                'rx_power_table' => '1.3.6.1.4.1.37950.1.1.5.13.1.1.21',
                'online_values'  => [1, 2, 3, 4],
            ],
            [
                'name' => 'GENERIC_CDATA_17409',
                'label' => 'Generic C-Data (Enterprise MIB 17409)',
                'status_table' => '1.3.6.1.4.1.17409.2.3.4.1.1.7',
                'name_table'   => '1.3.6.1.4.1.17409.2.3.4.1.1.2',
                'sn_table'     => '1.3.6.1.4.1.17409.2.3.4.1.1.4',
                'tx_power_table' => '1.3.6.1.4.1.17409.2.3.5.2.1.5',
                'rx_power_table' => '1.3.6.1.4.1.17409.2.3.5.2.1.4',
                'distance_table' => '1.3.6.1.4.1.17409.2.3.4.1.1.11',
                'temp_table'     => '1.3.6.1.4.1.17409.2.3.5.2.1.1',
                'online_values'  => [1, 2, 3, 4],
            ],
            [
                'name' => 'GENERIC_HIOSO_EPON',
                'label' => 'Generic HIOSO (MIB 25355)',
                'status_table' => '1.3.6.1.4.1.25355.3.2.6.3.2.1.39',
                'name_table'   => '1.3.6.1.4.1.25355.3.2.6.3.2.1.37',
                'sn_table'     => '1.3.6.1.4.1.25355.3.2.6.3.2.1.11',
                'tx_power_table' => '1.3.6.1.4.1.25355.3.2.6.14.2.1.4',
                'rx_power_table' => '1.3.6.1.4.1.25355.3.2.6.14.2.1.8',
                'distance_table' => '1.3.6.1.4.1.25355.3.2.6.3.2.1.25',
                'temp_table'     => '1.3.6.1.4.1.25355.3.2.6.14.2.1.7',
                'online_values'  => [1, 3, 4],
            ],
            [
                'name' => 'GENERIC_VSOL_GPON',
                'label' => 'Generic VSOL GPON (MIB 37950)',
                'status_table' => '1.3.6.1.4.1.37950.1.2.6.2.1.8',
                'name_table'   => '1.3.6.1.4.1.37950.1.2.6.2.1.4',
                'sn_table'     => '1.3.6.1.4.1.37950.1.2.6.2.1.2',
                'rx_power_table' => '1.3.6.1.4.1.37950.1.2.6.3.1.1',
                'distance_table' => '1.3.6.1.4.1.37950.1.2.6.3.1.3',
                'online_values'  => [1, 2, 3, 4, 5],
            ],
            [
                'name' => 'GENERIC_ZTE_GPON',
                'label' => 'Generic ZTE GPON (MIB 3902)',
                'status_table' => '1.3.6.1.4.1.3902.1082.500.10.2.3.3.1.9',
                'name_table'   => '1.3.6.1.4.1.3902.1082.500.10.2.3.3.1.2',
                'sn_table'     => '1.3.6.1.4.1.3902.1082.500.10.2.3.3.1.6',
                'rx_power_table' => '1.3.6.1.4.1.3902.1015.1010.11.2.1.2',
                'distance_table' => '1.3.6.1.4.1.3902.1015.1010.11.2.1.4',
                'online_values'  => [1, 3, 'working', 'online'],
            ],
            [
                'name' => 'GENERIC_HUAWEI_GPON',
                'label' => 'Generic Huawei GPON (MIB 2011)',
                'status_table' => '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.11',
                'name_table'   => '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.3',
                'sn_table'     => '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.9',
                'rx_power_table' => '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.4',
                'distance_table' => '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.20',
                'online_values'  => [1, 5, 'active', 'online'],
            ],
        ],
    ];

    /**
     * Poll OLT and sync discovered ONUs & system resources into database
     */
    public function pollOlt(Olt $olt): array
    {
        $mode = strtolower($olt->connection_mode ?? 'snmp');

        if ($mode === 'telnet') {
            return $this->pollViaTelnet($olt);
        }

        // 1. Run SNMP Walk
        $snmpResult = $this->pollViaSnmp($olt);
        $telnetResult = null;

        // 2. If Telnet credentials exist, or in hybrid mode, also poll Telnet and take the best result
        if ($mode === 'hybrid' || $olt->telnet_port || ($olt->username && $olt->password)) {
            try {
                $telnetResult = $this->pollViaTelnet($olt);
                if (!empty($telnetResult['success']) && ($telnetResult['count'] ?? 0) > 0) {
                    $snmpCount = $snmpResult['count'] ?? 0;
                    $snmpOnline = $snmpResult['online_count'] ?? 0;
                    $telnetCount = $telnetResult['count'] ?? 0;
                    $telnetOnline = $telnetResult['online_count'] ?? 0;

                    if (empty($snmpResult['success']) || $snmpCount < $telnetCount || ($snmpOnline === 0 && $telnetOnline > 0)) {
                        return $telnetResult;
                    }
                }
            } catch (\Throwable $e) {
                Log::debug("OltNms: Telnet poll in pollOlt notice: " . $e->getMessage());
            }
        }

        if (!empty($snmpResult['success']) && ($snmpResult['count'] ?? 0) > 0) {
            return $snmpResult;
        }

        // 3. If HTTP Web API (GoAhead / EPON System) credentials exist
        if ($olt->username && $olt->password) {
            $httpResult = $this->pollViaHttp($olt);
            if ($httpResult['success'] && ($httpResult['count'] ?? 0) > 0) {
                return $httpResult;
            }
        }

        return $telnetResult ?? $httpResult ?? $snmpResult;
    }

    /**
     * Poll via SNMP Walk with automatic v2c & v1 fallback
     */
    public function pollViaSnmp(Olt $olt): array
    {
        $brand = strtolower($olt->model ?? 'hioso');
        $selectedProfiles = self::BRAND_PROFILES[$brand] ?? self::BRAND_PROFILES['hioso'];

        if ($olt->submodel) {
            foreach ($selectedProfiles as $idx => $p) {
                if ($p['name'] === $olt->submodel) {
                    $selected = $selectedProfiles[$idx];
                    unset($selectedProfiles[$idx]);
                    array_unshift($selectedProfiles, $selected);
                    break;
                }
            }
        }

        // Add other brand profiles as fallback if selected brand doesn't match
        $otherProfiles = [];
        foreach (self::BRAND_PROFILES as $b => $plist) {
            if ($b !== $brand) {
                foreach ($plist as $p) {
                    $otherProfiles[] = $p;
                }
            }
        }
        $profiles = array_merge($selectedProfiles, $otherProfiles);

        $host = trim((string) $olt->host);
        $snmpPort = (int) ($olt->snmp_port ?: ($olt->port ?: 161));
        if (str_contains($host, ':')) {
            [$h, $p] = explode(':', $host, 2);
            $host = $h;
            if (is_numeric($p) && empty($olt->snmp_port)) {
                $snmpPort = (int) $p;
            }
        }

        $community = $olt->snmp_community ?: 'public';
        $discoveredOnusMap = [];
        $activeProfile = null;
        $pollSuccess = false;
        $client = null;

        $snmpVersions = [2, 1];

        foreach ($snmpVersions as $ver) {
            try {
                $client = new SnmpClient([
                    'host' => $host,
                    'port' => $snmpPort,
                    'version' => $ver,
                    'community' => $community,
                    'timeout_connect' => 4,
                    'timeout_read' => 6,
                ]);

                // Auto-detect vendor from sysDescr and sysName if available
                try {
                    $sysDescrVal = $client->getValue('1.3.6.1.2.1.1.1.0');
                    $sysNameVal = $client->getValue('1.3.6.1.2.1.1.5.0');
                    $rawDescr = (is_object($sysDescrVal) && method_exists($sysDescrVal, 'getValue')) ? $sysDescrVal->getValue() : (string)$sysDescrVal;
                    $rawName = (is_object($sysNameVal) && method_exists($sysNameVal, 'getValue')) ? $sysNameVal->getValue() : (string)$sysNameVal;
                    $combined = strtolower($rawDescr . ' ' . $rawName);

                    $detectedBrand = null;
                    if (str_contains($combined, 'bdcom') || str_contains($combined, 'p3608') || str_contains($combined, 'p3310') || str_contains($combined, 'p3600')) {
                        $detectedBrand = 'bdcom';
                    } elseif (str_contains($combined, 'c-data') || str_contains($combined, 'cdata') || str_contains($combined, 'fd1104') || str_contains($combined, 'fd1208') || str_contains($combined, 'fd1608') || str_contains($combined, 'fd1616')) {
                        $detectedBrand = 'cdata';
                    } elseif (str_contains($combined, 'vsol') || str_contains($combined, 'v-sol') || str_contains($combined, 'v1600')) {
                        $detectedBrand = 'vsol';
                    } elseif (str_contains($combined, 'hsgq') || str_contains($combined, 'e04m') || str_contains($combined, 'g08')) {
                        $detectedBrand = 'hsgq';
                    } elseif (str_contains($combined, 'zte') || str_contains($combined, 'c300') || str_contains($combined, 'c320') || str_contains($combined, 'c600')) {
                        $detectedBrand = 'zte';
                    } elseif (str_contains($combined, 'huawei') || str_contains($combined, 'smartax') || str_contains($combined, 'ma5680') || str_contains($combined, 'ma5608') || str_contains($combined, 'ma5800')) {
                        $detectedBrand = 'huawei';
                    } elseif (str_contains($combined, 'fiberhome') || str_contains($combined, 'an5516')) {
                        $detectedBrand = 'fiberhome';
                    } elseif (str_contains($combined, 'hioso') || str_contains($combined, 'ha7302') || str_contains($combined, 'ha7304') || str_contains($combined, 'ha7308')) {
                        $detectedBrand = 'hioso';
                    }

                    if ($detectedBrand) {
                        $brand = $detectedBrand;
                        if ($olt->model === 'other' || empty($olt->model)) {
                            $olt->model = $detectedBrand;
                            $olt->saveQuietly();
                        }
                        if (isset(self::BRAND_PROFILES[$detectedBrand])) {
                            $selectedProfiles = self::BRAND_PROFILES[$detectedBrand];
                            $otherProfiles = [];
                            foreach (self::BRAND_PROFILES as $b => $plist) {
                                if ($b !== $detectedBrand) {
                                    foreach ($plist as $p) {
                                        $otherProfiles[] = $p;
                                    }
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {}

                // Phase 1: Try brand-matching profiles (merge all matches e.g. GPON + EPON cards or multi-subtrees)
                foreach ($selectedProfiles as $profile) {
                    try {
                        // Walk Status table with candidate tables
                        $statusWalk = [];
                        $activeStatusTable = $profile['status_table'] ?? '';
                        $statusCandidateTables = array_filter(array_unique([
                            $profile['status_table'] ?? null,
                            // ZTE GPON / EPON / C600
                            '1.3.6.1.4.1.3902.1082.500.10.2.3.8.1.4',
                            '1.3.6.1.4.1.3902.1082.500.10.2.3.3.1.9',
                            '1.3.6.1.4.1.3902.1012.3.28.2.1.4',
                            '1.3.6.1.4.1.3902.1082.500.12.2.3.3.1.10',
                            // C-Data EPON & GPON (17409)
                            '1.3.6.1.4.1.17409.2.3.4.1.1.7',
                            '1.3.6.1.4.1.17409.2.8.4.1.1.7',
                            '1.3.6.1.4.1.17409.2.3.3.1.1.3',
                            '1.3.6.1.4.1.17409.2.8.3.1.1.3',
                            // BDCOM EPON & GPON
                            '1.3.6.1.4.1.3320.101.10.1.1.26',
                            '1.3.6.1.4.1.3320.101.10.1.1.4',
                            '1.3.6.1.4.1.3320.101.10.1.1.7',
                            // Huawei GPON
                            '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.11',
                            '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.9',
                            // VSOL EPON & GPON
                            '1.3.6.1.4.1.37950.1.1.5.13.1.1.4',
                            '1.3.6.1.4.1.37950.1.2.6.2.1.8',
                            '1.3.6.1.4.1.37950.1.2.5.1.1.8',
                            '1.3.6.1.4.1.37950.1.1.5.12.1.1.8',
                            // HSGQ EPON & GPON
                            '1.3.6.1.4.1.34592.1.3.100.12.1.1.1.15',
                            // Hioso EPON
                            '1.3.6.1.4.1.25355.3.2.6.3.2.1.39',
                            '1.3.6.1.4.1.25355.3.3.1.1.1.11',
                            // DBC
                            '1.3.6.1.4.1.27332.1.1.1.8.1.7',
                        ]));
                        foreach ($statusCandidateTables as $tableOid) {
                            try {
                                $items = $this->collectWalkItems($client->walk($tableOid));
                                if (!empty($items)) {
                                    $statusWalk = $items;
                                    $activeStatusTable = $tableOid;
                                    break;
                                }
                            } catch (\Throwable $e) {}
                        }

                        // Walk SN table with candidate tables
                        $snWalk = [];
                        $activeSnTable = $profile['sn_table'] ?? '';
                        $snCandidateTables = array_filter(array_unique([
                            $profile['sn_table'] ?? null,
                            '1.3.6.1.4.1.3902.1082.500.10.2.3.8.1.3',
                            '1.3.6.1.4.1.3902.1082.500.10.2.3.3.1.6',
                            '1.3.6.1.4.1.3902.1082.500.12.2.3.3.1.3',
                            '1.3.6.1.4.1.3902.1012.3.28.1.1.2',
                            '1.3.6.1.4.1.17409.2.3.4.1.1.4',
                            '1.3.6.1.4.1.17409.2.8.4.1.1.3',
                            '1.3.6.1.4.1.17409.2.3.3.1.1.1',
                            '1.3.6.1.4.1.17409.2.8.3.1.1.1',
                            '1.3.6.1.4.1.25355.3.2.6.3.2.1.11',
                            '1.3.6.1.4.1.3320.101.10.1.1.3',
                            '1.3.6.1.4.1.37950.1.1.5.13.1.1.2',
                            '1.3.6.1.4.1.37950.1.2.6.2.1.2',
                            '1.3.6.1.4.1.37950.1.2.5.1.1.2',
                            '1.3.6.1.4.1.34592.1.3.100.12.1.1.1.10',
                            '1.3.6.1.4.1.27332.1.1.1.8.1.4',
                        ]));
                        foreach ($snCandidateTables as $tableOid) {
                            try {
                                $items = $this->collectWalkItems($client->walk($tableOid));
                                if (!empty($items)) {
                                    $snWalk = $items;
                                    $activeSnTable = $tableOid;
                                    break;
                                }
                            } catch (\Throwable $e) {}
                        }

                        // Walk Name table with candidate tables
                        $nameWalk = [];
                        $activeNameTable = $profile['name_table'] ?? '';
                        $nameCandidateTables = array_filter(array_unique([
                            $profile['name_table'] ?? null,
                            '1.3.6.1.4.1.3902.1082.500.10.2.3.3.1.2',
                            '1.3.6.1.4.1.3902.1082.500.10.2.3.3.1.3',
                            '1.3.6.1.4.1.3902.1082.500.12.2.3.3.1.2',
                            '1.3.6.1.4.1.3902.1012.3.28.1.1.3',
                            '1.3.6.1.4.1.17409.2.3.4.1.1.2',
                            '1.3.6.1.4.1.17409.2.8.4.1.1.2',
                            '1.3.6.1.4.1.17409.2.3.3.1.1.2',
                            '1.3.6.1.4.1.17409.2.8.3.1.1.2',
                            '1.3.6.1.4.1.25355.3.2.6.3.2.1.37',
                            '1.3.6.1.4.1.25355.3.3.1.1.1.2',
                            '1.3.6.1.4.1.3320.101.10.1.1.79',
                            '1.3.6.1.4.1.3320.101.11.1.1.4',
                            '1.3.6.1.4.1.3320.101.10.1.1.3',
                            '1.3.6.1.4.1.37950.1.1.5.13.1.1.10',
                            '1.3.6.1.4.1.37950.1.2.6.2.1.4',
                            '1.3.6.1.4.1.37950.1.1.5.12.1.1.4',
                            '1.3.6.1.4.1.37950.1.2.5.1.1.4',
                            '1.3.6.1.4.1.34592.1.3.100.12.1.1.1.10',
                            '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.3',
                            '1.3.6.1.4.1.27332.1.1.1.8.1.3',
                        ]));
                        foreach ($nameCandidateTables as $tableOid) {
                            try {
                                $items = $this->collectWalkItems($client->walk($tableOid));
                                if (!empty($items)) {
                                    $nameWalk = $items;
                                    $activeNameTable = $tableOid;
                                    break;
                                }
                            } catch (\Throwable $e) {}
                        }

                        // If no status, no SN, and no Name, skip this profile
                        if (empty($statusWalk) && empty($snWalk) && empty($nameWalk)) {
                            continue;
                        }

                        if (!$activeProfile) {
                            $activeProfile = $profile;
                            $olt->submodel = $profile['name'];
                        }

                        // Walk Rx Optical Power table
                        $rxWalk = [];
                        $activeRxTable = $profile['rx_power_table'] ?? '';
                        $rxCandidateTables = array_filter(array_unique([
                            $profile['rx_power_table'] ?? null,
                            '1.3.6.1.4.1.3902.1082.500.10.2.3.9.1.2',
                            '1.3.6.1.4.1.3902.1082.500.10.2.3.9.1.3',
                            '1.3.6.1.4.1.3902.1082.500.20.2.2.2.1.10',
                            '1.3.6.1.4.1.3902.1015.1010.11.2.1.2',
                            '1.3.6.1.4.1.3902.1082.500.12.2.3.7.1.3',
                            '1.3.6.1.4.1.17409.2.3.5.2.1.4',
                            '1.3.6.1.4.1.17409.2.8.5.1.1.4',
                            '1.3.6.1.4.1.17409.2.3.5.2.1.3',
                            '1.3.6.1.4.1.17409.2.8.5.1.1.3',
                            '1.3.6.1.4.1.25355.3.2.6.14.2.1.8',
                            '1.3.6.1.4.1.25355.3.3.1.1.4.1.1',
                            '1.3.6.1.4.1.3320.101.10.5.1.6',
                            '1.3.6.1.4.1.3320.101.10.5.1.5',
                            '1.3.6.1.4.1.3320.101.10.3.1.4',
                            '1.3.6.1.4.1.50058.101.10.3.1.4',
                            '1.3.6.1.4.1.37950.1.2.6.3.1.1',
                            '1.3.6.1.4.1.37950.1.1.5.13.1.1.21',
                            '1.3.6.1.4.1.37950.1.1.5.12.1.1.25',
                            '1.3.6.1.4.1.37950.1.2.5.1.1.25',
                            '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.4',
                            '1.3.6.1.4.1.2011.6.128.1.1.2.51.1.4',
                            '1.3.6.1.4.1.34592.1.3.100.12.1.1.1.21',
                            '1.3.6.1.4.1.27332.1.1.1.11.1.4',
                        ]));
                        foreach ($rxCandidateTables as $tableOid) {
                            try {
                                $items = $this->collectWalkItems($client->walk($tableOid));
                                if (!empty($items)) {
                                    $rxWalk = $items;
                                    $activeRxTable = $tableOid;
                                    break;
                                }
                            } catch (\Throwable $e) {}
                        }

                        // Walk Tx Optical Power table
                        $txWalk = [];
                        $activeTxTable = $profile['tx_power_table'] ?? '';
                        $txCandidateTables = array_filter(array_unique([
                            $profile['tx_power_table'] ?? null,
                            '1.3.6.1.4.1.3902.1082.500.10.2.3.9.1.1',
                            '1.3.6.1.4.1.3902.1082.500.20.2.2.2.1.11',
                            '1.3.6.1.4.1.3902.1082.500.1.2.4.2.1.1',
                            '1.3.6.1.4.1.3902.1015.1010.11.2.1.1',
                            '1.3.6.1.4.1.3902.1082.500.12.2.3.7.1.2',
                            '1.3.6.1.4.1.17409.2.3.5.2.1.5',
                            '1.3.6.1.4.1.17409.2.8.5.1.1.5',
                            '1.3.6.1.4.1.25355.3.2.6.14.2.1.4',
                            '1.3.6.1.4.1.3320.101.10.5.1.5',
                            '1.3.6.1.4.1.37950.1.2.6.3.1.2',
                            '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.5',
                        ]));
                        foreach ($txCandidateTables as $tableOid) {
                            try {
                                $items = $this->collectWalkItems($client->walk($tableOid));
                                if (!empty($items)) {
                                    $txWalk = $items;
                                    $activeTxTable = $tableOid;
                                    break;
                                }
                            } catch (\Throwable $e) {}
                        }

                        // Walk Distance table
                        $distWalk = [];
                        $activeDistTable = $profile['distance_table'] ?? '';
                        $distCandidateTables = array_filter(array_unique([
                            $profile['distance_table'] ?? null,
                            '1.3.6.1.4.1.3902.1082.500.10.2.3.10.1.2',
                            '1.3.6.1.4.1.3902.1015.1010.11.2.1.4',
                            '1.3.6.1.4.1.17409.2.3.4.1.1.11',
                            '1.3.6.1.4.1.17409.2.8.4.1.1.10',
                            '1.3.6.1.4.1.3320.101.10.1.1.33',
                            '1.3.6.1.4.1.25355.3.2.6.3.2.1.25',
                            '1.3.6.1.4.1.37950.1.2.6.3.1.3',
                            '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.20',
                        ]));
                        foreach ($distCandidateTables as $tableOid) {
                            try {
                                $items = $this->collectWalkItems($client->walk($tableOid));
                                if (!empty($items)) {
                                    $distWalk = $items;
                                    $activeDistTable = $tableOid;
                                    break;
                                }
                            } catch (\Throwable $e) {}
                        }

                        // Walk Temperature table
                        $tempWalk = [];
                        $activeTempTable = $profile['temp_table'] ?? '';
                        $tempCandidateTables = array_filter(array_unique([
                            $profile['temp_table'] ?? null,
                            '1.3.6.1.4.1.3320.101.10.5.1.2',
                            '1.3.6.1.4.1.17409.2.3.5.2.1.1',
                            '1.3.6.1.4.1.17409.2.8.5.1.1.1',
                            '1.3.6.1.4.1.25355.3.2.6.14.2.1.7',
                            '1.3.6.1.4.1.3320.101.10.5.1.7',
                            '1.3.6.1.4.1.37950.1.2.6.3.1.4',
                        ]));
                        foreach ($tempCandidateTables as $tableOid) {
                            try {
                                $items = $this->collectWalkItems($client->walk($tableOid));
                                if (!empty($items)) {
                                    $tempWalk = $items;
                                    $activeTempTable = $tableOid;
                                    break;
                                }
                            } catch (\Throwable $e) {}
                        }

                        // Walk Interface Descr table
                        $portWalk = [];
                        try {
                            $portWalk = $this->collectWalkItems($client->walk($profile['port_table'] ?? '1.3.6.1.2.1.2.2.1.2'));
                        } catch (\Throwable $e) {}

                        $nameMap = $this->buildIndexMap($nameWalk, $activeNameTable);
                        $snMap = $this->buildIndexMap($snWalk, $activeSnTable);
                        $statusMap = $this->buildIndexMap($statusWalk, $activeStatusTable);
                        $rxMap = $this->buildIndexMap($rxWalk, $activeRxTable);
                        $txMap = $this->buildIndexMap($txWalk, $activeTxTable);
                        $distMap = $this->buildIndexMap($distWalk, $activeDistTable);
                        $tempMap = $this->buildIndexMap($tempWalk, $activeTempTable);
                        $portMap = $this->buildIndexMap($portWalk, $profile['port_table'] ?? '1.3.6.1.2.1.2.2.1.2');

                        $allIndexKeys = [];
                        foreach ($statusWalk as $item) {
                            $allIndexKeys[$this->extractIndex((string) $item->getOid(), $activeStatusTable)] = true;
                        }
                        foreach ($snWalk as $item) {
                            $allIndexKeys[$this->extractIndex((string) $item->getOid(), $activeSnTable)] = true;
                        }
                        foreach ($nameWalk as $item) {
                            $allIndexKeys[$this->extractIndex((string) $item->getOid(), $activeNameTable)] = true;
                        }

                        foreach (array_keys($allIndexKeys) as $index) {
                            $rawSn = $this->findValueInMap($snMap, $index);
                            $serialNumber = $this->formatSerialNumber($rawSn, $index);
                            $rawName = $this->findValueInMap($nameMap, $index, $serialNumber ?: $rawSn);

                            $cleanRawName = trim((string)$rawName);
                            $isGenericName = empty($cleanRawName) || in_array(strtoupper($cleanRawName), [
                                'NA', 'N/A', 'NULL', 'NONE', '1(GPON)', '2(GPON)', '1(EPON)', '2(EPON)', 'GPON', 'EPON', 'ONU', '1', '0'
                            ], true) || preg_match('/^\d+\([a-z0-9_-]+\)$/i', $cleanRawName);

                            if ($isGenericName) {
                                if ($brand === 'zte' || (is_numeric(explode('.', $index)[0]) && (int)explode('.', $index)[0] >= 268435456)) {
                                    $zte = $this->decodeZteIndex($index);
                                    $name = $zte['onu_name'] ?? (!empty($serialNumber) ? $serialNumber : "ONU-{$index}");
                                } else {
                                    $name = (!empty($serialNumber) && !str_starts_with($serialNumber, 'ONUIDX-')) ? $serialNumber : "ONU-{$index}";
                                }
                            } else {
                                $name = $cleanRawName;
                            }

                            $rawRx = $this->findValueInMap($rxMap, $index, $serialNumber);
                            $rxPower = $this->parseRxOpticalPower($rawRx, $brand);

                            $rawTx = $this->findValueInMap($txMap, $index, $serialNumber);
                            $txPower = $this->parseTxOpticalPower($rawTx, $brand);

                            $rawDist = $this->findValueInMap($distMap, $index, $serialNumber);
                            $distance = $rawDist !== null && is_numeric($rawDist) ? (float) $rawDist : null;

                            $rawTemp = $this->findValueInMap($tempMap, $index, $serialNumber);
                            $temperature = $this->parseTemperature($rawTemp);

                            $ponPort = $this->extractPonPort($index, $brand, $portMap);

                            $rawVal = $this->findValueInMap($statusMap, $index, $serialNumber);
                            if ($rawVal === null && isset($statusMap[$index])) {
                                $rawVal = $statusMap[$index];
                            }

                            $val = (is_object($rawVal) && method_exists($rawVal, 'getValue')) ? $rawVal->getValue() : (string)$rawVal;

                            $valInt = null;
                            if (is_numeric($val)) {
                                $valInt = (int) $val;
                            } elseif (preg_match('/(\d+)/', (string) $val, $m)) {
                                $valInt = (int) $m[1];
                            }

                            $valStr = strtolower(trim((string) $val));
                            $isOnline = false;

                            $allowedValues = $profile['online_values'] ?? [1, 2, 3, 4, 5, 'working', 'online', 'syncmib', 'logging', 'ready', 'active', 'authenticated', 'up', 'enable'];
                            if ($valInt !== null && (in_array($valInt, $allowedValues, true) || in_array((string)$valInt, $allowedValues, true))) {
                                $isOnline = true;
                            }

                            if (!$isOnline && (
                                str_contains($valStr, 'online') ||
                                str_contains($valStr, 'up') ||
                                str_contains($valStr, 'auth') ||
                                str_contains($valStr, 'reg') ||
                                str_contains($valStr, 'active') ||
                                str_contains($valStr, 'work') ||
                                str_contains($valStr, 'operat') ||
                                str_contains($valStr, 'enable') ||
                                str_contains($valStr, 'syncmib') ||
                                str_contains($valStr, 'logging') ||
                                str_contains($valStr, 'ready')
                            )) {
                                if (!str_contains($valStr, 'offline') && !str_contains($valStr, 'unauth') && !str_contains($valStr, 'down') && !str_contains($valStr, 'disable') && !str_contains($valStr, 'deact') && !str_contains($valStr, 'los') && !str_contains($valStr, 'dying')) {
                                    $isOnline = true;
                                }
                            }

                            // Optical power presence confirmation
                            if (!$isOnline && $rxPower !== null && $rxPower >= -42.0 && $rxPower <= -3.0) {
                                $isOnline = true;
                            }

                            // If status walk was empty or status value wasn't found for this ONU, but valid registered SN exists
                            if (!$isOnline && ($rawVal === null || empty($statusWalk))) {
                                if ($rxPower !== null && $rxPower >= -42.0 && $rxPower <= -3.0) {
                                    $isOnline = true;
                                } elseif (!empty($serialNumber) && !str_starts_with($serialNumber, 'ONU-IDX-')) {
                                    $isOnline = true;
                                }
                            }

                            $offlineReason = null;
                            if (!$isOnline) {
                                if ($valInt === 3 || $valInt === 4 || $valInt === 6 || str_contains($valStr, 'pwr') || str_contains($valStr, 'power') || str_contains($valStr, 'gasp') || str_contains($valStr, 'dereg') || str_contains($valStr, 'dying')) {
                                    $offlineReason = 'power_down';
                                } elseif (str_contains($valStr, 'los') || str_contains($valStr, 'loss')) {
                                    $offlineReason = 'los';
                                } else {
                                    $offlineReason = 'down';
                                }
                            }

                            $cleanSerial = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string)$serialNumber));
                            $uniqueKey = (!empty($cleanSerial) && !str_starts_with($cleanSerial, 'ONUIDX-'))
                                ? $cleanSerial
                                : "{$ponPort}:{$index}";

                            $discoveredOnusMap[$uniqueKey] = [
                                'index' => $index,
                                'name' => (string) $name,
                                'serial_number' => $serialNumber,
                                'pon_port' => $ponPort,
                                'status' => $isOnline ? 'online' : 'offline',
                                'offline_reason' => $isOnline ? null : $offlineReason,
                                'rx_power' => $rxPower,
                                'tx_power' => $txPower,
                                'distance' => $distance,
                                'temperature' => $temperature,
                            ];
                        }

                        $pollSuccess = true;
                    } catch (\Throwable $e) {
                        Log::debug("OltNms: Profile {$profile['name']} failed on {$host}:{$snmpPort}: {$e->getMessage()}");
                    }
                }

                // Phase 2: If primary brand profiles found NO ONUs, try other brand fallback profiles
                if (empty($discoveredOnusMap)) {
                    foreach ($otherProfiles as $profile) {
                        try {
                            $statusWalk = [];
                            $activeStatusTable = $profile['status_table'] ?? '';
                            try {
                                $sw = $client->walk($profile['status_table']);
                                $statusWalk = $this->collectWalkItems($sw);
                            } catch (\Throwable $e) {}

                            $snWalk = [];
                            $activeSnTable = $profile['sn_table'] ?? '';
                            try {
                                $sw = $client->walk($profile['sn_table']);
                                $snWalk = $this->collectWalkItems($sw);
                            } catch (\Throwable $e) {}

                            if (empty($statusWalk) && empty($snWalk)) continue;

                            $activeProfile = $profile;
                            $olt->submodel = $profile['name'];

                            $nameWalk = [];
                            try {
                                $nameWalk = $this->collectWalkItems($client->walk($profile['name_table'] ?? ''));
                            } catch (\Throwable $e) {}

                            $rxWalk = [];
                            try {
                                $rxWalk = $this->collectWalkItems($client->walk($profile['rx_power_table'] ?? ''));
                            } catch (\Throwable $e) {}

                            $nameMap = $this->buildIndexMap($nameWalk, $profile['name_table'] ?? '');
                            $snMap = $this->buildIndexMap($snWalk, $activeSnTable);
                            $statusMap = $this->buildIndexMap($statusWalk, $activeStatusTable);
                            $rxMap = $this->buildIndexMap($rxWalk, $profile['rx_power_table'] ?? '');

                            $allIndexKeys = [];
                            foreach ($statusWalk as $item) {
                                $allIndexKeys[$this->extractIndex((string) $item->getOid(), $activeStatusTable)] = true;
                            }
                            foreach ($snWalk as $item) {
                                $allIndexKeys[$this->extractIndex((string) $item->getOid(), $activeSnTable)] = true;
                            }

                            foreach (array_keys($allIndexKeys) as $index) {
                                $rawSn = $this->findValueInMap($snMap, $index);
                                $serialNumber = $this->formatSerialNumber($rawSn, $index);
                                $name = $this->findValueInMap($nameMap, $index, $serialNumber) ?: "ONU-{$index}";
                                $rawRx = $this->findValueInMap($rxMap, $index, $serialNumber);
                                $rxPower = $this->parseRxOpticalPower($rawRx, $brand);

                                $rawVal = $this->findValueInMap($statusMap, $index, $serialNumber);
                                if ($rawVal === null && isset($statusMap[$index])) {
                                    $rawVal = $statusMap[$index];
                                }
                                $val = (is_object($rawVal) && method_exists($rawVal, 'getValue')) ? $rawVal->getValue() : (string)$rawVal;
                                $valInt = is_numeric($val) ? (int)$val : null;
                                $isOnline = in_array($valInt, $profile['online_values'] ?? [1, 2, 3, 4, 5], true);

                                if (!$isOnline && $rxPower !== null && $rxPower >= -42.0 && $rxPower <= -3.0) {
                                    $isOnline = true;
                                }

                                if (!$isOnline && ($rawVal === null || empty($statusWalk)) && !empty($serialNumber) && !str_starts_with($serialNumber, 'ONU-IDX-')) {
                                    $isOnline = true;
                                }

                                $cleanSerial = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string)$serialNumber));
                                $uniqueKey = (!empty($cleanSerial) && !str_starts_with($cleanSerial, 'ONUIDX-'))
                                    ? $cleanSerial
                                    : "PON1:{$index}";

                                $discoveredOnusMap[$uniqueKey] = [
                                    'index' => $index,
                                    'name' => (string) $name,
                                    'serial_number' => $serialNumber,
                                    'pon_port' => 'PON 1',
                                    'status' => $isOnline ? 'online' : 'offline',
                                    'offline_reason' => $isOnline ? null : 'down',
                                    'rx_power' => $rxPower,
                                    'tx_power' => null,
                                    'distance' => null,
                                    'temperature' => null,
                                ];
                            }

                            $pollSuccess = true;
                            break;
                        } catch (\Throwable $e) {}
                    }
                }

                if (!empty($discoveredOnusMap)) {
                    break;
                }
            } catch (\Throwable $e) {
                Log::debug("OltNms: SNMP Connection failed on {$host}:{$snmpPort}: {$e->getMessage()}");
            }
        }

        $discoveredOnus = array_values($discoveredOnusMap);

        // Fetch System Resources (CPU, RAM, Temp, Uptime, etc.)
        $resources = $this->fetchSystemResources($olt, $client);
        $olt->hardware_metrics = $resources;

        return $this->persistDiscoveredOnus($olt, $discoveredOnus, $pollSuccess, $activeProfile['name'] ?? 'SNMP', 'SNMP');
    }

    /**
     * Fetch System Resources (CPU, Memory, Temp, Uptime, Hostname)
     */
    public function fetchSystemResources(Olt $olt, ?SnmpClient $snmp = null): array
    {
        $metrics = [
            'cpu' => null,
            'ram' => null,
            'ram_used_mb' => null,
            'ram_total_mb' => null,
            'temp' => null,
            'uptime' => null,
            'sys_name' => null,
            'sys_descr' => null,
            'pon_ports' => [],
            'updated_at' => now()->toIso8601String(),
        ];

        $host = trim((string) $olt->host);
        $snmpPort = (int) ($olt->snmp_port ?: ($olt->port ?: 161));
        if (str_contains($host, ':')) {
            [$h, $p] = explode(':', $host, 2);
            $host = $h;
            if (is_numeric($p) && empty($olt->snmp_port)) {
                $snmpPort = (int) $p;
            }
        }
        $community = $olt->snmp_community ?: 'public';

        try {
            if (!$snmp) {
                foreach ([2, 1] as $ver) {
                    try {
                        $candidate = new SnmpClient([
                            'host' => $host,
                            'port' => $snmpPort,
                            'version' => $ver,
                            'community' => $community,
                            'timeout_connect' => 3,
                            'timeout_read' => 3,
                        ]);
                        $testVal = $candidate->getValue('1.3.6.1.2.1.1.3.0');
                        if ($testVal !== null) {
                            $snmp = $candidate;
                            break;
                        }
                    } catch (\Throwable $e) {}
                }
            }

            if (!$snmp) {
                $snmp = new SnmpClient([
                    'host' => $host,
                    'port' => $snmpPort,
                    'version' => 2,
                    'community' => $community,
                    'timeout_connect' => 3,
                    'timeout_read' => 3,
                ]);
            }

            // 1. CPU Load (hrProcessorLoad & brand-specific enterprise OIDs)
            try {
                $cpuOids = [
                    '1.3.6.1.2.1.25.3.3.1.2.768',                        // Host Resources
                    '1.3.6.1.4.1.17409.2.2.1.1.4.1',                     // C-Data MIB 17409
                    '1.3.6.1.4.1.17409.2.1.1.1.1.1.3.1',                 // C-Data MIB 17409 Alt
                    '1.3.6.1.4.1.34592.1.3.100.1.1.1.3.0',               // C-Data MIB 34592
                    '1.3.6.1.4.1.37950.1.1.5.1.1.2',                     // VSOL
                    '1.3.6.1.4.1.25355.3.2.1.1.2.0',                     // HIOSO
                    '1.3.6.1.4.1.3902.1082.500.10.2.2.4.1.10.1.1.1',     // ZTE
                    '1.3.6.1.4.1.2011.6.128.1.1.2.23.1.14.0.0',          // Huawei
                    '1.3.6.1.4.1.3320.101.11.1.13.1',                    // HSGQ & BDCOM
                    '1.3.6.1.4.1.27332.1.1.1.9.1.12.1.1',                // FiberHome
                ];
                foreach ($cpuOids as $coid) {
                    $cpuVal = $snmp->getValue($coid);
                    if ($cpuVal !== null) {
                        $raw = is_object($cpuVal) && method_exists($cpuVal, 'getValue') ? $cpuVal->getValue() : (string) $cpuVal;
                        if (is_numeric($raw) && (float)$raw >= 0 && (float)$raw <= 100) {
                            $metrics['cpu'] = (int) $raw;
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {}

            // 2. RAM / Memory (hrStorage & brand-specific enterprise OIDs)
            try {
                $sizeVal = $snmp->getValue('1.3.6.1.2.1.25.2.3.1.5.1');
                $usedVal = $snmp->getValue('1.3.6.1.2.1.25.2.3.1.6.1');
                if ($sizeVal !== null && $usedVal !== null) {
                    $rawSize = (float) (is_object($sizeVal) && method_exists($sizeVal, 'getValue')) ? $sizeVal->getValue() : (string) $sizeVal;
                    $rawUsed = (float) (is_object($usedVal) && method_exists($usedVal, 'getValue')) ? $usedVal->getValue() : (string) $usedVal;
                    if ($rawSize > 0) {
                        $metrics['ram'] = (int) round(($rawUsed / $rawSize) * 100);
                        $metrics['ram_used_mb'] = round($rawUsed / 1024, 1);
                        $metrics['ram_total_mb'] = round($rawSize / 1024, 1);
                    }
                }

                if ($metrics['ram'] === null) {
                    $ramOids = [
                        '1.3.6.1.4.1.17409.2.2.1.1.5.1',                  // C-Data MIB 17409
                        '1.3.6.1.4.1.17409.2.1.1.1.1.1.4.1',              // C-Data MIB 17409 Alt
                        '1.3.6.1.4.1.34592.1.3.100.1.1.1.4.0',            // C-Data MIB 34592
                        '1.3.6.1.4.1.37950.1.1.5.1.1.4',                  // VSOL
                        '1.3.6.1.4.1.25355.3.2.1.1.3.0',                  // HIOSO
                        '1.3.6.1.4.1.3902.1082.500.10.2.2.4.1.11.1.1.1',  // ZTE
                        '1.3.6.1.4.1.2011.6.128.1.1.2.23.1.15.0.0',       // Huawei
                        '1.3.6.1.4.1.3320.101.11.1.14.1',                 // HSGQ & BDCOM
                        '1.3.6.1.4.1.27332.1.1.1.9.1.14.1.1',             // FiberHome
                    ];
                    foreach ($ramOids as $roid) {
                        $ramVal = $snmp->getValue($roid);
                        if ($ramVal !== null) {
                            $raw = is_object($ramVal) && method_exists($ramVal, 'getValue') ? $ramVal->getValue() : (string) $ramVal;
                            if (is_numeric($raw) && (float)$raw >= 0 && (float)$raw <= 100) {
                                $metrics['ram'] = (int) $raw;
                                break;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {}

            // 3. Uptime (sysUpTimeInstance)
            try {
                $upVal = $snmp->getValue('1.3.6.1.2.1.1.3.0');
                if ($upVal !== null) {
                    $rawUp = is_object($upVal) && method_exists($upVal, 'getValue') ? $upVal->getValue() : (string) $upVal;
                    if (is_numeric($rawUp)) {
                        $seconds = (int) round(((int) $rawUp) / 100);
                        $days = floor($seconds / 86400);
                        $hours = floor(($seconds % 86400) / 3600);
                        $minutes = floor(($seconds % 3600) / 60);
                        $metrics['uptime'] = ($days > 0 ? "{$days}h " : "") . "{$hours}j {$minutes}m";
                    }
                }
            } catch (\Throwable $e) {}

            // 4. System Name & Description
            try {
                $nameVal = $snmp->getValue('1.3.6.1.2.1.1.5.0');
                if ($nameVal !== null) {
                    $raw = is_object($nameVal) && method_exists($nameVal, 'getValue') ? $nameVal->getValue() : $nameVal;
                    $metrics['sys_name'] = self::safeUtf8String(trim((string) $raw, '" '));
                }
            } catch (\Throwable $e) {}

            try {
                $descrVal = $snmp->getValue('1.3.6.1.2.1.1.1.0');
                if ($descrVal !== null) {
                    $raw = is_object($descrVal) && method_exists($descrVal, 'getValue') ? $descrVal->getValue() : $descrVal;
                    $metrics['sys_descr'] = self::safeUtf8String(trim((string) $raw, '" '));
                }
            } catch (\Throwable $e) {}

            // 5. Temperature (Average across active sensors/modules or enterprise OIDs)
            try {
                $tempOids = [
                    '1.3.6.1.4.1.17409.2.2.1.1.3.1',                     // C-Data MIB 17409
                    '1.3.6.1.4.1.17409.2.1.1.1.1.1.2.1',                 // C-Data MIB 17409 Alt
                    '1.3.6.1.4.1.34592.1.3.100.1.1.1.2.0',               // C-Data MIB 34592
                    '1.3.6.1.4.1.37950.1.1.5.1.1.11',                    // VSOL
                    '1.3.6.1.4.1.25355.3.2.1.1.1.0',                     // HIOSO
                    '1.3.6.1.4.1.3902.1082.500.10.2.2.4.1.19.1.1.1',    // ZTE
                    '1.3.6.1.4.1.2011.6.128.1.1.2.23.1.14.0.0',         // Huawei
                    '1.3.6.1.4.1.3320.101.11.1.13.1',                   // HSGQ & BDCOM
                    '1.3.6.1.4.1.27332.1.1.1.9.1.12.1.1',               // FiberHome
                ];
                foreach ($tempOids as $toid) {
                    $tempVal = $snmp->getValue($toid);
                    if ($tempVal !== null) {
                        $raw = is_object($tempVal) && method_exists($tempVal, 'getValue') ? $tempVal->getValue() : (string) $tempVal;
                        if (is_numeric($raw) && (float)$raw > 0 && (float)$raw < 150) {
                            $metrics['temp'] = (float)$raw > 100 ? round((float)$raw / 10, 1) : round((float)$raw, 1);
                            break;
                        }
                    }
                }

                if ($metrics['temp'] === null) {
                    $tempWalk = $snmp->walk('1.3.6.1.4.1.25355.3.2.6.14.2.1.7');
                    $tempItems = $this->collectWalkItems($tempWalk);
                    $temps = [];
                    foreach ($tempItems as $tItem) {
                        $raw = $tItem->getValue();
                        $val = (float) (is_object($raw) && method_exists($raw, 'getValue') ? $raw->getValue() : (string) $raw);
                        if ($val > 0 && $val < 100) {
                            $temps[] = $val;
                        }
                    }
                    if (!empty($temps)) {
                        $metrics['temp'] = round(array_sum($temps) / count($temps), 1);
                    }
                }
            } catch (\Throwable $e) {}

        } catch (\Throwable $e) {
            Log::debug("OltNms: Fetch resources failed for {$olt->name}: {$e->getMessage()}");
        }

        $isDemo = ($olt->tenant?->slug === 'demo') || (session('tenant_slug') === 'demo');
        if ($isDemo) {
            if ($metrics['cpu'] === null) {
                $metrics['cpu'] = rand(3, 6);
            }
            if ($metrics['ram'] === null) {
                $metrics['ram'] = rand(36, 42);
                $metrics['ram_used_mb'] = 45.6;
                $metrics['ram_total_mb'] = 120.0;
            }
            if ($metrics['temp'] === null) {
                $metrics['temp'] = round(39.0 + (rand(1, 25) / 10), 1);
            }
            if ($metrics['uptime'] === null) {
                $metrics['uptime'] = '38 hari, 14:12:05';
            }
            if ($metrics['sys_name'] === null) {
                $metrics['sys_name'] = $olt->name;
            }
            if ($metrics['sys_descr'] === null) {
                $metrics['sys_descr'] = strtoupper($olt->model ?? 'HIOSO') . ' Standalone Gigabit Optical Line Terminal';
            }
        }

        // Summary per PON Port from DB ONUs
        $ponSummary = [];
        $onus = $olt->onus()->get(['pon_port', 'status']);
        foreach ($onus as $o) {
            $p = $o->pon_port ?: 'PON 1';
            if (!isset($ponSummary[$p])) {
                $ponSummary[$p] = ['total' => 0, 'online' => 0, 'offline' => 0];
            }
            $ponSummary[$p]['total']++;
            if ($o->status === 'online') {
                $ponSummary[$p]['online']++;
            } else {
                $ponSummary[$p]['offline']++;
            }
        }
        $metrics['pon_ports'] = $ponSummary;

        return self::safeUtf8($metrics);
    }

    private function collectWalkItems($walk): array
    {
        if (!$walk) return [];
        if (is_array($walk)) return $walk;

        $items = [];
        if (method_exists($walk, 'hasOids') && method_exists($walk, 'next')) {
            while ($walk->hasOids()) {
                $item = $walk->next();
                if ($item) $items[] = $item;
            }
        } elseif ($walk instanceof \Traversable) {
            foreach ($walk as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Test Telnet connectivity and authentication
     */
    public function testTelnetAuth(string $host, int $port = 23, ?string $username = 'admin', ?string $password = 'admin', ?string $enablePassword = null): array
    {
        $host = trim($host);
        if (str_contains($host, ':')) {
            [$host, ] = explode(':', $host, 2);
        }

        $fp = @fsockopen($host, $port, $errno, $errstr, 4);
        if (!$fp) {
            return [
                'success' => false,
                'port_open' => false,
                'message' => "Port {$port} tidak dapat dijangkau ({$errstr})",
            ];
        }

        stream_set_timeout($fp, 5);
        try {
            $this->performTelnetLogin($fp, $username ?: 'admin', $password ?: 'admin', $enablePassword);
            @fclose($fp);
            return [
                'success' => true,
                'port_open' => true,
                'message' => "Port {$port} Terbuka & Autentikasi Berhasil",
            ];
        } catch (\Throwable $e) {
            @fclose($fp);
            return [
                'success' => false,
                'port_open' => true,
                'message' => "Port {$port} Terbuka, namun " . $e->getMessage(),
            ];
        }
    }

    /**
     * Poll via Telnet CLI Commands
     */
    public function pollViaTelnet(Olt $olt): array
    {
        $host = trim((string) $olt->host);
        $telnetPort = (int) ($olt->telnet_port ?: 23);
        if (str_contains($host, ':')) {
            [$h, $p] = explode(':', $host, 2);
            $host = $h;
        }

        $username = $olt->username ?: 'admin';
        $password = $olt->password ?: 'admin';
        $enablePassword = $olt->enable_password;
        $brand = strtolower($olt->model ?? 'hioso');

        $discoveredOnus = [];

        try {
            $fp = @fsockopen($host, $telnetPort, $errno, $errstr, 4);
            if (!$fp) {
                throw new \Exception("Gagal koneksi Telnet ke {$host}:{$telnetPort} ({$errstr})");
            }

            stream_set_timeout($fp, 5);

            // Execute robust 2-stage login handshake
            $this->performTelnetLogin($fp, $username, $password, $enablePassword);

            // Brand-specific commands
            $output = '';
            if ($brand === 'hioso') {
                fwrite($fp, "show epon onu-information\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show optical-power-status\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                $discoveredOnus = $this->parseHiosoTelnetOutput($output);
            } elseif ($brand === 'vsol') {
                fwrite($fp, "show onu status\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show epon onu-information\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show onu opm-diag\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                $discoveredOnus = $this->parseVsolTelnetOutput($output);
            } elseif ($brand === 'cdata' || $brand === 'bdcom' || $brand === 'hsgq') {
                fwrite($fp, "show epon onu-information\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show onu status\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show onu info\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show onu ctc basic-info\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show optical-power-status\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show onu ctc optical-power\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show epon optical-transceiver-diagnosis\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show gpon onu state\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show gpon onu detail-info\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show ont info summary 0\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show onu opm-diag\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show mac-address-table\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);

                $discoveredOnus = $this->parseCdataTelnetOutput($output);
                if (empty($discoveredOnus)) {
                    $discoveredOnus = $this->parseHiosoTelnetOutput($output);
                }
                if (empty($discoveredOnus)) {
                    $discoveredOnus = $this->parseVsolTelnetOutput($output);
                }
                if (empty($discoveredOnus)) {
                    $discoveredOnus = $this->parseGenericTelnetOutput($output);
                }
            } elseif ($brand === 'zte') {
                fwrite($fp, "terminal length 0\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show gpon onu state\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show gpon onu baseinfo\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show gpon onu detail-info\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show gpon onu uncfg\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show pon power attenuation\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show epon onu-information\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show epon onu status\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show epon optical-transceiver-diagnosis\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                $discoveredOnus = $this->parseZteTelnetOutput($output);
                if (empty($discoveredOnus)) {
                    $discoveredOnus = $this->parseGenericTelnetOutput($output);
                }
            } elseif ($brand === 'huawei') {
                fwrite($fp, "display ont info summary 0\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                $discoveredOnus = $this->parseHuaweiTelnetOutput($output);
            } else {
                fwrite($fp, "show epon onu-information\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show onu status\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show optical-power-status\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show gpon onu state\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "display ont info summary 0\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);
                fwrite($fp, "show onu uncfg\r\n");
                $output .= $this->telnetReadUntil($fp, ['#', '>']);

                $discoveredOnus = $this->parseHiosoTelnetOutput($output);
                if (empty($discoveredOnus)) {
                    $discoveredOnus = $this->parseVsolTelnetOutput($output);
                }
                if (empty($discoveredOnus)) {
                    $discoveredOnus = $this->parseZteTelnetOutput($output);
                }
                if (empty($discoveredOnus)) {
                    $discoveredOnus = $this->parseHuaweiTelnetOutput($output);
                }
                if (empty($discoveredOnus)) {
                    $discoveredOnus = $this->parseGenericTelnetOutput($output);
                }
            }

            fclose($fp);

            return $this->persistDiscoveredOnus($olt, $discoveredOnus, !empty($discoveredOnus), 'TELNET CLI', 'Telnet');
        } catch (\Throwable $e) {
            Log::error("OltNms: Telnet scan error on {$host}: {$e->getMessage()}");
            return [
                'success' => false,
                'count' => 0,
                'message' => "Gagal Telnet ke OLT {$olt->name} ({$host}:{$telnetPort}): {$e->getMessage()}",
            ];
        }
    }

    /**
     * Perform unified 2-stage Telnet Login handshake
     */
    public function performTelnetLogin($fp, string $username, string $password, ?string $enablePassword = null): void
    {
        // 1. Wait specifically for login / username / password prompt (ignoring banner borders)
        $buffer = $this->telnetReadUntilLogin($fp, ['login:', 'username:', 'user:', 'password:']);
        if (stripos($buffer, 'login:') !== false || stripos($buffer, 'username:') !== false || stripos($buffer, 'user:') !== false) {
            fwrite($fp, $username . "\r\n");
            $this->telnetReadUntilLogin($fp, ['password:', 'pass:']);
            fwrite($fp, $password . "\r\n");
        } elseif (stripos($buffer, 'password:') !== false) {
            fwrite($fp, $password . "\r\n");
        }

        // 2. Read authenticated prompt
        $authPrompt = $this->telnetReadUntil($fp, ['#', '>', '%', '$', 'login:', 'username:', 'invalid', 'bad password', '%Error', 'incorrect', 'denied'], 4);
        if (
            stripos($authPrompt, 'invalid') !== false ||
            stripos($authPrompt, 'bad password') !== false ||
            stripos($authPrompt, 'incorrect') !== false ||
            stripos($authPrompt, 'denied') !== false ||
            stripos($authPrompt, '%Error') !== false ||
            stripos($authPrompt, 'login:') !== false ||
            stripos($authPrompt, 'username:') !== false
        ) {
            throw new \Exception("Autentikasi Telnet ditolak oleh OLT (Username atau Password salah).");
        }

        // 3. Enable mode
        if (!empty($enablePassword) || str_ends_with(rtrim($authPrompt), '>')) {
            fwrite($fp, "enable\r\n");
            $enBuf = $this->telnetReadUntil($fp, ['password:', 'pass:', '#', '>'], 3);
            if (stripos($enBuf, 'password:') !== false || stripos($enBuf, 'pass:') !== false) {
                fwrite($fp, ($enablePassword ?: $password) . "\r\n");
                $this->telnetReadUntil($fp, ['#', '>'], 3);
            }
        }

        // 4. Disable pagination
        fwrite($fp, "terminal length 0\r\n");
        $this->telnetReadUntil($fp, ['#', '>'], 2);
        fwrite($fp, "terminal page-break disable\r\n");
        $this->telnetReadUntil($fp, ['#', '>'], 2);
        fwrite($fp, "undo smart\r\n");
        $this->telnetReadUntil($fp, ['#', '>'], 2);
    }

    private function telnetReadUntilLogin($fp, array $prompts, int $timeout = 6): string
    {
        $buffer = '';
        $start = time();
        while (!feof($fp) && (time() - $start) < $timeout) {
            $chunk = fread($fp, 1024);
            if ($chunk === false || $chunk === '') {
                usleep(50000);
                continue;
            }
            $buffer .= $chunk;
            $clean = preg_replace('/[^\x20-\x7E\r\n]/', '', $buffer);
            $lower = strtolower(rtrim($clean));
            foreach ($prompts as $p) {
                if (str_ends_with($lower, strtolower($p)) || preg_match('/' . preg_quote($p, '/') . '\s*$/i', $lower)) {
                    return $clean;
                }
            }
        }
        return preg_replace('/[^\x20-\x7E\r\n]/', '', $buffer);
    }

    private function telnetReadUntil($fp, array $prompts, int $timeout = 6): string
    {
        $buffer = '';
        $start = time();
        while (!feof($fp) && (time() - $start) < $timeout) {
            $chunk = fread($fp, 2048);
            if ($chunk === false || $chunk === '') {
                usleep(50000);
                continue;
            }
            $buffer .= $chunk;
            $clean = preg_replace('/[^\x20-\x7E\r\n]/', '', $buffer);
            $lower = strtolower(rtrim($clean));

            // Auto-respond to pagination prompts: send space to load next page
            if (
                str_ends_with($lower, '--more--') ||
                str_ends_with($lower, '--- more ---') ||
                str_ends_with($lower, '-- more --') ||
                preg_match('/(?:--more--|---more---|type <space>|press any key|q to quit)\s*$/i', $lower)
            ) {
                fwrite($fp, " \r\n");
                $start = time();
                continue;
            }

            foreach ($prompts as $p) {
                if (str_ends_with($lower, strtolower($p)) || preg_match('/' . preg_quote($p, '/') . '\s*$/i', $lower)) {
                    return $clean;
                }
            }
        }
        return preg_replace('/[^\x20-\x7E\r\n]/', '', $buffer);
    }

    private function parseHiosoTelnetOutput(string $text): array
    {
        $onus = [];
        $opticalMap = [];
        $lines = explode("\n", $text);

        // Pass 1: Parse optical power lines (e.g. from 'show optical-power-status' or 'show epon optical-power-status')
        foreach ($lines as $line) {
            $t = trim($line);
            if (preg_match('/(?:Port:?)?\s*0?\/(\d+):(\d+)\s+.*?([+-]?\d+(?:\.\d+)?)\s*(?:\(dbm\)|dBm)?/i', $t, $pm)) {
                $p = (int)$pm[1];
                $o = (int)$pm[2];
                $rawOpt = (float)$pm[3];
                if ($rawOpt <= -5.0 && $rawOpt >= -50.0) {
                    $opticalMap["{$p}.{$o}"] = round($rawOpt, 2);
                }
            }
        }

        // Pass 2: Parse ONU info lines
        foreach ($lines as $line) {
            $t = trim($line);
            if (preg_match('/^0\/(\d+):(\d+)\s+(\S+)\s+([0-9a-fA-F:\.-]+)\s+(\S+)/', $t, $m)) {
                $pon = "PON {$m[1]}";
                $index = "{$m[1]}.{$m[2]}";
                $name = $m[3] !== 'NA' ? $m[3] : "ONU-{$index}";
                $mac = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $m[4]));
                $rawStatus = trim($m[5]);
                $isOnline = (stripos($rawStatus, 'online') !== false || stripos($rawStatus, 'auth') !== false || stripos($rawStatus, 'up') !== false);
                $status = $isOnline ? 'online' : 'offline';
                $offlineReason = null;
                if (!$isOnline) {
                    $lowerRaw = strtolower($rawStatus);
                    if (str_contains($lowerRaw, 'pwr') || str_contains($lowerRaw, 'power') || str_contains($lowerRaw, 'gasp')) {
                        $offlineReason = 'power_down';
                    } else {
                        $offlineReason = 'down'; // Kabel Putus / LOS
                    }
                }

                $rxPower = $opticalMap[$index] ?? null;
                if ($rxPower === null && preg_match('/([+-]?\d+\.\d+)\s*(?:dBm|\(dbm\))/i', $t, $pm)) {
                    $pwr = (float) $pm[1];
                    if ($pwr <= -5.0 && $pwr >= -50.0) {
                        $rxPower = round($pwr, 2);
                    }
                }

                $onus[] = [
                    'index' => $index,
                    'name' => $name,
                    'serial_number' => $mac,
                    'pon_port' => $pon,
                    'status' => $status,
                    'offline_reason' => $offlineReason,
                    'rx_power' => $rxPower,
                    'distance' => null,
                ];
            }
        }
        return $onus;
    }

    private function parseCdataTelnetOutput(string $text): array
    {
        $onus = [];
        $opticalMap = [];
        $txMap = [];
        $lines = explode("\n", $text);

        // Pass 1: Extract optical power map
        foreach ($lines as $line) {
            $t = trim($line);
            if (empty($t)) continue;

            $p = null;
            $o = null;

            if (preg_match('/(?:EPON|GPON)?\s*0?\/(\d+)[:\s]+(\d+)/i', $t, $pm)) {
                $p = (int) $pm[1];
                $o = (int) $pm[2];
            } elseif (preg_match('/(?:Port:?)?\s*0?\/(\d+)[:\s]+(?:ONU:?)?\s*(\d+)/i', $t, $pm)) {
                $p = (int) $pm[1];
                $o = (int) $pm[2];
            }

            if ($p !== null && $o !== null) {
                $key = "{$p}.{$o}";
                // Look for Rx power (e.g. -22.5 dBm or -22.5)
                if (preg_match('/([+-]?\d+(?:\.\d+)?)\s*(?:dBm|\(dbm\))?/i', $t, $om)) {
                    $rawOpt = (float) $om[1];
                    if ($rawOpt <= -5.0 && $rawOpt >= -50.0) {
                        $opticalMap[$key] = round($rawOpt, 2);
                    }
                }
                // Look for multiple numbers (e.g. TX and RX)
                if (preg_match_all('/([+-]?\d+(?:\.\d+)?)/', $t, $allNums)) {
                    foreach ($allNums[1] as $numStr) {
                        $num = (float) $numStr;
                        if ($num <= -5.0 && $num >= -50.0 && !isset($opticalMap[$key])) {
                            $opticalMap[$key] = round($num, 2);
                        } elseif ($num >= -5.0 && $num <= 10.0 && $num != 0.0 && !isset($txMap[$key])) {
                            $txMap[$key] = round($num, 2);
                        }
                    }
                }
            }
        }

        // Pass 2: Extract ONU list
        foreach ($lines as $line) {
            $t = trim($line);
            if (empty($t) || str_starts_with($t, '---') || str_starts_with($t, '===') || stripos($t, 'Command') !== false || stripos($t, 'Hardware') !== false) continue;

            $pon = null;
            $index = null;
            $sn = null;
            $name = null;

            // Check for port/ONU-ID patterns
            if (preg_match('/(?:gpon-onu_|epon-onu_)?(?:1\/)?(\d+)\/(\d+)(?:\/(\d+))?:(\d+)/i', $t, $pm)) {
                if (!empty($pm[3])) {
                    $pon = "PON {$pm[1]}/{$pm[2]}/{$pm[3]}";
                    $index = "{$pm[1]}.{$pm[2]}.{$pm[3]}.{$pm[4]}";
                } else {
                    $pon = "PON {$pm[1]}/{$pm[2]}";
                    $index = "{$pm[1]}.{$pm[2]}.{$pm[4]}";
                }
            } elseif (preg_match('/(?:EPON|GPON)?\s*0?\/(\d+):(\d+)/i', $t, $pm)) {
                $typePrefix = stripos($t, 'gpon') !== false ? 'GPON0/' : 'EPON0/';
                $pon = "{$typePrefix}{$pm[1]}";
                $index = "{$pm[1]}.{$pm[2]}";
            } elseif (preg_match('/(?:EPON|GPON)?\s*0?\/(\d+)\s+(\d+)/i', $t, $pm)) {
                $typePrefix = stripos($t, 'gpon') !== false ? 'GPON0/' : 'EPON0/';
                $pon = "{$typePrefix}{$pm[1]}";
                $index = "{$pm[1]}.{$pm[2]}";
            } elseif (preg_match('/(?:EPON|GPON|PON)?\s*(\d+):(\d+)/i', $t, $pm)) {
                $pon = "PON {$pm[1]}";
                $index = "{$pm[1]}.{$pm[2]}";
            }

            // Extract MAC address or GPON Serial
            if (preg_match('/([0-9a-fA-F]{2}(?::[0-9a-fA-F]{2}){5}|[0-9a-fA-F]{4}\.[0-9a-fA-F]{4}\.[0-9a-fA-F]{4}|[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}|[0-9a-fA-F]{12}|[A-Z]{4}[0-9a-fA-F]{8})/i', $t, $sm)) {
                $sn = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $sm[1]));
            }

            $invalidKeywords = ['ENABLE', 'DISABLE', 'WORKING', 'SYNCMIB', 'LOGGING', 'ONLINE', 'OFFLINE', 'READY', 'NA', 'NULL', 'NONE', 'AUTHFAILED', 'LOS', 'DYINGGASP', 'UNAUTH', 'DEACT', 'ACTIVE', 'DOWN', 'UP', 'UNKNOWN'];
            if (empty($sn) || in_array($sn, $invalidKeywords, true)) {
                $sn = null;
            }

            if (!$index && !$sn) {
                continue;
            }

            if (!$index && $sn) {
                $index = (string)(count($onus) + 1);
                $pon = 'PON 1';
            }

            if (empty($sn)) {
                $sn = "ONU-" . ($pon ? preg_replace('/[^0-9]/', '', $pon) . '-' : '') . str_replace(['.', ':', '/'], '-', (string) $index);
            }

            $lowerLine = strtolower($t);
            $isOnline = false;
            if (
                str_contains($lowerLine, 'online') ||
                str_contains($lowerLine, 'working') ||
                str_contains($lowerLine, 'auth') ||
                str_contains($lowerLine, 'registered') ||
                str_contains($lowerLine, 'active') ||
                str_contains($lowerLine, 'up') ||
                str_contains($lowerLine, 'enable')
            ) {
                if (!str_contains($lowerLine, 'offline') && !str_contains($lowerLine, 'unauth') && !str_contains($lowerLine, 'down') && !str_contains($lowerLine, 'dereg') && !str_contains($lowerLine, 'deact')) {
                    $isOnline = true;
                }
            }

            $rxPower = $opticalMap[$index] ?? null;
            if ($rxPower === null && preg_match('/([+-]?\d+\.\d+)\s*(?:dBm|\(dbm\))/i', $t, $pm)) {
                $pwr = (float) $pm[1];
                if ($pwr <= -5.0 && $pwr >= -50.0) {
                    $rxPower = round($pwr, 2);
                }
            }

            if (!$isOnline && $rxPower !== null && $rxPower >= -38.0 && $rxPower <= -5.0) {
                $isOnline = true;
            }

            $txPower = $isOnline ? ($txMap[$index] ?? null) : null;

            $status = $isOnline ? 'online' : 'offline';
            $offlineReason = null;
            if (!$isOnline) {
                if (str_contains($lowerLine, 'pwr') || str_contains($lowerLine, 'power') || str_contains($lowerLine, 'gasp') || str_contains($lowerLine, 'dereg')) {
                    $offlineReason = 'power_down';
                } else {
                    $offlineReason = 'down';
                }
            }

            // Extract description / customer name if present in line
            $tokens = preg_split('/\s+/', $t);
            foreach ($tokens as $tok) {
                $tokTrim = trim($tok);
                if (strlen($tokTrim) >= 3 && !preg_match('/^[0-9:\.\-\/]+$/', $tokTrim) && !in_array(strtolower($tokTrim), ['epon', 'gpon', 'online', 'offline', 'working', 'auth', 'unauth', 'down', 'up', 'enable', 'active', 'dbm', 'mac', 'llid', 'port', 'onuid', 'status'])) {
                    if (strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $tokTrim)) !== $sn) {
                        $name = $tokTrim;
                        break;
                    }
                }
            }

            $onus[] = [
                'index' => $index,
                'name' => $name ?: "ONU-{$index}",
                'serial_number' => $sn ?: "ONU-{$index}",
                'pon_port' => $pon ?: 'EPON0/1',
                'status' => $status,
                'offline_reason' => $isOnline ? null : $offlineReason,
                'rx_power' => $isOnline ? $rxPower : null,
                'tx_power' => $txPower,
                'distance' => null,
            ];
        }

        return $onus;
    }

    private function parseVsolTelnetOutput(string $text): array
    {
        $onus = [];
        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            $t = trim($line);
            if (empty($t)) continue;

            $pon = null;
            $index = null;
            $sn = null;
            $rawStatus = null;
            $rxPower = null;

            // Check if line contains port notation e.g. 0/1:1, EPON0/1:1, GPON0/1:1, 1:1, 1/1:1
            if (preg_match('/(?:EPON|GPON)?\s*0?\/(\d+):(\d+)/i', $t, $pm)) {
                $pon = "PON {$pm[1]}";
                $index = "{$pm[1]}.{$pm[2]}";
            } elseif (preg_match('/(?:EPON|GPON)?\s*(\d+)\/(\d+)\/(\d+):(\d+)/i', $t, $pm)) {
                $pon = "PON {$pm[2]}";
                $index = "{$pm[2]}.{$pm[4]}";
            } elseif (preg_match('/(?:EPON|GPON|PON)?\s*(\d+):(\d+)/i', $t, $pm)) {
                $pon = "PON {$pm[1]}";
                $index = "{$pm[1]}.{$pm[2]}";
            }

            if (!$index) continue;

            // Extract MAC / SN
            if (preg_match('/([0-9a-fA-F]{2}(?::[0-9a-fA-F]{2}){5}|[0-9a-fA-F]{4}\.[0-9a-fA-F]{4}\.[0-9a-fA-F]{4}|[A-Z0-9]{12,16})/i', $t, $sm)) {
                $sn = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $sm[1]));
            }

            // Extract Optical Power dBm
            if (preg_match('/([+-]?\d+\.\d+)\s*(?:dBm|\(dbm\))?/i', $t, $om)) {
                $opt = (float) $om[1];
                if ($opt <= -5.0 && $opt >= -50.0) {
                    $rxPower = round($opt, 2);
                }
            }

            // Extract Status keywords
            $lowerLine = strtolower($t);
            $isOnline = false;
            if (
                str_contains($lowerLine, 'online') ||
                str_contains($lowerLine, 'active') ||
                str_contains($lowerLine, 'auth') ||
                str_contains($lowerLine, 'registered') ||
                str_contains($lowerLine, 'working') ||
                str_contains($lowerLine, 'up') ||
                str_contains($lowerLine, 'enable')
            ) {
                if (!str_contains($lowerLine, 'offline') && !str_contains($lowerLine, 'unauth') && !str_contains($lowerLine, 'down') && !str_contains($lowerLine, 'deact')) {
                    $isOnline = true;
                }
            }

            // Fallback: If optical power is valid (-38 to -6 dBm), ONU is online
            if (!$isOnline && $rxPower !== null && $rxPower >= -38.0 && $rxPower <= -5.0) {
                $isOnline = true;
            }

            $status = $isOnline ? 'online' : 'offline';
            $offlineReason = null;
            if (!$isOnline) {
                if (str_contains($lowerLine, 'pwr') || str_contains($lowerLine, 'power') || str_contains($lowerLine, 'gasp')) {
                    $offlineReason = 'power_down';
                } else {
                    $offlineReason = 'down';
                }
            }

            $onus[] = [
                'index' => $index,
                'name' => "VSOL-{$index}",
                'serial_number' => $sn ?: "VSOL-IDX-{$index}",
                'pon_port' => $pon ?: 'PON 1',
                'status' => $status,
                'offline_reason' => $isOnline ? null : $offlineReason,
                'rx_power' => $rxPower,
                'distance' => null,
            ];
        }
        return $onus;
    }

    private function parseZteTelnetOutput(string $text): array
    {
        $onusMap = [];
        $opticalRxMap = [];
        $opticalTxMap = [];
        $lines = explode("\n", $text);

        // Helper to normalize ZTE key from various formats:
        // Examples:
        // "gpon-onu_1/2/1:18" -> shelf=1, slot=2, port=1, onu=18 -> pon="GPON 1/2/1", raw="gpon-onu_1/2/1:18"
        // "1/2/1:18"          -> shelf=1, slot=2, port=1, onu=18 -> pon="GPON 1/2/1"
        // "1/2:3"             -> shelf=1, slot=1, port=2, onu=3  -> pon="GPON 1/1/2"
        $normalizeZte = function (string $raw): ?array {
            $raw = trim($raw);
            if (empty($raw)) return null;

            // Pattern 1: gpon-onu_1/2/1:18 or epon-onu_1/2/1:18 or 1/2/1:18
            if (preg_match('/^(?:(gpon|epon)-onu_)?(?:(\d+)\/)?(\d+)\/(\d+):(\d+)$/i', $raw, $m)) {
                $type = !empty($m[1]) ? strtoupper($m[1]) : 'GPON';
                $shelf = !empty($m[2]) ? (int) $m[2] : 1;
                $slot = (int) $m[3];
                $port = (int) $m[4];
                $onuId = (int) $m[5];

                return [
                    'key' => "{$shelf}/{$slot}/{$port}:{$onuId}",
                    'raw_key' => strtolower($type) . "-onu_{$shelf}/{$slot}/{$port}:{$onuId}",
                    'pon_port' => "{$type} {$shelf}/{$slot}/{$port}",
                    'index' => "{$shelf}.{$slot}.{$port}.{$onuId}",
                    'onu_id' => $onuId,
                    'type' => $type,
                    'shelf' => $shelf,
                    'slot' => $slot,
                    'port' => $port,
                ];
            }

            // Pattern 2: 1/2:18
            if (preg_match('/^(?:(gpon|epon)-onu_)?(\d+)\/(\d+):(\d+)$/i', $raw, $m)) {
                $type = !empty($m[1]) ? strtoupper($m[1]) : 'GPON';
                $slot = (int) $m[2];
                $port = (int) $m[3];
                $onuId = (int) $m[4];

                return [
                    'key' => "1/{$slot}/{$port}:{$onuId}",
                    'raw_key' => strtolower($type) . "-onu_1/{$slot}/{$port}:{$onuId}",
                    'pon_port' => "{$type} 1/{$slot}/{$port}",
                    'index' => "1.{$slot}.{$port}.{$onuId}",
                    'onu_id' => $onuId,
                    'type' => $type,
                    'shelf' => 1,
                    'slot' => $slot,
                    'port' => $port,
                ];
            }

            return null;
        };

        // Pass 1: Parse optical power attenuation & diagnosis
        foreach ($lines as $line) {
            $t = trim($line);
            if (empty($t)) continue;

            if (preg_match('/((?:gpon-onu_|epon-onu_|onu_)?(?:\d+\/)?\d+\/\d+(?:\/\d+)?:?\d+)/i', $t, $km)) {
                $norm = $normalizeZte($km[1]);
                if ($norm) {
                    $k = $norm['key'];
                    $rawK = $norm['raw_key'];
                    if (preg_match_all('/([+-]?\d+(?:\.\d+)?)\s*(?:\(dbm\)|dBm)?/i', substr($t, strlen($km[1])), $allNums)) {
                        $floats = array_map('floatval', $allNums[1]);
                        $validFloats = array_values(array_filter($floats, function ($v) {
                            return $v != 0.0 && $v != 65535.0 && $v != -65535.0 && $v != -1000.0;
                        }));
                        if (count($validFloats) >= 2) {
                            $rxVal = $validFloats[1];
                            $txVal = $validFloats[0];
                            if ($rxVal <= -5.0 && $rxVal >= -50.0) {
                                $opticalRxMap[$k] = round($rxVal, 2);
                                $opticalRxMap[$rawK] = round($rxVal, 2);
                            }
                            if ($txVal >= -10.0 && $txVal <= 15.0) {
                                $opticalTxMap[$k] = round($txVal, 2);
                                $opticalTxMap[$rawK] = round($txVal, 2);
                            }
                        } elseif (count($validFloats) === 1) {
                            $val = $validFloats[0];
                            if ($val <= -5.0 && $val >= -50.0) {
                                $opticalRxMap[$k] = round($val, 2);
                                $opticalRxMap[$rawK] = round($val, 2);
                            } elseif ($val >= -10.0 && $val <= 15.0) {
                                $opticalTxMap[$k] = round($val, 2);
                                $opticalTxMap[$rawK] = round($val, 2);
                            }
                        }
                    }
                }
            }
        }

        // Pass 2: Parse "show gpon onu state"
        // Format:
        // OnuIndex               Admin  OMCC   Phase    Channel
        // gpon-onu_1/2/1:1       enable enable working  0
        // gpon-onu_1/2/1:2       enable disable offline 0
        // gpon-onu_1/2/1:3       enable disable LOS     0
        // gpon-onu_1/2/1:4       enable disable DyingGasp 0
        foreach ($lines as $line) {
            $t = trim($line);
            if (empty($t)) continue;

            if (preg_match('/^((?:gpon-onu_|epon-onu_|onu_)?(?:\d+\/)?\d+\/\d+(?:\/\d+)?:?\d+)\s+(\S+)\s+(\S+)\s+(\S+)/i', $t, $m)) {
                $norm = $normalizeZte($m[1]);
                if ($norm) {
                    $k = $norm['key'];
                    $admin = strtolower($m[2]);
                    $omcc = strtolower($m[3]);
                    $phase = strtolower($m[4]);

                    $isOnline = in_array($phase, ['working', 'online', 'syncmib', 'logging', 'ready', 'active'], true) ||
                                (in_array($admin, ['enable', 'unlock', 'up'], true) && in_array($omcc, ['enable', 'up'], true) && !in_array($phase, ['offline', 'los', 'dyinggasp', 'authfailed', 'down', 'deact'], true));

                    $offlineReason = null;
                    if (!$isOnline) {
                        if (str_contains($phase, 'los') || str_contains($phase, 'loss')) {
                            $offlineReason = 'los';
                        } elseif (str_contains($phase, 'gasp') || str_contains($phase, 'power') || str_contains($phase, 'dying')) {
                            $offlineReason = 'power_down';
                        } else {
                            $offlineReason = 'down';
                        }
                    }

                    if (!isset($onusMap[$k])) {
                        $onusMap[$k] = [
                            'norm' => $norm,
                            'status' => $isOnline ? 'online' : 'offline',
                            'offline_reason' => $offlineReason,
                            'serial_number' => null,
                            'name' => null,
                            'distance' => null,
                        ];
                    } else {
                        $onusMap[$k]['status'] = $isOnline ? 'online' : 'offline';
                        $onusMap[$k]['offline_reason'] = $offlineReason;
                    }
                }
            }
        }

        // Pass 3: Parse "show gpon onu baseinfo", "show gpon onu detail-info", "show gpon onu uncfg"
        $currentDetailKey = null;
        foreach ($lines as $line) {
            $t = trim($line);
            if (empty($t)) continue;

            // Check multi-line detail block
            if (preg_match('/(?:ONU\s*index|ONU\s*Index)\s*:\s*((?:gpon-onu_|epon-onu_|onu_)?(?:\d+\/)?\d+\/\d+(?:\/\d+)?:?\d+)/i', $t, $dm)) {
                $norm = $normalizeZte($dm[1]);
                $currentDetailKey = $norm ? $norm['key'] : null;
                if ($norm && !isset($onusMap[$norm['key']])) {
                    $onusMap[$norm['key']] = [
                        'norm' => $norm,
                        'status' => 'online',
                        'offline_reason' => null,
                        'serial_number' => null,
                        'name' => null,
                        'distance' => null,
                    ];
                }
            }

            if ($currentDetailKey && isset($onusMap[$currentDetailKey])) {
                if (preg_match('/(?:SN|Serial\s*Number|Serial-Number)\s*:\s*([A-Za-z0-9]+)/i', $t, $sm)) {
                    $snCandidate = strtoupper(trim($sm[1]));
                    if (!in_array($snCandidate, ['ENABLE', 'DISABLE', 'WORKING', 'ONLINE', 'OFFLINE', 'NONE', 'NULL', 'NA', '1(GPON)', '2(GPON)', '1(EPON)'], true)) {
                        $onusMap[$currentDetailKey]['serial_number'] = $snCandidate;
                    }
                }
                if (preg_match('/(?:Name|ONU\s*Name|Description)\s*:\s*(\S+)/i', $t, $nm)) {
                    $nameCandidate = trim($nm[1]);
                    if (!in_array(strtolower($nameCandidate), ['enable', 'disable', 'working', 'online', 'offline', 'none', 'null', 'na', '1(gpon)', '2(gpon)', '1(epon)', 'gpon', 'epon'], true) && !preg_match('/^\d+\([a-z0-9_-]+\)$/i', $nameCandidate)) {
                        $onusMap[$currentDetailKey]['name'] = $nameCandidate;
                    }
                }
                if (preg_match('/(?:State|Phase\s*State)\s*:\s*(\S+)/i', $t, $stm)) {
                    $st = strtolower(trim($stm[1]));
                    if (in_array($st, ['working', 'online', 'syncmib', 'logging', 'ready'], true)) {
                        $onusMap[$currentDetailKey]['status'] = 'online';
                        $onusMap[$currentDetailKey]['offline_reason'] = null;
                    }
                }
            }

            // Check single line baseinfo table
            if (preg_match('/^((?:gpon-onu_|epon-onu_|onu_)?(?:\d+\/)?\d+\/\d+(?:\/\d+)?:?\d+)\s+(.+)$/i', $t, $bm)) {
                $norm = $normalizeZte($bm[1]);
                if ($norm) {
                    $k = $norm['key'];
                    $rest = $bm[2];

                    if (!isset($onusMap[$k])) {
                        $onusMap[$k] = [
                            'norm' => $norm,
                            'status' => 'offline',
                            'offline_reason' => 'down',
                            'serial_number' => null,
                            'name' => null,
                            'distance' => null,
                        ];
                    }

                    // Look for GPON Serial Number pattern (ZTEG..., ZTEGC..., 12-16 hex chars)
                    if (preg_match('/(ZTEG[0-9A-Fa-f]{8}|ZTEGC[0-9A-Fa-f]{7}|[A-Z]{4}[0-9A-Fa-f]{8}|[0-9A-Fa-f]{12,16})/i', $rest, $snm)) {
                        $snFound = strtoupper($snm[1]);
                        if (!in_array($snFound, ['ENABLE', 'DISABLE', 'WORKING', 'ONLINE', 'OFFLINE', 'SYNCMIB', 'LOGGING'], true)) {
                            $onusMap[$k]['serial_number'] = $snFound;
                        }
                    }

                    // Look for client name in tokens (excluding generic tokens and port types)
                    $tokens = preg_split('/\s+/', $rest);
                    foreach ($tokens as $tok) {
                        $tokTrim = trim($tok);
                        if (strlen($tokTrim) >= 2 &&
                            !preg_match('/^[0-9:\.\-\/]+$/', $tokTrim) &&
                            !preg_match('/^\d+\([a-z0-9_-]+\)$/i', $tokTrim) &&
                            !in_array(strtolower($tokTrim), ['gpon', 'epon', 'zte', 'f660', 'f609', 'f601', 'f670', 'f680', 'enable', 'disable', 'working', 'online', 'offline', 'sn', 'pwd', 'sn+pwd', 'loid', 'loid+pwd', 'none', 'null', 'na', '1(gpon)', '2(gpon)', '1(epon)', 'syncmib', 'logging', 'los', 'dyinggasp', 'authfailed', 'channel'])
                        ) {
                            if (empty($onusMap[$k]['name'])) {
                                $onusMap[$k]['name'] = $tokTrim;
                            }
                        }
                    }
                }
            }
        }

        // Pass 4: Parse EPON ONUs ("show epon onu-information", "show epon onu status")
        foreach ($lines as $line) {
            $t = trim($line);
            if (empty($t)) continue;

            if (preg_match('/((?:epon-onu_|gpon-onu_|onu_)?(?:\d+\/)?\d+\/\d+(?:\/\d+)?:?\d+)\s+([0-9a-fA-F]{4}\.[0-9a-fA-F]{4}\.[0-9a-fA-F]{4}|[0-9a-fA-F:]{17}|[0-9a-fA-F]{12})\s+(\S+)(?:\s+(\d+))?/i', $t, $em)) {
                $norm = $normalizeZte($em[1]);
                if ($norm) {
                    $k = $norm['key'];
                    $mac = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $em[2]));
                    $st = strtolower($em[3]);
                    $dist = isset($em[4]) && is_numeric($em[4]) ? (float) $em[4] : null;

                    $isOnline = in_array($st, ['authenticated', 'up', 'online', 'working', 'registered'], true);

                    if (!isset($onusMap[$k])) {
                        $onusMap[$k] = [
                            'norm' => $norm,
                            'status' => $isOnline ? 'online' : 'offline',
                            'offline_reason' => $isOnline ? null : 'down',
                            'serial_number' => $mac,
                            'name' => null,
                            'distance' => $dist,
                        ];
                    } else {
                        if (!empty($mac)) $onusMap[$k]['serial_number'] = $mac;
                        if ($dist !== null) $onusMap[$k]['distance'] = $dist;
                        $onusMap[$k]['status'] = $isOnline ? 'online' : 'offline';
                    }
                }
            }
        }

        // Pass 5: Assemble and finalize all ONUs
        $discovered = [];
        foreach ($onusMap as $k => $data) {
            $norm = $data['norm'];
            $index = $norm['index'];
            $ponPort = $norm['pon_port'];
            $onuId = $norm['onu_id'];
            $rawKey = $norm['raw_key'];

            $rxPower = $opticalRxMap[$k] ?? ($opticalRxMap[$rawKey] ?? null);
            $txPower = $opticalTxMap[$k] ?? ($opticalTxMap[$rawKey] ?? null);

            $status = $data['status'];
            $offlineReason = $data['offline_reason'];

            // If valid optical light detected (-38 to -5 dBm), ONU is definitely online
            if ($rxPower !== null && $rxPower >= -38.0 && $rxPower <= -5.0) {
                $status = 'online';
                $offlineReason = null;
            }

            // Determine unique Serial Number
            $sn = $data['serial_number'];
            if (empty($sn) || in_array($sn, ['ENABLE', 'DISABLE', 'WORKING', 'ONLINE', 'OFFLINE', 'SYNCMIB', 'LOGGING', 'NULL', 'NONE', 'NA', '1(GPON)', '2(GPON)', '1(EPON)'], true)) {
                $cleanPon = preg_replace('/[^0-9]/', '', $ponPort);
                $sn = 'ZTEG' . str_pad(dechex(crc32("{$cleanPon}:{$onuId}")), 8, '0', STR_PAD_LEFT);
                $sn = strtoupper($sn);
            }

            $rawName = trim((string)($data['name'] ?? ''));
            $isGenericName = empty($rawName) || in_array(strtoupper($rawName), [
                'NA', 'N/A', 'NULL', 'NONE', '1(GPON)', '2(GPON)', '1(EPON)', '2(EPON)', 'GPON', 'EPON', 'ONU', '1', '0'
            ], true) || preg_match('/^\d+\([a-z0-9_-]+\)$/i', $rawName);

            $name = $isGenericName ? $rawKey : $rawName;

            $discovered[] = [
                'index' => $index,
                'onu_id' => $onuId,
                'name' => $name,
                'serial_number' => $sn,
                'pon_port' => $ponPort,
                'status' => $status,
                'offline_reason' => $status === 'online' ? null : ($offlineReason ?: 'down'),
                'rx_power' => $status === 'online' ? $rxPower : null,
                'tx_power' => $status === 'online' ? $txPower : null,
                'distance' => $data['distance'],
            ];
        }

        return $discovered;
    }

    private function parseHuaweiTelnetOutput(string $text): array
    {
        $onus = [];
        $opticalMap = [];
        $lines = explode("\n", $text);

        // Pass 1: Parse optical info
        foreach ($lines as $line) {
            $t = trim($line);
            if (preg_match('/(?:0\/\s*(\d+)\/\s*(\d+)\s+(\d+)|(?:PON\s*)?(\d+):(\d+))\s+.*?([+-]?\d+(?:\.\d+)?)\s*(?:\(dbm\)|dBm)?/i', $t, $pm)) {
                $p = (int)($pm[2] ?: $pm[4]);
                $o = (int)($pm[3] ?: $pm[5]);
                $rawOpt = (float)$pm[6];
                if ($rawOpt <= -5.0 && $rawOpt >= -50.0) {
                    $opticalMap["{$p}.{$o}"] = round($rawOpt, 2);
                }
            }
        }

        // Pass 2: Parse summary
        foreach ($lines as $line) {
            $t = trim($line);
            if (preg_match('/^0\/\s*(\d+)\/\s*(\d+)\s+(\d+)\s+([0-9A-Fa-f]+)\s+(\S+)\s+(\S+)/', $t, $m)) {
                $pon = "PON {$m[2]}";
                $index = "{$m[2]}.{$m[3]}";
                $sn = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $m[4]));
                $status = (stripos($m[6], 'online') !== false || stripos($m[6], 'active') !== false) ? 'online' : 'offline';
                $rxPower = $opticalMap[$index] ?? null;

                $onus[] = [
                    'index' => $index,
                    'name' => "HW-{$index}",
                    'serial_number' => $sn,
                    'pon_port' => $pon,
                    'status' => $status,
                    'rx_power' => $rxPower,
                    'distance' => null,
                ];
            }
        }
        return $onus;
    }

    private function parseGenericTelnetOutput(string $text): array
    {
        $onus = [];
        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            $t = trim($line);
            if (preg_match('/([0-9a-fA-F]{4}[0-9a-fA-F]{8}|[0-9a-fA-F:]{17})/', $t, $m)) {
                $rxPower = null;
                if (preg_match('/([+-]?\d+\.\d+)\s*(?:dBm|\(dbm\))/i', $t, $pm)) {
                    $pwr = (float) $pm[1];
                    if ($pwr <= -5.0 && $pwr >= -50.0) {
                        $rxPower = round($pwr, 2);
                    }
                }

                $onus[] = [
                    'index' => (string) (count($onus) + 1),
                    'name' => "ONU-" . (count($onus) + 1),
                    'serial_number' => strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $m[1])),
                    'pon_port' => "PON 1",
                    'status' => 'online',
                    'rx_power' => $rxPower,
                    'distance' => null,
                ];
            }
        }
        return $onus;
    }

    private function persistDiscoveredOnus(Olt $olt, array $discoveredOnus, bool $pollSuccess, string $profileName, string $protocol): array
    {
        if ($pollSuccess && !empty($discoveredOnus)) {
            $tenantId = $olt->tenant_id;
            $allCustomers = \App\Models\Customer::where('tenant_id', $tenantId)->get(['id', 'name', 'pppoe_username', 'phone']);
            $customerMapByName = [];
            $customerMapByUser = [];
            foreach ($allCustomers as $c) {
                if (!empty($c->name)) {
                    $customerMapByName[strtolower(trim($c->name))] = $c->id;
                }
                if (!empty($c->pppoe_username)) {
                    $customerMapByUser[strtolower(trim($c->pppoe_username))] = $c->id;
                }
            }

            $normalizedLiveSerials = [];

            $invalidKeywords = ['ENABLE', 'DISABLE', 'WORKING', 'SYNCMIB', 'LOGGING', 'ONLINE', 'OFFLINE', 'READY', 'NA', 'NULL', 'NONE', 'AUTHFAILED', 'LOS', 'DYINGGASP', 'UNAUTH', 'DEACT', 'ACTIVE', 'DOWN', 'UP', 'UNKNOWN'];
            foreach ($discoveredOnus as $d) {
                $cleanSerial = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) ($d['serial_number'] ?? '')));
                if (empty($cleanSerial) || in_array($cleanSerial, $invalidKeywords, true) || strlen($cleanSerial) < 3) {
                    $cleanSerial = 'ONU-' . ($d['pon_port'] ? preg_replace('/[^0-9]/', '', $d['pon_port']) . '-' : '') . str_replace(['.', ':', '/'], '-', (string) ($d['index'] ?? uniqid()));
                }

                $unpaddedSerial = ltrim($cleanSerial, '0');
                $paddedSerial = (strlen($cleanSerial) < 12 && ctype_xdigit($cleanSerial)) ? str_pad($cleanSerial, 12, '0', STR_PAD_LEFT) : $cleanSerial;
                $candidateSerials = array_values(array_unique(array_filter([$cleanSerial, $unpaddedSerial, $paddedSerial])));

                foreach ($candidateSerials as $cs) {
                    $normalizedLiveSerials[] = $cs;
                }

                // Match ONU by candidate serials OR by PON port + index fallback
                $onu = Onu::where('olt_id', $olt->id)
                    ->where(function ($q) use ($candidateSerials, $d) {
                        $q->whereIn('serial_number', $candidateSerials)
                          ->orWhereRaw("REPLACE(REPLACE(REPLACE(UPPER(serial_number), ':', ''), '-', ''), '.', '') IN ('" . implode("','", $candidateSerials) . "')");
                        if (!empty($d['index'])) {
                            $parts = explode('.', (string) $d['index']);
                            $idx = (int) end($parts);
                            $pon = $d['pon_port'] ?? null;
                            if ($idx > 0 && $pon) {
                                $q->orWhere(function ($sub) use ($idx, $pon) {
                                    $sub->where('onu_index', $idx)->where('pon_port', $pon);
                                });
                            }
                        }
                    })
                    ->first() ?? new Onu(['olt_id' => $olt->id, 'tenant_id' => $tenantId]);

                $onu->tenant_id = $tenantId;
                $onu->serial_number = $cleanSerial;

                $cleanDName = trim((string)($d['name'] ?? ''));
                $isGenericDName = empty($cleanDName) || in_array(strtoupper($cleanDName), [
                    'NA', 'N/A', 'NULL', 'NONE', '1(GPON)', '2(GPON)', '1(EPON)', '2(EPON)', 'GPON', 'EPON', 'ONU', '1', '0'
                ], true) || preg_match('/^\d+\([a-z0-9_-]+\)$/i', $cleanDName);

                if ($isGenericDName) {
                    if (($olt->model === 'zte' || (is_numeric(explode('.', (string)($d['index'] ?? ''))[0]) && (int)explode('.', (string)($d['index'] ?? ''))[0] >= 268435456)) && ($zte = $this->decodeZteIndex((string)($d['index'] ?? '')))) {
                        $onuName = $zte['onu_name'] ?? $cleanSerial;
                    } else {
                        $onuName = (!empty($cleanSerial) && !str_starts_with($cleanSerial, 'ONUIDX-') && !str_starts_with($cleanSerial, 'ONU-')) ? $cleanSerial : "ONU-" . ($d['index'] ?? $onu->id);
                    }
                } else {
                    $onuName = $cleanDName;
                }
                $onu->name = $onuName;

                $onu->pon_port = $d['pon_port'] ?? $onu->pon_port ?? 'PON 1';
                if (!empty($d['onu_id'])) {
                    $onu->onu_index = (int) $d['onu_id'];
                } elseif (($olt->model === 'zte' || (is_numeric(explode('.', (string)($d['index'] ?? ''))[0]) && (int)explode('.', (string)($d['index'] ?? ''))[0] >= 268435456)) && ($zte = $this->decodeZteIndex((string)($d['index'] ?? '')))) {
                    $onu->onu_index = $zte['onu_id'];
                } elseif (!empty($d['index'])) {
                    $parts = explode('.', (string) $d['index']);
                    $onu->onu_index = (int) end($parts);
                }
                $isOnline = ($d['status'] ?? '') === 'online';
                $onu->status = $isOnline ? 'online' : 'offline';
                $onu->offline_reason = $d['offline_reason'] ?? ($isOnline ? null : ($onu->offline_reason ?? 'power_down'));

                // Explicitly update telemetry (null if unmetered or offline)
                $onu->rx_power = (isset($d['rx_power']) && $d['rx_power'] !== null) ? (float) $d['rx_power'] : null;
                $onu->tx_power = (isset($d['tx_power']) && $d['tx_power'] !== null) ? (float) $d['tx_power'] : null;
                $onu->temperature = (isset($d['temperature']) && $d['temperature'] !== null) ? (float) $d['temperature'] : null;
                $onu->distance = (isset($d['distance']) && $d['distance'] !== null) ? (float) $d['distance'] : null;

                if ($isOnline) {
                    $onu->last_online_at = now();
                }
                $onu->last_sync_at = now();

                // Auto-match ONU to customer by PPPoE username or customer name
                if (!$onu->customer_id && !empty($onu->name)) {
                    $lookup = strtolower(trim($onu->name));
                    if (isset($customerMapByUser[$lookup])) {
                        $onu->customer_id = $customerMapByUser[$lookup];
                    } elseif (isset($customerMapByName[$lookup])) {
                        $onu->customer_id = $customerMapByName[$lookup];
                    }
                }

                $onu->save();
            }

            // Prune stale/orphan ONUs in DB that no longer exist on the physical OLT
            if (!empty($normalizedLiveSerials)) {
                $uniqueNormalized = array_values(array_unique($normalizedLiveSerials));
                $placeholders = implode(',', array_fill(0, count($uniqueNormalized), '?'));
                Onu::where('olt_id', $olt->id)
                    ->whereNotIn('serial_number', $uniqueNormalized)
                    ->whereRaw("REPLACE(REPLACE(REPLACE(UPPER(serial_number), ':', ''), '-', ''), '.', '') NOT IN ({$placeholders})", $uniqueNormalized)
                    ->delete();
            }

            // Recalculate fresh PON port distribution from freshly saved & pruned database records
            $freshPonSummary = [];
            $freshOnus = $olt->onus()->get(['pon_port', 'status']);
            foreach ($freshOnus as $fo) {
                $p = $fo->pon_port ?: 'PON 1';
                if (!isset($freshPonSummary[$p])) {
                    $freshPonSummary[$p] = ['total' => 0, 'online' => 0, 'offline' => 0];
                }
                $freshPonSummary[$p]['total']++;
                if ($fo->status === 'online') {
                    $freshPonSummary[$p]['online']++;
                } else {
                    $freshPonSummary[$p]['offline']++;
                }
            }

            $currentMetrics = $olt->hardware_metrics ?: [];
            $currentMetrics['pon_ports'] = $freshPonSummary;
            $olt->hardware_metrics = $currentMetrics;

            $olt->last_poll_at = now();
            $olt->last_poll_status = 'online';
            $olt->save();
            Cache::put("olt_monitor_status_{$olt->id}", 'online', now()->addDays(7));

            $onlineCount = count(array_filter($discoveredOnus, fn($o) => ($o['status'] ?? '') === 'online'));

            return [
                'success' => true,
                'count' => count($discoveredOnus),
                'online_count' => $onlineCount,
                'profile' => $profileName,
                'protocol' => $protocol,
                'onus' => $discoveredOnus,
                'resources' => $olt->hardware_metrics,
            ];
        }

        $isDemo = ($olt->tenant?->slug === 'demo') || (session('tenant_slug') === 'demo');

        if ($isDemo) {
            // ── Fallback Telemetri & Simulasi HANYA untuk Demo Tenant ──
            $existingOnus = $olt->onus()->get();
            if ($existingOnus->count() >= 80) {
                foreach ($existingOnus as $onu) {
                    $onu->last_sync_at = now();
                    if ($onu->status === 'online') {
                        $onu->last_online_at = now();
                    }
                    $onu->save();
                }
                $onuCount = $existingOnus->count();
            } else {
                $onuCount = $this->generateFullOnuDataset($olt);
            }

            $olt->last_poll_at = now();
            $olt->last_poll_status = 'online';
            $olt->hardware_metrics = $this->fetchSystemResources($olt);
            $olt->save();

            return [
                'success' => true,
                'count' => $onuCount,
                'profile' => 'HIOSO_GPON (Simulasi Demo)',
                'protocol' => $protocol,
                'message' => "Koneksi SNMP & Telemetri OLT {$olt->name} Demo Normal ({$onuCount} ONU Terhubung)",
                'resources' => $olt->hardware_metrics,
            ];
        }

        // Real OLT Offline / Unreachable
        $olt->last_poll_at = now();
        $olt->last_poll_status = 'offline';
        $olt->save();

        return [
            'success' => false,
            'count' => $olt->onus()->count(),
            'profile' => null,
            'protocol' => $protocol,
            'message' => "Impossible de se connecter à l'OLT {$olt->name} ({$olt->host}) via SNMP/Telnet. Assurez-vous que l'OLT est actif et la communauté SNMP correcte.",
            'resources' => null,
        ];
    }

    public function generateFullOnuDataset(Olt $olt): int
    {
        $tenantId = $olt->tenant_id;
        $olt->onus()->delete();

        $jsonFile = resource_path('data/real_onus.json');
        if (!file_exists($jsonFile)) {
            $jsonFile = storage_path('app/real_onus.json');
        }
        $realOnus = [];
        if (file_exists($jsonFile)) {
            $realOnus = json_decode(file_get_contents($jsonFile), true) ?: [];
        }

        $allCustomers = \App\Models\Customer::where('tenant_id', $tenantId)->get(['id', 'name', 'pppoe_username']);
        $customerMapByName = [];
        foreach ($allCustomers as $c) {
            if (!empty($c->name)) {
                $customerMapByName[strtolower(trim($c->name))] = $c->id;
            }
        }

        $totalCreated = 0;
        foreach ($realOnus as $d) {
            $onu = new Onu();
            $onu->olt_id = $olt->id;
            $onu->tenant_id = $tenantId;
            $onu->name = $d['name'] ?? "ONU-{$d['index']}";
            $onu->serial_number = $d['serial_number'] ?? ('sn_' . md5((string)$totalCreated));
            $onu->pon_port = $d['pon_port'] ?? 'PON 1';
            $onu->status = $d['status'] ?? 'online';
            $onu->rx_power = isset($d['rx_power']) && $d['rx_power'] !== null ? (float) $d['rx_power'] : null;
            $onu->distance = isset($d['distance']) && $d['distance'] !== null ? (float) $d['distance'] : null;
            $onu->last_sync_at = now();
            $onu->last_online_at = ($d['status'] ?? 'online') === 'online' ? now() : now()->subHours(rand(2, 48));

            $lookup = strtolower(trim((string)$onu->name));
            if (isset($customerMapByName[$lookup])) {
                $onu->customer_id = $customerMapByName[$lookup];
            }

            $onu->save();
            $totalCreated++;
        }

        return $totalCreated;
    }

    private function buildIndexMap(array $items, string $baseOid = ''): array
    {
        $map = [];
        if (empty($items)) return $map;

        $base = trim($baseOid, '.');
        foreach ($items as $item) {
            if (!$item) continue;
            $oidStr = trim((string) $item->getOid(), '.');
            $rawVal = $item->getValue();
            $val = (is_object($rawVal) && method_exists($rawVal, 'getValue')) ? $rawVal->getValue() : $rawVal;

            if (!empty($base) && str_starts_with($oidStr, $base . '.')) {
                $idx = substr($oidStr, strlen($base) + 1);
                $map[$idx] = $val;
            } else {
                if (preg_match('/\.(\d+(?:\.\d+)*)$/', $oidStr, $m)) {
                    $map[$m[1]] = $val;
                }
            }
            $map[$oidStr] = $val;
        }
        return $map;
    }

    private function findValueInMap(array $map, string $index, $serial = null)
    {
        if (empty($map)) return null;

        // 1. Direct exact key match
        if (array_key_exists($index, $map)) {
            return $map[$index];
        }

        // 2. Trailing sub-interface / channel match (.1, .0, .2, .1.1)
        if (array_key_exists($index . '.1', $map)) return $map[$index . '.1'];
        if (array_key_exists($index . '.0', $map)) return $map[$index . '.0'];
        if (array_key_exists($index . '.2', $map)) return $map[$index . '.2'];
        if (array_key_exists($index . '.1.1', $map)) return $map[$index . '.1.1'];

        // 2b. ZTE decoded match (e.g. ifIndex 268501248.1 maps to rack.shelf.slot.port.onu or shelf.slot.port.onu)
        if (is_numeric(explode('.', $index)[0]) && (int)explode('.', $index)[0] >= 268435456) {
            $zte = $this->decodeZteIndex($index);
            if ($zte) {
                $k1 = "{$zte['shelf']}.{$zte['slot']}.{$zte['port']}.{$zte['onu_id']}";
                $k2 = "1.{$zte['shelf']}.{$zte['slot']}.{$zte['port']}.{$zte['onu_id']}";
                $k3 = "{$zte['slot']}.{$zte['port']}.{$zte['onu_id']}";
                if (array_key_exists($k1, $map)) return $map[$k1];
                if (array_key_exists($k2, $map)) return $map[$k2];
                if (array_key_exists($k3, $map)) return $map[$k3];
                if (array_key_exists($k1 . '.1', $map)) return $map[$k1 . '.1'];
                if (array_key_exists($k2 . '.1', $map)) return $map[$k2 . '.1'];
            }
        }

        // 3. Serial / MAC to dotted-decimal matching (for BDCOM & similar vendor name tables indexed by MAC)
        if (!empty($serial)) {
            $hexMac = '';
            if (is_string($serial)) {
                if (strlen($serial) === 6) {
                    $hexMac = bin2hex($serial);
                } else {
                    $cleanHex = preg_replace('/[^0-9A-Fa-f]/', '', $serial);
                    if (strlen($cleanHex) >= 10 && strlen($cleanHex) <= 12) {
                        $hexMac = str_pad($cleanHex, 12, '0', STR_PAD_LEFT);
                    }
                }
            }
            if (!empty($hexMac)) {
                $bytes = array_map('hexdec', str_split($hexMac, 2));
                $dottedDec = implode('.', $bytes); // e.g. "0.26.105.67.38.89"
                foreach ($map as $k => $v) {
                    $kStr = (string) $k;
                    if (str_ends_with($kStr, '.' . $dottedDec) || str_ends_with($kStr, $dottedDec) || str_contains($kStr, '.' . $dottedDec . '.')) {
                        return $v;
                    }
                }
            }
        }

        // 4. If $index has trailing suffix e.g. 1.1.1.1, try stripped prefix
        if (preg_match('/^(.*)\.\d+$/', $index, $pm)) {
            if (array_key_exists($pm[1], $map)) return $map[$pm[1]];
            if (array_key_exists($pm[1] . '.1', $map)) return $map[$pm[1] . '.1'];
        }

        // 5. Prefix & Suffix string search
        foreach ($map as $k => $v) {
            $kStr = (string) $k;
            if (str_starts_with($kStr, $index . '.') || str_ends_with($kStr, '.' . $index)) {
                return $v;
            }
        }

        // 6. Match by port & ONU number components
        $parts = explode('.', $index);
        if (count($parts) >= 2) {
            $shortKey = $parts[count($parts) - 2] . '.' . end($parts);
            foreach ($map as $k => $v) {
                $kStr = (string) $k;
                if (str_ends_with($kStr, '.' . $shortKey) || str_contains($kStr, '.' . $shortKey . '.') || $kStr === $shortKey) {
                    return $v;
                }
            }
        }

        // 7. Match by last integer (ONU ID)
        $lastNum = end($parts);
        if (is_numeric($lastNum)) {
            foreach ($map as $k => $v) {
                if (str_ends_with((string)$k, '.' . $lastNum)) {
                    return $v;
                }
            }
        }

        return null;
    }

    private function extractIndex(string $fullOid, string $baseOid): string
    {
        $full = trim($fullOid, '.');
        $base = trim($baseOid, '.');
        if (str_starts_with($full, $base . '.')) {
            return substr($full, strlen($base) + 1);
        }
        return basename($fullOid);
    }

    private function formatSerialNumber($rawSn, string $fallbackIndex): string
    {
        if (empty($rawSn)) {
            return "ONU-IDX-{$fallbackIndex}";
        }

        if (is_object($rawSn) && method_exists($rawSn, 'getValue')) {
            $rawSn = $rawSn->getValue();
        }

        if (is_string($rawSn)) {
            if ($rawSn === '' || $rawSn === 'NA' || $rawSn === 'N/A' || $rawSn === 'null') {
                return "ONU-IDX-{$fallbackIndex}";
            }

            // 1. 8-byte raw binary GPON Serial Number: 4 bytes ASCII vendor + 4 bytes hex payload
            if (strlen($rawSn) === 8) {
                $vendor = substr($rawSn, 0, 4);
                $hexPart = bin2hex(substr($rawSn, 4));
                if (ctype_print($vendor) && preg_match('/^[A-Za-z0-9]{4}$/', $vendor)) {
                    return strtoupper($vendor . $hexPart);
                }
                return strtoupper(bin2hex($rawSn));
            }

            // 2. 6-byte raw binary EPON MAC Address (e.g. 00:1A:69:43:26:59)
            if (strlen($rawSn) === 6) {
                return strtoupper(implode(':', str_split(bin2hex($rawSn), 2)));
            }

            $trimmed = trim($rawSn);
            if (strlen($trimmed) === 6) {
                return strtoupper(implode(':', str_split(bin2hex($trimmed), 2)));
            }

            // 3. Hex-encoded strings (e.g. "5A54454701020304" or "5A:54:45:47:..." or "00:1a:2b:3c:4d:5e")
            $cleanedHex = preg_replace('/[^0-9A-Fa-f]/', '', $rawSn);
            if (strlen($cleanedHex) >= 10 && strlen($cleanedHex) <= 12) {
                $paddedHex = str_pad($cleanedHex, 12, '0', STR_PAD_LEFT);
                return strtoupper(implode(':', str_split($paddedHex, 2)));
            }

            if (strlen($cleanedHex) === 16) {
                $decoded = @hex2bin($cleanedHex);
                if ($decoded && strlen($decoded) === 8) {
                    $vendor = substr($decoded, 0, 4);
                    $hexPart = bin2hex(substr($decoded, 4));
                    if (ctype_print($vendor) && preg_match('/^[A-Za-z0-9]{4}$/', $vendor)) {
                        return strtoupper($vendor . $hexPart);
                    }
                }
                return strtoupper($cleanedHex);
            }

            // 4. Printable ASCII string (already formatted GPON SN e.g. ZTEGC1234567 or MAC)
            if (ctype_print($trimmed)) {
                $invalidWords = ['enable', 'disable', 'working', 'syncmib', 'logging', 'online', 'offline', 'ready', 'na', 'n/a', 'null', 'none', 'authfailed', 'los', 'dyinggasp', 'unauth', 'deact', 'active', 'down', 'up', 'unknown'];
                if (in_array(strtolower($trimmed), $invalidWords, true)) {
                    return "ONU-IDX-{$fallbackIndex}";
                }
                return self::safeUtf8String($trimmed);
            }

            // 5. Fallback for non-printable binary strings
            return strtoupper(bin2hex($rawSn));
        }

        return self::safeUtf8String((string) $rawSn);
    }

    private function parseRxOpticalPower($rawVal, string $brand = ''): ?float
    {
        return $this->parseOpticalPower($rawVal, $brand, false);
    }

    private function parseTxOpticalPower($rawVal, string $brand = ''): ?float
    {
        return $this->parseOpticalPower($rawVal, $brand, true);
    }

    private function parseOpticalPower($rawVal, string $brand = '', bool $isTx = false): ?float
    {
        if ($rawVal === null || $rawVal === '' || $rawVal === 'NA' || $rawVal === 'N/A' || $rawVal === '-' || $rawVal === 'null') {
            return null;
        }

        if (is_string($rawVal)) {
            $cleaned = trim($rawVal);
            if (preg_match('/([+-]?\d+(?:\.\d+)?)/', $cleaned, $m)) {
                $f = (float) $m[1];
                if ($isTx) {
                    if ($f >= -10.0 && $f <= 15.0) {
                        return round($f, 2);
                    }
                } else {
                    if ($f <= -5.0 && $f >= -50.0) {
                        return round($f, 2);
                    }
                    if ($f >= 5.0 && $f <= 50.0 && (stripos($cleaned, 'dbm') !== false || stripos($cleaned, 'rx') !== false || stripos($cleaned, 'power') !== false)) {
                        return round(-$f, 2);
                    }
                }
                $rawVal = $f;
            }
        }

        if (is_numeric($rawVal)) {
            $n = (float) $rawVal;
            if ($n === 0.0 || $n === 65535.0 || $n === 2147483647.0 || $n === -65535.0 || $n === -2147483648.0 || $n === -1000.0) {
                return null;
            }

            // Convert 16-bit unsigned integer to signed integer (e.g. 62536 -> -3000)
            $signed = ($n > 32767 && $n <= 65535) ? ($n - 65536) : $n;

            if ($isTx) {
                // Direct float dBm (e.g. 0.5 to 8.0 dBm)
                if (is_float($rawVal) && $signed >= -10.0 && $signed <= 12.0) {
                    return round((float) $signed, 2);
                }

                // 0.1 scale (e.g. 5 to 60 -> 0.5 to 6.0 dBm, like BDCOM 15 -> 1.5 dBm)
                if ($signed >= 5 && $signed <= 60) {
                    return round((float) $signed / 10.0, 2);
                }

                // 0.01 scale (e.g. 60 to 600 -> 0.6 to 6.0 dBm, like 150 -> 1.5 dBm, 215 -> 2.15 dBm)
                if (($signed >= 60 && $signed <= 600) || ($signed >= -600 && $signed <= -50)) {
                    return round((float) $signed / 100.0, 2);
                }

                // 0.001 scale (e.g. 1000 to 10000 -> 1.0 to 10.0 dBm)
                if ($signed >= 1000 && $signed <= 10000) {
                    return round((float) $signed / 1000.0, 2);
                }

                // 0.0001 scale (e.g. 10000 to 100000 -> 1.0 to 10.0 dBm)
                if ($signed >= 10000 && $signed <= 100000) {
                    return round((float) $signed / 10000.0, 2);
                }

                if ($signed >= -10.0 && $signed <= 12.0) {
                    return round((float) $signed, 2);
                }
            } else {
                // RX Power: typically -50.0 to -5.0 dBm
                if ($signed <= -5.0 && $signed >= -50.0) {
                    return round((float) $signed, 2);
                }

                // Candidate scale factors
                $candidates = [
                    $signed / 100.0,
                    $signed / 10.0,
                    $signed / 1000.0,
                    $signed / 10000.0,
                    ($signed > 0 ? -$signed / 100.0 : $signed / 100.0),
                    ($signed > 0 ? -$signed / 10.0 : $signed / 10.0),
                    ($signed > 0 ? -$signed / 1000.0 : $signed / 1000.0),
                    ($signed / 100.0) - 100.0,
                    ($signed / 10.0) - 100.0,
                ];

                foreach ($candidates as $cand) {
                    if ($cand >= -50.0 && $cand <= -5.0) {
                        return round((float) $cand, 2);
                    }
                }
            }
        }

        return null;
    }

    private function parseTemperature($rawVal): ?float
    {
        if ($rawVal === null || $rawVal === '' || $rawVal === 'NA' || $rawVal === 'N/A' || $rawVal === '-' || $rawVal === 'null') {
            return null;
        }

        if (is_numeric($rawVal)) {
            $n = (float) $rawVal;
            if ($n <= 0 || $n === 65535.0 || $n === 2147483647.0 || $n === -65535.0 || $n === -2147483648.0) {
                return null;
            }

            // Direct Celsius (10°C - 90°C)
            if ($n >= 10.0 && $n <= 90.0) {
                return round($n, 1);
            }

            // SFP 1/256 scale (e.g. 10240 - 23040 -> 40°C - 90°C)
            if ($n >= 2560 && $n <= 25600) {
                $c = $n / 256.0;
                if ($c >= 10.0 && $c <= 95.0) {
                    return round($c, 1);
                }
            }

            // 0.1 scale (e.g. 250 - 900 -> 25.0°C - 90.0°C)
            if ($n >= 100 && $n <= 1000) {
                return round($n / 10.0, 1);
            }

            // 0.01 scale (e.g. 2500 - 9000 -> 25.0°C - 90.0°C)
            if ($n >= 1000 && $n <= 10000) {
                return round($n / 100.0, 1);
            }

            // 0.001 scale (e.g. 25000 - 90000 -> 25.0°C - 90.0°C)
            if ($n >= 10000 && $n <= 100000) {
                return round($n / 1000.0, 1);
            }
        }

        return null;
    }

    /**
     * Decode ZTE SNMP index into physical Shelf, Slot, Port, and ONU ID
     * Supports:
     * - 32-bit ifIndex (e.g. "268501248.1" or "268566784.18" or "268501248")
     * - Dotted notation (e.g. "1.1.1.1.1" -> rack.shelf.slot.port.onu or "1.2.1.18" -> shelf.slot.port.onu)
     * - String format (e.g. "gpon-onu_1/2/1:18")
     */
    public function decodeZteIndex(string $index): ?array
    {
        $clean = trim($index);
        if ($clean === '') {
            return null;
        }

        // 1. Text format (gpon-onu_1/2/1:18 or epon-onu_1/2/1:18)
        if (preg_match('/^(?:(gpon|epon)-onu_)?(?:(\d+)\/)?(\d+)\/(\d+):(\d+)$/i', $clean, $m)) {
            $type = !empty($m[1]) ? strtoupper($m[1]) : 'GPON';
            $shelf = !empty($m[2]) ? (int) $m[2] : 1;
            $slot = (int) $m[3];
            $port = (int) $m[4];
            $onuId = (int) $m[5];
            return [
                'type' => $type,
                'shelf' => $shelf,
                'slot' => $slot,
                'port' => $port,
                'onu_id' => $onuId,
                'pon_port' => "{$type} {$shelf}/{$slot}/{$port}",
                'onu_name' => strtolower($type) . "-onu_{$shelf}/{$slot}/{$port}:{$onuId}",
            ];
        }

        $parts = explode('.', $clean);

        // 2. ZTE 32-bit ifIndex + sub-onu (e.g. "268501248.1" or "268566784.18" or "268501248")
        if (is_numeric($parts[0])) {
            $num = (int) $parts[0];
            // ZTE GPON/EPON ifIndex is typically >= 0x10000000 (268435456)
            if ($num >= 268435456) {
                $typeId = ($num >> 28) & 0x0F;
                $shelf = ($num >> 24) & 0x0F;
                $slot = ($num >> 16) & 0xFF;
                $port = ($num >> 8) & 0xFF;
                $onuFromBits = $num & 0xFF;

                $type = ($typeId === 2) ? 'EPON' : 'GPON';
                $shelf = ($shelf > 0) ? $shelf : 1;
                $onuId = (isset($parts[1]) && is_numeric($parts[1])) ? (int) $parts[1] : ($onuFromBits > 0 ? $onuFromBits : 1);

                return [
                    'type' => $type,
                    'shelf' => $shelf,
                    'slot' => $slot,
                    'port' => $port,
                    'onu_id' => $onuId,
                    'pon_port' => "{$type} {$shelf}/{$slot}/{$port}",
                    'onu_name' => strtolower($type) . "-onu_{$shelf}/{$slot}/{$port}:{$onuId}",
                ];
            }
        }

        // 3. 5-part dotted OID (rack.shelf.slot.port.onu e.g. "1.1.2.1.18")
        if (count($parts) >= 5 && is_numeric($parts[1]) && is_numeric($parts[2]) && is_numeric($parts[3]) && is_numeric($parts[4])) {
            $shelf = (int) $parts[1];
            $slot = (int) $parts[2];
            $port = (int) $parts[3];
            $onuId = (int) $parts[4];
            return [
                'type' => 'GPON',
                'shelf' => $shelf > 0 ? $shelf : 1,
                'slot' => $slot,
                'port' => $port,
                'onu_id' => $onuId,
                'pon_port' => "GPON {$shelf}/{$slot}/{$port}",
                'onu_name' => "gpon-onu_{$shelf}/{$slot}/{$port}:{$onuId}",
            ];
        }

        // 4. 4-part dotted OID (shelf.slot.port.onu e.g. "1.2.1.18")
        if (count($parts) === 4 && is_numeric($parts[0]) && is_numeric($parts[1]) && is_numeric($parts[2]) && is_numeric($parts[3])) {
            $shelf = (int) $parts[0];
            $slot = (int) $parts[1];
            $port = (int) $parts[2];
            $onuId = (int) $parts[3];
            return [
                'type' => 'GPON',
                'shelf' => $shelf > 0 ? $shelf : 1,
                'slot' => $slot,
                'port' => $port,
                'onu_id' => $onuId,
                'pon_port' => "GPON {$shelf}/{$slot}/{$port}",
                'onu_name' => "gpon-onu_{$shelf}/{$slot}/{$port}:{$onuId}",
            ];
        }

        return null;
    }

    private function extractPonPort(string $index, string $brand, array $portMap = []): string
    {
        // 1. If ZTE or ZTE-like index, decode bitfields first
        if ($brand === 'zte' || (is_numeric(explode('.', $index)[0]) && (int)explode('.', $index)[0] >= 268435456)) {
            $zte = $this->decodeZteIndex($index);
            if ($zte && !empty($zte['pon_port'])) {
                return $zte['pon_port'];
            }
        }

        // 2. Check ifDescr map (e.g. "EPON0/1:1" -> "EPON0/1" or "gpon-olt_1/1/1" -> "GPON 1/1/1")
        if (!empty($portMap)) {
            $descr = $this->findValueInMap($portMap, $index);
            if (!empty($descr) && is_string($descr)) {
                $cleaned = preg_replace('/:[0-9]+$/', '', trim($descr));
                if (!empty($cleaned) && $cleaned !== 'NA' && $cleaned !== 'N/A') {
                    $cleaned = str_ireplace(['gpon-olt_', 'epon-olt_', 'gpon-onu_', 'epon-onu_'], ['GPON ', 'EPON ', 'GPON ', 'EPON '], $cleaned);
                    return strtoupper($cleaned);
                }
            }
        }

        // 3. Format like 1/1/1:1 or 1/2/1:1
        if (preg_match('/(?:gpon-onu_|epon-onu_)?(?:1\/)?(\d+)\/(\d+)(?:\/(\d+))?:?\d*/i', $index, $m)) {
            if (!empty($m[3])) {
                return "PON {$m[1]}/{$m[2]}/{$m[3]}";
            }
            return "PON {$m[1]}/{$m[2]}";
        }

        // 4. Dotted format e.g. 1.2.3.4
        $parts = explode('.', $index);
        if (count($parts) >= 4) {
            return "PON {$parts[0]}/{$parts[1]}/{$parts[2]}";
        }
        if (count($parts) === 3) {
            return "PON {$parts[0]}/{$parts[1]}";
        }
        if (count($parts) === 2 && is_numeric($parts[0]) && (int)$parts[0] < 1000) {
            return "PON {$parts[0]}";
        }

        if (is_numeric($index)) {
            $num = (int) $index;
            if ($brand === 'bdcom') {
                if ($num >= 97 && $num <= 128) return "EPON0/1";
                if ($num >= 129 && $num <= 160) return "EPON0/4";
                if ($num >= 161 && $num <= 192) return "EPON0/4";
                if ($num >= 193 && $num <= 224) return "EPON0/5";
                if ($num >= 65 && $num <= 88) return "EPON0/" . ($num - 75);
                $ponNum = intdiv($num, 64);
                return "EPON0/{$ponNum}";
            }
            if ($num > 0 && $num <= 32) {
                return "PON 1";
            }
        }
        return "PON 1";
    }

    /**
     * Resolve exact OID index for an ONU
     */
    public function resolveOnuSnmpIndex(Olt $olt, Onu $onu, array $profile, ?SnmpClient $client = null): string
    {
        $ponNum = 1;
        if (preg_match('/(\d+)/', (string) $onu->pon_port, $m)) {
            $ponNum = (int) $m[1];
        }

        $idxVal = $onu->onu_index ?? $onu->onu_id ?? null;

        if (!empty($idxVal) && str_contains((string) $idxVal, '.')) {
            return (string) $idxVal;
        }

        if (!empty($idxVal) && is_numeric($idxVal)) {
            return "{$ponNum}.{$idxVal}";
        }

        if (preg_match('/(\d+)\.(\d+)/', (string) $onu->name, $m)) {
            return "{$m[1]}.{$m[2]}";
        }

        // Try to match serial/MAC against sn_table walk if client is available
        if ($client && !empty($profile['sn_table']) && !empty($onu->serial_number)) {
            try {
                $walk = $client->walk($profile['sn_table']);
                $cleanSearch = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) $onu->serial_number));
                $items = $this->collectWalkItems($walk);
                foreach ($items as $item) {
                    if (!$item) continue;
                    $raw = (is_object($item->getValue()) && method_exists($item->getValue(), 'getValue')) ? $item->getValue()->getValue() : $item->getValue();
                    $formatted = $this->formatSerialNumber($raw, '');
                    $cleanSn = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $formatted));
                    if ($cleanSn && ($cleanSn === $cleanSearch || str_contains($cleanSn, $cleanSearch) || str_contains($cleanSearch, $cleanSn))) {
                        return $this->extractIndex((string) $item->getOid(), $profile['sn_table']);
                    }
                }
            } catch (\Throwable $e) {}
        }

        return "{$ponNum}.1";
    }

    /**
     * Reboot an individual ONU via SNMP SET
     */
    public function rebootOnuViaSnmp(Olt $olt, Onu $onu): array
    {
        $host = trim((string) $olt->host);
        $snmpPort = (int) ($olt->snmp_port ?: ($olt->port ?: 161));
        if (str_contains($host, ':')) {
            [$h, $p] = explode(':', $host, 2);
            $host = $h;
            if (is_numeric($p) && empty($olt->snmp_port)) {
                $snmpPort = (int) $p;
            }
        }

        $community = $olt->snmp_community ?: 'public';
        $brand = strtolower($olt->model ?? 'hioso');
        $profiles = self::BRAND_PROFILES[$brand] ?? self::BRAND_PROFILES['hioso'];
        $profile = $profiles[0];

        if ($olt->submodel) {
            foreach ($profiles as $p) {
                if ($p['name'] === $olt->submodel) {
                    $profile = $p;
                    break;
                }
            }
        }

        $lastError = '';
        foreach ([2, 1] as $ver) {
            try {
                $client = new SnmpClient([
                    'host' => $host,
                    'port' => $snmpPort,
                    'version' => $ver,
                    'community' => $community,
                    'timeout_connect' => 3,
                    'timeout_read' => 3,
                ]);

                $index = $this->resolveOnuSnmpIndex($olt, $onu, $profile, $client);

                // Determine Reboot OID based on profile
                $rebootOid = null;
                $rebootValue = 1;

                if ($profile['name'] === 'HIOSO_EPON_C') {
                    $rebootOid = "1.3.6.1.4.1.25355.3.2.6.3.2.1.40.{$index}";
                } elseif ($profile['name'] === 'HIOSO_EPON_B' || $profile['name'] === 'BDCOM_EPON' || $profile['name'] === 'HSGQ_EPON') {
                    $rebootOid = "1.3.6.1.4.1.3320.101.10.1.1.28.{$index}";
                } elseif ($profile['name'] === 'VSOL_EPON') {
                    $rebootOid = "1.3.6.1.4.1.37950.1.1.5.13.1.1.15.{$index}";
                } elseif ($profile['name'] === 'ZTE_GPON_C300') {
                    $rebootOid = "1.3.6.1.4.1.3902.1082.500.10.2.3.8.1.4.{$index}";
                } elseif ($profile['name'] === 'HUAWEI_GPON') {
                    $rebootOid = "1.3.6.1.4.1.2011.6.128.1.1.2.45.1.5.{$index}";
                } elseif ($profile['name'] === 'CDATA_EPON') {
                    $rebootOid = "1.3.6.1.4.1.34592.1.3.100.12.1.1.1.16.{$index}";
                } else {
                    $rebootOid = ($profile['status_table'] ?? '1.3.6.1.4.1.25355.3.2.6.3.2.1.39') . ".{$index}";
                    $rebootValue = 2;
                }

                $oidObj = \FreeDSx\Snmp\Oid::fromInteger($rebootOid, $rebootValue);
                $client->set($oidObj);

                return [
                    'success' => true,
                    'protocol' => "SNMPv{$ver} SET",
                    'message' => "Perintah restart ONU berhasil dikirim via SNMP SET ke OLT ({$rebootOid} = {$rebootValue})",
                ];
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        return [
            'success' => false,
            'protocol' => 'SNMP SET',
            'message' => "Error SNMP SET: {$lastError}",
        ];
    }

    /**
     * Delete / unbind an ONU via SNMP SET
     */
    public function deleteOnuViaSnmp(Olt $olt, Onu $onu): array
    {
        $host = trim((string) $olt->host);
        $snmpPort = (int) ($olt->snmp_port ?: ($olt->port ?: 161));
        if (str_contains($host, ':')) {
            [$h, $p] = explode(':', $host, 2);
            $host = $h;
            if (is_numeric($p) && empty($olt->snmp_port)) {
                $snmpPort = (int) $p;
            }
        }

        $community = $olt->snmp_community ?: 'public';
        $brand = strtolower($olt->model ?? 'hioso');
        $profiles = self::BRAND_PROFILES[$brand] ?? self::BRAND_PROFILES['hioso'];
        $profile = $profiles[0];

        if ($olt->submodel) {
            foreach ($profiles as $p) {
                if ($p['name'] === $olt->submodel) {
                    $profile = $p;
                    break;
                }
            }
        }

        $lastError = '';
        foreach ([2, 1] as $ver) {
            try {
                $client = new SnmpClient([
                    'host' => $host,
                    'port' => $snmpPort,
                    'version' => $ver,
                    'community' => $community,
                    'timeout_connect' => 3,
                    'timeout_read' => 3,
                ]);

                $index = $this->resolveOnuSnmpIndex($olt, $onu, $profile, $client);
                $statusTable = $profile['status_table'] ?? '1.3.6.1.4.1.25355.3.2.6.3.2.1.39';
                $deleteOid = "{$statusTable}.{$index}";

                // RowStatus 6 = destroy
                $oidObj = \FreeDSx\Snmp\Oid::fromInteger($deleteOid, 6);
                $client->set($oidObj);

                return [
                    'success' => true,
                    'protocol' => "SNMPv{$ver} SET",
                    'message' => "Perintah unbind/hapus ONU berhasil dikirim via SNMP SET ({$deleteOid} = 6)",
                ];
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        return [
            'success' => false,
            'protocol' => 'SNMP SET',
            'message' => "Error SNMP SET: {$lastError}",
        ];
    }

    /**
     * Reboot an individual ONU via OLT Telnet CLI
     */
    public function rebootOnuViaTelnet(Olt $olt, Onu $onu): array
    {
        $host = trim((string) $olt->host);
        if (str_contains($host, ':')) {
            [$h, $p] = explode(':', $host, 2);
            $host = $h;
        }

        $telnetPort = (int) ($olt->telnet_port ?: 23);
        $username = $olt->username ?: 'admin';
        $password = $olt->password ?: 'admin';
        $enablePassword = $olt->enable_password ?: $password;
        $brand = strtolower($olt->model ?? 'hioso');

        $ponNum = 1;
        if (preg_match('/(\d+)/', (string) $onu->pon_port, $m)) {
            $ponNum = (int) $m[1];
        }

        $onuIndex = $onu->onu_index ?? $onu->onu_id ?? 1;
        if (empty($onu->onu_index) && preg_match('/(\d+)\.(\d+)/', (string) $onu->name, $m)) {
            $ponNum = (int) $m[1];
            $onuIndex = (int) $m[2];
        }

        $mac = strtoupper(str_replace([':', '-', '.'], '', (string) $onu->serial_number));

        try {
            $fp = @fsockopen($host, $telnetPort, $errno, $errstr, 4);
            if (!$fp) {
                return [
                    'success' => false,
                    'message' => "Gagal koneksi Telnet ke OLT {$host}:{$telnetPort} ({$errstr})",
                ];
            }

            stream_set_timeout($fp, 5);

            // Execute robust login handshake
            $this->performTelnetLogin($fp, $username, $password, $enablePassword);

            // Send vendor-specific CLI reboot commands
            $out = '';
            if ($brand === 'hioso') {
                fwrite($fp, "config\r\n");
                $this->telnetReadUntil($fp, ['(config)#', '#', '>']);
                fwrite($fp, "interface epon 0/{$ponNum}\r\n");
                $this->telnetReadUntil($fp, ['(config-if)#', '(config-if-epon)#', '#', '>']);
                fwrite($fp, "epon onu-reboot {$onuIndex}\r\n");
                $out .= $this->telnetReadUntil($fp, ['#', '>']);
                if ($mac) {
                    fwrite($fp, "epon onu-reboot {$mac}\r\n");
                    $out .= $this->telnetReadUntil($fp, ['#', '>']);
                }
                fwrite($fp, "exit\r\n");
                fwrite($fp, "reboot onu 0/{$ponNum}:{$onuIndex}\r\n");
                $out .= $this->telnetReadUntil($fp, ['#', '>']);
            } elseif ($brand === 'vsol') {
                fwrite($fp, "config\r\n");
                $this->telnetReadUntil($fp, ['(config)#', '#']);
                fwrite($fp, "interface epon 0/{$ponNum}\r\n");
                $this->telnetReadUntil($fp, ['#']);
                fwrite($fp, "ont reboot {$onuIndex}\r\n");
                $out .= $this->telnetReadUntil($fp, ['#']);
            } elseif ($brand === 'cdata' || $brand === 'hsgq' || $brand === 'bdcom') {
                fwrite($fp, "config\r\n");
                $this->telnetReadUntil($fp, ['(config)#', '#']);
                fwrite($fp, "interface epon 0/{$ponNum}\r\n");
                $this->telnetReadUntil($fp, ['#']);
                fwrite($fp, "epon onu {$onuIndex} reboot\r\n");
                $out .= $this->telnetReadUntil($fp, ['#']);
                fwrite($fp, "ont reboot {$onuIndex}\r\n");
                $out .= $this->telnetReadUntil($fp, ['#']);
            } elseif ($brand === 'zte') {
                fwrite($fp, "conf t\r\n");
                $this->telnetReadUntil($fp, ['(config)#', '#']);
                fwrite($fp, "interface gpon-onu_1/1/{$ponNum}:{$onuIndex}\r\n");
                $this->telnetReadUntil($fp, ['#']);
                fwrite($fp, "reboot\r\n");
                $out .= $this->telnetReadUntil($fp, ['#']);
            } elseif ($brand === 'huawei') {
                fwrite($fp, "config\r\n");
                $this->telnetReadUntil($fp, ['(config)#', '#']);
                fwrite($fp, "interface gpon 0/1\r\n");
                $this->telnetReadUntil($fp, ['#']);
                fwrite($fp, "ont reset {$ponNum} {$onuIndex}\r\n");
                $out .= $this->telnetReadUntil($fp, ['#']);
            } else {
                fwrite($fp, "reboot onu {$onuIndex}\r\n");
                $out .= $this->telnetReadUntil($fp, ['#']);
            }

            fclose($fp);

            return [
                'success' => true,
                'message' => "Perintah restart berhasil dikirim via Telnet OLT ke PON {$ponNum}:{$onuIndex}",
                'output' => $out,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => "OLT Telnet error: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Delete / unbind an ONU from OLT via Telnet CLI
     */
    public function deleteOnuViaTelnet(Olt $olt, Onu $onu): array
    {
        $host = trim((string) $olt->host);
        if (str_contains($host, ':')) {
            [$h, $p] = explode(':', $host, 2);
            $host = $h;
        }

        $telnetPort = (int) ($olt->telnet_port ?: 23);
        $username = $olt->username ?: 'admin';
        $password = $olt->password ?: 'admin';
        $enablePassword = $olt->enable_password ?: $password;
        $brand = strtolower($olt->model ?? 'hioso');

        $ponNum = 1;
        if (preg_match('/(\d+)/', (string) $onu->pon_port, $m)) {
            $ponNum = (int) $m[1];
        }

        $onuIndex = $onu->onu_index ?? $onu->onu_id ?? 1;
        if (empty($onu->onu_index) && preg_match('/(\d+)\.(\d+)/', (string) $onu->name, $m)) {
            $ponNum = (int) $m[1];
            $onuIndex = (int) $m[2];
        }

        $mac = strtoupper(str_replace([':', '-', '.'], '', (string) $onu->serial_number));

        try {
            $fp = @fsockopen($host, $telnetPort, $errno, $errstr, 4);
            if (!$fp) {
                return [
                    'success' => false,
                    'message' => "Gagal koneksi Telnet ke OLT {$host}:{$telnetPort} ({$errstr})",
                ];
            }

            stream_set_timeout($fp, 5);

            // Execute robust login handshake
            $this->performTelnetLogin($fp, $username, $password, $enablePassword);

            // Send vendor-specific unbind / delete commands
            $out = '';
            if ($brand === 'hioso') {
                fwrite($fp, "config\r\n");
                $this->telnetReadUntil($fp, ['(config)#', '#', '>']);
                fwrite($fp, "interface epon 0/{$ponNum}\r\n");
                $this->telnetReadUntil($fp, ['(config-if)#', '(config-if-epon)#', '#', '>']);
                fwrite($fp, "no epon bind-onu {$onuIndex}\r\n");
                $out .= $this->telnetReadUntil($fp, ['#', '>']);
                if ($mac) {
                    fwrite($fp, "no epon bind-onu {$mac}\r\n");
                    $out .= $this->telnetReadUntil($fp, ['#', '>']);
                }
            } elseif ($brand === 'vsol') {
                fwrite($fp, "config\r\n");
                $this->telnetReadUntil($fp, ['(config)#', '#']);
                fwrite($fp, "interface epon 0/{$ponNum}\r\n");
                $this->telnetReadUntil($fp, ['#']);
                fwrite($fp, "no ont {$onuIndex}\r\n");
                $out .= $this->telnetReadUntil($fp, ['#']);
            } elseif ($brand === 'cdata' || $brand === 'hsgq' || $brand === 'bdcom') {
                fwrite($fp, "config\r\n");
                $this->telnetReadUntil($fp, ['(config)#', '#']);
                fwrite($fp, "interface epon 0/{$ponNum}\r\n");
                $this->telnetReadUntil($fp, ['#']);
                fwrite($fp, "no epon bind-onu {$onuIndex}\r\n");
                $out .= $this->telnetReadUntil($fp, ['#']);
                fwrite($fp, "no ont {$onuIndex}\r\n");
                $out .= $this->telnetReadUntil($fp, ['#']);
            } elseif ($brand === 'zte') {
                fwrite($fp, "conf t\r\n");
                $this->telnetReadUntil($fp, ['(config)#', '#']);
                fwrite($fp, "interface gpon-olt_1/1/{$ponNum}\r\n");
                $this->telnetReadUntil($fp, ['#']);
                fwrite($fp, "no onu {$onuIndex}\r\n");
                $out .= $this->telnetReadUntil($fp, ['#']);
            } elseif ($brand === 'huawei') {
                fwrite($fp, "config\r\n");
                $this->telnetReadUntil($fp, ['(config)#', '#']);
                fwrite($fp, "interface gpon 0/1\r\n");
                $this->telnetReadUntil($fp, ['#']);
                fwrite($fp, "ont delete {$ponNum} {$onuIndex}\r\n");
                $out .= $this->telnetReadUntil($fp, ['#']);
            }

            fclose($fp);

            return [
                'success' => true,
                'message' => "Perintah unbind/hapus ONU berhasil dikirim ke OLT (PON {$ponNum}:{$onuIndex})",
                'output' => $out,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => "OLT Telnet error: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Poll ONUs and hardware metrics via OLT Web Management API (GoAhead / EPON System)
     */
    public function pollViaHttp(Olt $olt): array
    {
        $host = trim((string) $olt->host);
        $port = 80;
        if (str_contains($host, ':')) {
            [$h, $p] = explode(':', $host, 2);
            $host = $h;
            if (is_numeric($p)) {
                $port = (int) $p;
            }
        }

        $username = $olt->username ?: 'admin';
        $password = $olt->password ?: 'admin';

        $url = "http://{$host}:{$port}/onuAllPonOnuList.asp";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "{$username}:{$password}");
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || empty($html)) {
            return [
                'success' => false,
                'count' => 0,
                'message' => "Gagal koneksi ke API Web OLT {$host}:{$port} (HTTP {$httpCode}: {$curlError})",
            ];
        }

        $discoveredOnus = [];
        // Pattern: '0/1/1:1','FERI','68:8b:0f:cd:bc:88','Up','0101','9127','5','41.00','3.00','14.00','2.54','-22.68',...
        if (preg_match_all("/'0\/1\/(\d+):(\d+)'\s*,\s*'([^']*)'\s*,\s*'([^']*)'\s*,\s*'([^']*)'\s*,\s*'[^']*'\s*,\s*'[^']*'\s*,\s*'[^']*'\s*,\s*('[^']*'|[0-9\.]+)\s*,\s*('[^']*'|[0-9\.]+)\s*,\s*('[^']*'|[0-9\.]+)\s*,\s*('[^']*'|[0-9\.]+)\s*,\s*'([^']*)'[^,]*,\s*[^,]*,\s*[^,]*,\s*[^,]*,\s*([0-9]+)/", $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $ponNum = $m[1];
                $onuIndex = $m[2];
                $name = trim($m[3]);
                $mac = strtoupper(trim($m[4]));
                $rawStatus = trim($m[5]);
                $isUp = strtolower($rawStatus) === 'up';
                $status = $isUp ? 'online' : 'offline';
                $offlineReason = null;
                if (!$isUp) {
                    $lowerRaw = strtolower($rawStatus);
                    if (str_contains($lowerRaw, 'pwr') || str_contains($lowerRaw, 'power') || str_contains($lowerRaw, 'gasp')) {
                        $offlineReason = 'power_down';
                    } else {
                        $offlineReason = 'down'; // Down / Kabel Putus
                    }
                }
                $temperature = is_numeric($m[6]) ? (float) $m[6] : null;
                $txPower = is_numeric($m[9]) ? (float) $m[9] : null;
                $rxPower = is_numeric($m[10]) ? (float) $m[10] : null;
                $distance = is_numeric($m[11]) ? (float) $m[11] : null;

                $discoveredOnus[] = [
                    'index' => "{$ponNum}.{$onuIndex}",
                    'onu_id' => (int) $onuIndex,
                    'name' => $name !== 'NA' && !empty($name) ? $name : "ONU-{$ponNum}.{$onuIndex}",
                    'serial_number' => $mac,
                    'pon_port' => "PON {$ponNum}",
                    'status' => $status,
                    'offline_reason' => $offlineReason,
                    'rx_power' => $rxPower,
                    'tx_power' => $txPower,
                    'distance' => $distance,
                    'temperature' => $temperature,
                ];
            }
        } elseif (preg_match_all("/'0\/1\/(\d+):(\d+)'\s*,\s*'([^']*)'\s*,\s*'([^']*)'\s*,\s*'([^']*)'/", $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $ponNum = $m[1];
                $onuIndex = $m[2];
                $name = trim($m[3]);
                $mac = strtoupper(trim($m[4]));
                $rawStatus = trim($m[5]);
                $isUp = strtolower($rawStatus) === 'up';
                $status = $isUp ? 'online' : 'offline';
                $offlineReason = null;
                if (!$isUp) {
                    $lowerRaw = strtolower($rawStatus);
                    if (str_contains($lowerRaw, 'pwr') || str_contains($lowerRaw, 'power') || str_contains($lowerRaw, 'gasp')) {
                        $offlineReason = 'power_down';
                    } else {
                        $offlineReason = 'down';
                    }
                }

                $discoveredOnus[] = [
                    'index' => "{$ponNum}.{$onuIndex}",
                    'onu_id' => (int) $onuIndex,
                    'name' => $name !== 'NA' && !empty($name) ? $name : "ONU-{$ponNum}.{$onuIndex}",
                    'serial_number' => $mac,
                    'pon_port' => "PON {$ponNum}",
                    'status' => $status,
                    'offline_reason' => $offlineReason,
                    'rx_power' => null,
                    'distance' => null,
                ];
            }
        }

        if (empty($discoveredOnus)) {
            return [
                'success' => false,
                'count' => 0,
                'message' => "Tidak ada ONU ditemukan di API Web OLT {$olt->name}",
            ];
        }

        // Compute exact dynamic PON port statistics
        $ponPortStats = [];
        foreach ($discoveredOnus as $d) {
            $p = $d['pon_port'] ?? 'PON 1';
            if (!isset($ponPortStats[$p])) {
                $ponPortStats[$p] = ['total' => 0, 'online' => 0, 'offline' => 0];
            }
            $ponPortStats[$p]['total']++;
            if (($d['status'] ?? '') === 'online') {
                $ponPortStats[$p]['online']++;
            } else {
                $ponPortStats[$p]['offline']++;
            }
        }

        // Fetch CPU & Memory from system.asp if available
        $cpu = 5;
        $ram = 30;
        try {
            $sysCh = curl_init("http://{$host}:{$port}/system.asp");
            curl_setopt($sysCh, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($sysCh, CURLOPT_USERPWD, "{$username}:{$password}");
            curl_setopt($sysCh, CURLOPT_TIMEOUT, 3);
            $sysHtml = curl_exec($sysCh);
            curl_close($sysCh);

            if (preg_match('/var\s+cpu_rate\s*=\s*([0-9\.]+)/', (string) $sysHtml, $cm)) {
                $cpu = (int) $cm[1];
            }
            if (preg_match('/var\s+mem_rate\s*=\s*([0-9\.]+)/', (string) $sysHtml, $rm)) {
                $ram = (int) $rm[1];
            }
        } catch (\Throwable $e) {}

        $olt->hardware_metrics = [
            'cpu' => $cpu,
            'ram' => $ram,
            'ram_used_mb' => round(128 * ($ram / 100)),
            'ram_total_mb' => 128,
            'temp' => 45,
            'uptime' => 'Online',
            'sys_name' => 'HIOSO HA7302',
            'sys_descr' => 'Hioso EPON OLT Web System',
            'pon_ports' => $ponPortStats,
            'updated_at' => now()->toIso8601String(),
        ];

        return $this->persistDiscoveredOnus($olt, $discoveredOnus, true, 'HIOSO_EPON_WEB', 'HTTP Web API');
    }

    /**
     * Reboot an individual ONU via OLT Web Management API (GoAhead / EPON System)
     */
    public function rebootOnuViaHttp(Olt $olt, Onu $onu): array
    {
        $host = trim((string) $olt->host);
        $port = 80;
        if (str_contains($host, ':')) {
            [$h, $p] = explode(':', $host, 2);
            $host = $h;
            if (is_numeric($p)) {
                $port = (int) $p;
            }
        }

        $username = $olt->username ?: 'admin';
        $password = $olt->password ?: 'admin';

        $onuIdStr = null;
        $onuName = $onu->name;

        // 1. Live MAC resolution from OLT active table to get the exact slot (e.g. 0/1/1:2)
        if (!empty($onu->serial_number)) {
            try {
                $targetMac = preg_replace('/[^0-9a-fA-F]/', '', (string) $onu->serial_number);
                $ch = curl_init("http://{$host}:{$port}/onuAllPonOnuList.asp");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERPWD, "{$username}:{$password}");
                curl_setopt($ch, CURLOPT_TIMEOUT, 4);
                $html = curl_exec($ch);
                curl_close($ch);

                if ($html && preg_match_all("/'(0\/1\/\d+:\d+)'\s*,\s*'([^']*)'\s*,\s*'([^']*)'/", $html, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $m) {
                        $cleanMac = preg_replace('/[^0-9a-fA-F]/', '', $m[3]);
                        if (!empty($cleanMac) && strcasecmp($cleanMac, $targetMac) === 0) {
                            $onuIdStr = $m[1];
                            $onuName = !empty($m[2]) && $m[2] !== 'NA' ? $m[2] : $onu->name;
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }

        // 2. Fallback to DB record
        if (!$onuIdStr) {
            $ponNum = 1;
            if (preg_match('/(\d+)/', (string) $onu->pon_port, $m)) {
                $ponNum = (int) $m[1];
            }

            $onuIndex = $onu->onu_index ?? $onu->onu_id ?? 1;
            if (empty($onu->onu_index) && preg_match('/(\d+)\.(\d+)/', (string) $onu->name, $m)) {
                $ponNum = (int) $m[1];
                $onuIndex = (int) $m[2];
            }

            $onuIdStr = "0/1/{$ponNum}:{$onuIndex}";
        }

        if (empty($onuName)) {
            $onuName = "ONU-{$onuIdStr}";
        }

        $ch = curl_init("http://{$host}:{$port}/goform/setOnu");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "{$username}:{$password}");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'onuId' => $onuIdStr,
            'onuName' => $onuName,
            'onuOperation' => 'rebootOp',
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 400) {
            return [
                'success' => true,
                'protocol' => 'HTTP Web API',
                'message' => "Perintah restart berhasil dikirim via API Web OLT ke {$onuName} ({$onuIdStr})",
            ];
        }

        return [
            'success' => false,
            'protocol' => 'HTTP Web API',
            'message' => "Gagal mengirim restart via API Web OLT {$host}:{$port} (HTTP {$httpCode}: {$curlError})",
        ];
    }

    /**
     * Delete / unbind an ONU via OLT Web Management API (GoAhead / EPON System)
     */
    public function deleteOnuViaHttp(Olt $olt, Onu $onu): array
    {
        $host = trim((string) $olt->host);
        $port = 80;
        if (str_contains($host, ':')) {
            [$h, $p] = explode(':', $host, 2);
            $host = $h;
            if (is_numeric($p)) {
                $port = (int) $p;
            }
        }

        $username = $olt->username ?: 'admin';
        $password = $olt->password ?: 'admin';

        $onuIdStr = null;

        // 1. Live MAC resolution from OLT active table to get the exact slot (e.g. 0/1/1:2)
        if (!empty($onu->serial_number)) {
            try {
                $targetMac = preg_replace('/[^0-9a-fA-F]/', '', (string) $onu->serial_number);
                $ch = curl_init("http://{$host}:{$port}/onuAllPonOnuList.asp");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERPWD, "{$username}:{$password}");
                curl_setopt($ch, CURLOPT_TIMEOUT, 4);
                $html = curl_exec($ch);
                curl_close($ch);

                if ($html && preg_match_all("/'(0\/1\/\d+:\d+)'\s*,\s*'([^']*)'\s*,\s*'([^']*)'/", $html, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $m) {
                        $cleanMac = preg_replace('/[^0-9a-fA-F]/', '', $m[3]);
                        if (!empty($cleanMac) && strcasecmp($cleanMac, $targetMac) === 0) {
                            $onuIdStr = $m[1];
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }

        // 2. Fallback to DB record
        if (!$onuIdStr) {
            $ponNum = 1;
            if (preg_match('/(\d+)/', (string) $onu->pon_port, $m)) {
                $ponNum = (int) $m[1];
            }

            $onuIndex = $onu->onu_index ?? $onu->onu_id ?? 1;
            if (empty($onu->onu_index) && preg_match('/(\d+)\.(\d+)/', (string) $onu->name, $m)) {
                $ponNum = (int) $m[1];
                $onuIndex = (int) $m[2];
            }

            $onuIdStr = "0/1/{$ponNum}:{$onuIndex}";
        }

        $ch = curl_init("http://{$host}:{$port}/goform/deleteOnu");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "{$username}:{$password}");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'onuId' => $onuIdStr,
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 400) {
            return [
                'success' => true,
                'protocol' => 'HTTP Web API',
                'message' => "Perintah unbind/hapus ONU berhasil dikirim via API Web OLT ({$onuIdStr})",
            ];
        }

        return [
            'success' => false,
            'protocol' => 'HTTP Web API',
            'message' => "Gagal unbind ONU via API Web OLT {$host}:{$port} (HTTP {$httpCode}: {$curlError})",
        ];
    }

    /**
     * Recursively sanitizes any string or array of strings to valid UTF-8.
     */
    public static function safeUtf8(mixed $data): mixed
    {
        if (is_array($data)) {
            $clean = [];
            foreach ($data as $key => $value) {
                $cleanKey = is_string($key) ? self::safeUtf8String($key) : $key;
                $clean[$cleanKey] = self::safeUtf8($value);
            }
            return $clean;
        }

        if (is_string($data)) {
            return self::safeUtf8String($data);
        }

        return $data;
    }

    /**
     * Sanitizes a single string to clean, valid UTF-8 without control characters or null bytes.
     */
    public static function safeUtf8String(?string $str): string
    {
        if ($str === null || $str === '') {
            return '';
        }

        // 1. Remove null bytes and non-printable control characters (keep \t, \n, \r)
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $str);

        // 2. Convert from common vendor SNMP encodings if not UTF-8
        if (!mb_check_encoding($clean, 'UTF-8')) {
            $converted = @mb_convert_encoding($clean, 'UTF-8', ['UTF-8', 'GBK', 'GB2312', 'GB18030', 'CP936', 'ISO-8859-1', 'Windows-1252']);
            if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                $clean = $converted;
            } else {
                $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $clean) ?: '';
            }
        }

        // 3. Scrub any remaining invalid multibyte sequences
        if (function_exists('mb_scrub')) {
            $clean = mb_scrub($clean, 'UTF-8');
        }

        return trim($clean);
    }
}
