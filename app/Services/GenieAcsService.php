<?php

namespace App\Services;

use App\Models\OntDevice;
use App\Models\AcsTenantSetting;
use App\Models\Customer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenieAcsService
{
    protected string $nbiUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->nbiUrl = config('services.genieacs.nbi_url', env('GENIEACS_NBI_URL', 'http://127.0.0.1:7557'));
        $this->timeout = 10;
    }

    /**
     * Safely extract scalar string from raw GenieACS parameter node
     */
    public function extractScalar(mixed $val, ?string $fallback = null): ?string
    {
        if ($val === null) {
            return $fallback;
        }
        if (is_scalar($val)) {
            $str = trim((string) $val);
            return $str !== '' ? $str : $fallback;
        }
        if (is_array($val)) {
            if (isset($val['_value']) && is_scalar($val['_value'])) {
                $str = trim((string) $val['_value']);
                return $str !== '' ? $str : $fallback;
            }
            if (isset($val['value']) && is_scalar($val['value'])) {
                $str = trim((string) $val['value']);
                return $str !== '' ? $str : $fallback;
            }
            return $fallback;
        }
        return $fallback;
    }

    /**
     * Safely extract float / numeric from raw GenieACS parameter node
     */
    public function extractNumeric(mixed $val, ?float $fallback = null): ?float
    {
        $scalar = $this->extractScalar($val);
        if ($scalar !== null && is_numeric($scalar)) {
            return (float) $scalar;
        }
        return $fallback;
    }

    /**
     * Build HTTP client with optional Basic Auth for Self-Hosted GenieACS
     */
    protected function makeHttpClient(int $tenantId = 1, int $timeout = 4)
    {
        $client = Http::timeout($timeout)->withoutVerifying();
        try {
            $setting = AcsTenantSetting::where('tenant_id', $tenantId)->first();
            if ($setting && $setting->connection_mode === 'self_hosted' && !empty($setting->acs_username) && !empty($setting->acs_password)) {
                $client = $client->withBasicAuth($setting->acs_username, $setting->acs_password);
            }
        } catch (\Throwable $e) {}

        return $client;
    }

    /**
     * Get NBI base URL candidates with automatic failover (Direct 7557, WA Proxy 3022, Local)
     */
    public function getNbiCandidates(int $tenantId = 1): array
    {
        try {
            $setting = AcsTenantSetting::where('tenant_id', $tenantId)->first();
            if ($setting && $setting->connection_mode === 'self_hosted') {
                if (!empty($setting->nbi_url)) {
                    return [rtrim($setting->nbi_url, '/')];
                }
                return [];
            }
            if ($setting && !empty($setting->nbi_url)) {
                $candidates[] = rtrim($setting->nbi_url, '/');
            }
        } catch (\Throwable $e) {}

        $candidates = [];
        if (!empty($this->nbiUrl)) {
            $candidates[] = rtrim($this->nbiUrl, '/');
        }

        $candidates[] = 'http://acs.dgtlnetsolution.com:3022/genieacs';
        $candidates[] = 'http://113.192.48.45:3022/genieacs';
        $candidates[] = 'http://127.0.0.1:3022/genieacs';
        $candidates[] = 'http://127.0.0.1:7557';
        $candidates[] = 'http://acs.dgtlnetsolution.com:7557';

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * Get NBI base URL dynamically according to tenant settings (Cloud vs Self-Hosted)
     */
    public function getTenantNbiUrl(int $tenantId = 1): string
    {
        $candidates = $this->getNbiCandidates($tenantId);
        return $candidates[0] ?? rtrim($this->nbiUrl, '/');
    }

    /**
     * Get all devices from GenieACS NBI with optional projection/filters and multi-endpoint fallback
     */
    public function getDevices(int $tenantId, array $filters = []): array
    {
        $setting = AcsTenantSetting::where('tenant_id', $tenantId)->first();
        
        $query = [];
        if ($setting && $setting->connection_mode === 'self_hosted') {
            // Self-hosted server: tenant owns entire ACS server, no forced tag query
            if (!empty($filters['tag'])) {
                $query['_tags'] = $filters['tag'];
            }
        } else {
            // Cloud managed server: isolated by tenant tag
            if ($setting && !empty($setting->acs_username)) {
                $tag = $setting->acs_username;
                // Include matching tenant tag OR untagged/newly registered CPEs
                $query['$or'] = [
                    ['_tags' => $tag],
                    ['_tags' => ['$exists' => false]],
                    ['_tags' => ['$size' => 0]],
                ];
            } elseif ($tenantId > 1) {
                $query['_tags'] = 'tenant_' . $tenantId;
            }
        }

        if (!empty($filters['serial'])) {
            $query['_id'] = ['$regex' => $filters['serial']];
        }

        $candidates = $this->getNbiCandidates($tenantId);

        foreach ($candidates as $baseUrl) {
            try {
                $url = $baseUrl . '/devices/?query=' . urlencode(json_encode($query));
                $response = $this->makeHttpClient($tenantId, 3)->get($url);

                if ($response->successful()) {
                    $data = $response->json();
                    if (is_array($data)) {
                        return $data;
                    }
                }
            } catch (\Throwable $e) {
                // Try next candidate endpoint
            }
        }

        return [];
    }

    /**
     * Resolve clean Serial Number or local device ID to full GenieACS Device _id
     */
    public function resolveAcsDeviceId(string $deviceId, int $tenantId = 1): string
    {
        // If already looks like a full GenieACS composite _id
        if (str_contains($deviceId, '-') && (str_contains($deviceId, '%20') || str_contains($deviceId, ' '))) {
            return $deviceId;
        }

        // Try lookup in local DB
        $ont = OntDevice::where('tenant_id', $tenantId)
            ->where('serial_number', $deviceId)
            ->first();

        if ($ont && !empty($ont->raw_parameters['acs_device_id'])) {
            return $ont->raw_parameters['acs_device_id'];
        }

        // Query GenieACS dynamically by SerialNumber
        $candidates = $this->getNbiCandidates($tenantId);
        foreach ($candidates as $baseUrl) {
            try {
                $query = json_encode(['_deviceId._SerialNumber' => $deviceId]);
                $url = $baseUrl . '/devices?query=' . urlencode($query);
                $resp = $this->makeHttpClient($tenantId, 2)->get($url);
                if ($resp->successful()) {
                    $items = $resp->json();
                    if (!empty($items[0]['_id'])) {
                        return $items[0]['_id'];
                    }
                }
            } catch (\Throwable $e) {}
        }

        return $deviceId;
    }

    /**
     * Add / assign tag to device in GenieACS NBI
     */
    public function tagDevice(string $deviceId, string $tag, int $tenantId = 1): bool
    {
        $acsId = $this->resolveAcsDeviceId($deviceId, $tenantId);
        $candidates = $this->getNbiCandidates($tenantId);

        foreach ($candidates as $baseUrl) {
            try {
                $url = $baseUrl . '/devices/' . urlencode($acsId) . '/tags/' . urlencode($tag);
                $response = $this->makeHttpClient($tenantId, 3)->post($url);
                if ($response->successful() || $response->status() === 200 || $response->status() === 204) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Try next candidate
            }
        }
        return false;
    }

    /**
     * Remove / delete tag from device in GenieACS NBI
     */
    public function untagDevice(string $deviceId, string $tag, int $tenantId = 1): bool
    {
        $acsId = $this->resolveAcsDeviceId($deviceId, $tenantId);
        $candidates = $this->getNbiCandidates($tenantId);

        foreach ($candidates as $baseUrl) {
            try {
                $url = $baseUrl . '/devices/' . urlencode($acsId) . '/tags/' . urlencode($tag);
                $response = $this->makeHttpClient($tenantId, 3)->delete($url);
                if ($response->successful() || $response->status() === 200 || $response->status() === 204) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Try next candidate
            }
        }
        return false;
    }

    /**
     * Get single device detailed parameter tree from GenieACS
     */
    public function getDeviceDetail(string $deviceId, int $tenantId = 1): ?array
    {
        $acsId = $this->resolveAcsDeviceId($deviceId, $tenantId);
        $candidates = $this->getNbiCandidates($tenantId);

        foreach ($candidates as $baseUrl) {
            try {
                $url = $baseUrl . '/devices/' . urlencode($acsId);
                $response = $this->makeHttpClient($tenantId, 3)->get($url);

                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Throwable $e) {
                // Try next candidate
            }
        }

        return null;
    }

    /**
     * Reboot ONT Device via CWMP Connection Request Task
     */
    public function rebootDevice(string $deviceId, int $tenantId = 1): bool
    {
        $acsId = $this->resolveAcsDeviceId($deviceId, $tenantId);
        $candidates = $this->getNbiCandidates($tenantId);

        foreach ($candidates as $baseUrl) {
            try {
                $url = $baseUrl . '/devices/' . urlencode($acsId) . '/tasks?connection_request';
                $response = $this->makeHttpClient($tenantId, 4)->post($url, [
                    'name' => 'reboot'
                ]);

                if ($response->status() === 200 || $response->status() === 202) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Try next candidate
            }
        }

        return false;
    }

    /**
     * Push WiFi SSID and PreSharedKey / Password to ONT
     */
    public function setWifiConfig(string $deviceId, ?string $ssid = null, ?string $password = null, int $tenantId = 1): bool
    {
        $acsId = $this->resolveAcsDeviceId($deviceId, $tenantId);
        $parameterValues = [];

        if (!empty($ssid)) {
            $parameterValues[] = ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID', $ssid, 'xsd:string'];
        }

        if (!empty($password)) {
            $parameterValues[] = [
                'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey',
                $password,
                'xsd:string'
            ];
            $parameterValues[] = [
                'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase',
                $password,
                'xsd:string'
            ];
        }

        if (empty($parameterValues)) {
            return false;
        }

        $candidates = $this->getNbiCandidates($tenantId);

        foreach ($candidates as $baseUrl) {
            try {
                $url = $baseUrl . '/devices/' . urlencode($acsId) . '/tasks?connection_request';
                $response = $this->makeHttpClient($tenantId, 4)->post($url, [
                    'name' => 'setParameterValues',
                    'parameterValues' => $parameterValues,
                ]);

                if ($response->status() === 200 || $response->status() === 202) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Try next candidate
            }
        }

        return false;
    }

    /**
     * Refresh / Re-read all optical & diagnostic parameters
     */
    public function refreshParameters(string $deviceId, int $tenantId = 1): bool
    {
        $acsId = $this->resolveAcsDeviceId($deviceId, $tenantId);
        $candidates = $this->getNbiCandidates($tenantId);

        foreach ($candidates as $baseUrl) {
            try {
                $url = $baseUrl . '/devices/' . urlencode($acsId) . '/tasks?connection_request';
                // Targeted node refresh to prevent too_many_commits fault on XPON ONTs
                $this->makeHttpClient($tenantId, 3)->post($url, [
                    'name' => 'refreshObject',
                    'objectName' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration'
                ]);
                $this->makeHttpClient($tenantId, 3)->post($url, [
                    'name' => 'refreshObject',
                    'objectName' => 'InternetGatewayDevice.WANDevice.1.X_CT-COM_EponInterfaceConfig'
                ]);
                $this->makeHttpClient($tenantId, 3)->post($url, [
                    'name' => 'refreshObject',
                    'objectName' => 'InternetGatewayDevice.WANDevice.1.X_CMCC_EponInterfaceConfig'
                ]);
                $this->makeHttpClient($tenantId, 3)->post($url, [
                    'name' => 'refreshObject',
                    'objectName' => 'InternetGatewayDevice.WANDevice.1.X_CMCC_GponInterfaceConfig'
                ]);
                // TR-181 targeted refresh
                $this->makeHttpClient($tenantId, 3)->post($url, [
                    'name' => 'refreshObject',
                    'objectName' => 'Device.DeviceInfo'
                ]);
                $this->makeHttpClient($tenantId, 3)->post($url, [
                    'name' => 'refreshObject',
                    'objectName' => 'Device.WiFi'
                ]);
                $this->makeHttpClient($tenantId, 3)->post($url, [
                    'name' => 'refreshObject',
                    'objectName' => 'Device.IP'
                ]);
                $this->makeHttpClient($tenantId, 3)->post($url, [
                    'name' => 'refreshObject',
                    'objectName' => 'Device.Optical'
                ]);
                return true;
            } catch (\Throwable $e) {
                // Try next candidate
            }
        }

        return false;
    }

    /**
     * Sync and import newly discovered CPE devices from GenieACS into local Database
     */
    public function syncDevicesFromAcs(int $tenantId): array
    {
        $imported = 0;
        $updated = 0;

        try {
            $setting = AcsTenantSetting::where('tenant_id', $tenantId)->first();
            // Fetch devices matching tenant tag or all uncategorized
            $rawDevices = $this->getDevices($tenantId);

            foreach ($rawDevices as $cpe) {
                $acsDeviceId = $cpe['_id'] ?? null;
                $serial = $this->extractScalar($cpe['DeviceID']['SerialNumber'] ?? null)
                    ?? $this->extractScalar($cpe['_deviceId']['_SerialNumber'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['DeviceInfo']['SerialNumber'] ?? null)
                    ?? $this->extractScalar($cpe['Device']['DeviceInfo']['SerialNumber'] ?? null);

                if (!$serial && $acsDeviceId) {
                    $parts = explode('-', $acsDeviceId);
                    $serial = end($parts);
                }
                $serial = $this->extractScalar($serial);

                if (!$serial) continue;

                // Ignore dummy/external discovery probes
                $acsDevIdUpper = strtoupper($acsDeviceId ?? '');
                $serialUpper = strtoupper($serial);
                $mfgUpper = strtoupper($cpe['_deviceId']['_Manufacturer'] ?? ($cpe['DeviceID']['Manufacturer'] ?? ''));
                if (
                    str_starts_with($serialUpper, 'DISCOVERY') ||
                    str_starts_with($acsDevIdUpper, 'DISCOVERY') ||
                    str_starts_with($mfgUpper, 'DISCOVERY')
                ) {
                    continue;
                }

                $manufacturer = $this->extractScalar($cpe['_deviceId']['_Manufacturer'] ?? null)
                    ?? $this->extractScalar($cpe['DeviceID']['Manufacturer'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['DeviceInfo']['Manufacturer'] ?? null)
                    ?? $this->extractScalar($cpe['Device']['DeviceInfo']['Manufacturer'] ?? null)
                    ?? 'XPON';

                $model = $this->extractScalar($cpe['_deviceId']['_ProductClass'] ?? null)
                    ?? $this->extractScalar($cpe['DeviceID']['ProductClass'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['DeviceInfo']['ModelName'] ?? null)
                    ?? $this->extractScalar($cpe['Device']['DeviceInfo']['ModelName'] ?? null)
                    ?? $this->extractScalar($cpe['Device']['DeviceInfo']['ModelNumber'] ?? null)
                    ?? 'ONT';
                
                // Extract External IP Address (TR-098 & TR-181)
                $ip = $this->extractScalar($cpe['_ip'] ?? null);
                if (!$ip && isset($cpe['InternetGatewayDevice']['ManagementServer']['ConnectionRequestURL'])) {
                    $crUrl = $this->extractScalar($cpe['InternetGatewayDevice']['ManagementServer']['ConnectionRequestURL']);
                    if ($crUrl) {
                        $ip = parse_url($crUrl, PHP_URL_HOST);
                    }
                }
                if (!$ip && isset($cpe['Device']['ManagementServer']['ConnectionRequestURL'])) {
                    $crUrl = $this->extractScalar($cpe['Device']['ManagementServer']['ConnectionRequestURL']);
                    if ($crUrl) {
                        $ip = parse_url($crUrl, PHP_URL_HOST);
                    }
                }
                if (!$ip && isset($cpe['InternetGatewayDevice']['WANDevice']['1']['WANConnectionDevice'])) {
                    if (is_array($cpe['InternetGatewayDevice']['WANDevice']['1']['WANConnectionDevice'])) {
                        foreach ($cpe['InternetGatewayDevice']['WANDevice']['1']['WANConnectionDevice'] as $wanConn) {
                            if (isset($wanConn['WANIPConnection']['1']['ExternalIPAddress'])) {
                                $extIp = $this->extractScalar($wanConn['WANIPConnection']['1']['ExternalIPAddress']);
                                if ($extIp) {
                                    $ip = $extIp;
                                    break;
                                }
                            }
                        }
                    }
                }
                if (!$ip && isset($cpe['Device']['IP']['Interface'])) {
                    if (is_array($cpe['Device']['IP']['Interface'])) {
                        foreach ($cpe['Device']['IP']['Interface'] as $ipIntf) {
                            if (isset($ipIntf['IPv4Address']['1']['IPAddress'])) {
                                $extIp = $this->extractScalar($ipIntf['IPv4Address']['1']['IPAddress']);
                                if ($extIp) {
                                    $ip = $extIp;
                                    break;
                                }
                            }
                        }
                    }
                }
                $ip = $this->extractScalar($ip);

                $macAddress = $this->extractScalar($cpe['InternetGatewayDevice']['LANDevice']['1']['WLANConfiguration']['1']['BSSID'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['WANConnectionDevice']['1']['WANIPConnection']['1']['MACAddress'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['WANConnectionDevice']['1']['WANPPPConnection']['1']['MACAddress'] ?? null)
                    ?? $this->extractScalar($cpe['Device']['WiFi']['SSID']['1']['BSSID'] ?? null)
                    ?? $this->extractScalar($cpe['Device']['Ethernet']['Interface']['1']['MACAddress'] ?? null);

                $hardwareVersion = $this->extractScalar($cpe['InternetGatewayDevice']['DeviceInfo']['HardwareVersion'] ?? null)
                    ?? $this->extractScalar($cpe['Device']['DeviceInfo']['HardwareVersion'] ?? null);

                $softwareVersion = $this->extractScalar($cpe['InternetGatewayDevice']['DeviceInfo']['SoftwareVersion'] ?? null)
                    ?? $this->extractScalar($cpe['Device']['DeviceInfo']['SoftwareVersion'] ?? null);

                $lastInform = isset($cpe['_lastInform']) ? date('Y-m-d H:i:s', strtotime($cpe['_lastInform'])) : now();

                // Extract optical RX/TX Power
                $rxPower = null;
                $txPower = null;

                $rawRx = $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_CT-COM_EponInterfaceConfig']['RXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_CT-COM_GponInterfaceConfig']['RXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_CT-COM_PonInterfaceConfig']['RXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_CMCC_EponInterfaceConfig']['RXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_CMCC_GponInterfaceConfig']['RXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_ZTE-COM_EponInterfaceConfig']['RXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_ZTE-COM_GponInterfaceConfig']['RXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_HW_EponInterfaceConfig']['RXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_HW_GponInterfaceConfig']['RXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_FH_EponInterfaceConfig']['RXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_FH_GponInterfaceConfig']['RXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_FH_PonInterfaceConfig']['RXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_FIBERHOME_EponInterfaceConfig']['RXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_FIBERHOME_GponInterfaceConfig']['RXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_00E0FC_EponInterfaceConfig']['RXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_00E0FC_GponInterfaceConfig']['RXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['WANEponInterfaceConfig']['RxPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['WANGponInterfaceConfig']['RxPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['DeviceInfo']['X_CT-COM_OpticalInfo']['RxPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['DeviceInfo']['X_FH_OpticalInfo']['RxPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['DeviceInfo']['X_HW_OpticalInfo']['RxPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['DeviceInfo']['X_ZTE-COM_OpticalInfo']['RxPower'] ?? null)
                    ?? $this->extractScalar($cpe['Device']['Optical']['Interface']['1']['RxPower'] ?? null)
                    ?? $this->extractScalar($cpe['Device']['Optical']['Interface']['1']['OpticalSignalLevel'] ?? null);

                if ($rawRx !== null && is_numeric($rawRx)) {
                    $val = floatval($rawRx);
                    if ($val < 0) {
                        $rxPower = $val;
                    } elseif ($val > 0 && $val < 500) {
                        // CTC XPON standard: 195 = -19.5 dBm
                        $rxPower = -($val / 10.0);
                    } elseif ($val >= 500 && $val < 100000) {
                        $rxPower = round(10 * log10($val / 10000), 2);
                    }
                }

                $rawTx = $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_CT-COM_EponInterfaceConfig']['TXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_CT-COM_GponInterfaceConfig']['TXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_CT-COM_PonInterfaceConfig']['TXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_CMCC_EponInterfaceConfig']['TXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_CMCC_GponInterfaceConfig']['TXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_ZTE-COM_EponInterfaceConfig']['TXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_ZTE-COM_GponInterfaceConfig']['TXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_HW_EponInterfaceConfig']['TXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_HW_GponInterfaceConfig']['TXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_FH_EponInterfaceConfig']['TXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_FH_GponInterfaceConfig']['TXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_FH_PonInterfaceConfig']['TXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_FIBERHOME_EponInterfaceConfig']['TXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_FIBERHOME_GponInterfaceConfig']['TXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_00E0FC_EponInterfaceConfig']['TXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_00E0FC_GponInterfaceConfig']['TXPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['WANEponInterfaceConfig']['TxPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['WANGponInterfaceConfig']['TxPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['DeviceInfo']['X_CT-COM_OpticalInfo']['TxPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['DeviceInfo']['X_FH_OpticalInfo']['TxPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['DeviceInfo']['X_HW_OpticalInfo']['TxPower'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['DeviceInfo']['X_ZTE-COM_OpticalInfo']['TxPower'] ?? null)
                    ?? $this->extractScalar($cpe['Device']['Optical']['Interface']['1']['TxPower'] ?? null);

                if ($rawTx !== null && is_numeric($rawTx)) {
                    $val = floatval($rawTx);
                    if ($val > 1000) {
                        $txPower = round(10 * log10($val / 10000), 2);
                    } elseif ($val > 0 && $val <= 1000) {
                        $txPower = round($val / 10.0, 2);
                    } else {
                        $txPower = $val;
                    }
                }

                // Extract SSID & Password (TR-098 & TR-181)
                $ssid = $this->extractScalar($cpe['InternetGatewayDevice']['LANDevice']['1']['WLANConfiguration']['1']['SSID'] ?? null)
                    ?? $this->extractScalar($cpe['Device']['WiFi']['SSID']['1']['SSID'] ?? null);
                
                $wifiPassword = $this->extractScalar($cpe['InternetGatewayDevice']['LANDevice']['1']['WLANConfiguration']['1']['KeyPassphrase'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['LANDevice']['1']['WLANConfiguration']['1']['PreSharedKey']['1']['KeyPassphrase'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['LANDevice']['1']['WLANConfiguration']['1']['PreSharedKey']['1']['PreSharedKey'] ?? null)
                    ?? $this->extractScalar($cpe['Device']['WiFi']['AccessPoint']['1']['Security']['KeyPassphrase'] ?? null)
                    ?? $this->extractScalar($cpe['Device']['WiFi']['AccessPoint']['1']['Security']['PreSharedKey'] ?? null);

                // Extract Connected LAN/WLAN Hosts & Hostnames
                $connectedHosts = [];
                $customNames = [];

                // 1. Build MAC -> Hostname dictionary from vendor customisation nodes
                $cmccCustom = $cpe['InternetGatewayDevice']['LANDevice']['1']['X_CMCC_HostCustomise'] ?? [];
                if (is_array($cmccCustom)) {
                    foreach ($cmccCustom as $cItem) {
                        if (!is_array($cItem)) continue;
                        $cMac = strtoupper(trim((string)$this->extractScalar($cItem['MACAddress'] ?? null, '')));
                        $cName = trim((string)$this->extractScalar($cItem['Name'] ?? null, ''));
                        if (!empty($cMac) && !empty($cName)) {
                            $customNames[$cMac] = $cName;
                        }
                    }
                }

                $seenMacs = [];

                // 2. Extract from TR-098 LANDevice Hosts.Host or TR-181 Device.Hosts.Host
                $hostTree = $cpe['InternetGatewayDevice']['LANDevice']['1']['Hosts']['Host'] 
                    ?? ($cpe['Device']['Hosts']['Host'] ?? []);

                if (is_array($hostTree)) {
                    foreach ($hostTree as $hostKey => $hostData) {
                        if (!is_array($hostData)) continue;
                        $hMac = strtoupper(trim((string)$this->extractScalar($hostData['MACAddress'] ?? null, '-')));
                        $hIp = trim((string)$this->extractScalar($hostData['IPAddress'] ?? null, '-'));
                        $hName = trim((string)$this->extractScalar($hostData['HostName'] ?? null, ''));
                        $hActiveRaw = $this->extractScalar($hostData['Active'] ?? null, '0');
                        $hActive = filter_var($hActiveRaw, FILTER_VALIDATE_BOOLEAN);
                        $hInterface = $this->extractScalar($hostData['InterfaceType'] ?? null, '802.11');
                        $hLeaseRaw = $this->extractScalar($hostData['LeaseTimeRemaining'] ?? null);
                        $hLease = ($hLeaseRaw !== null && is_numeric($hLeaseRaw)) ? (int)$hLeaseRaw : null;

                        if (!empty($hMac) && $hMac !== '-') {
                            if (isset($customNames[$hMac])) {
                                $hName = $customNames[$hMac];
                            }
                        }

                        if (($hIp !== '-' && $hIp !== '') || ($hMac !== '-' && $hMac !== '')) {
                            $normMac = ($hMac !== '-' && $hMac !== '') ? $hMac : $hIp;
                            $seenMacs[$normMac] = true;
                            $connectedHosts[] = [
                                'hostname' => $hName ?: 'Perangkat-WiFi',
                                'ip_address' => $hIp ?: '-',
                                'mac_address' => $hMac ?: '-',
                                'is_active' => $hActive,
                                'interface_type' => $hInterface ?: '802.11',
                                'lease_time' => $hLease,
                            ];
                        }
                    }
                }

                // 3. Fallback: Extract live associated WiFi clients from WLANConfiguration.*.AssociatedDevice.*
                $wlanConfigs = $cpe['InternetGatewayDevice']['LANDevice']['1']['WLANConfiguration'] 
                    ?? ($cpe['Device']['WiFi']['AccessPoint'] ?? []);
                if (is_array($wlanConfigs)) {
                    foreach ($wlanConfigs as $wlanKey => $wlanData) {
                        if (!is_array($wlanData) || !isset($wlanData['AssociatedDevice']) || !is_array($wlanData['AssociatedDevice'])) {
                            continue;
                        }
                        foreach ($wlanData['AssociatedDevice'] as $assocKey => $assocData) {
                            if (!is_array($assocData)) continue;
                            $aMac = strtoupper(trim((string)$this->extractScalar(
                                $assocData['AssociatedDeviceMACAddress'] 
                                ?? ($assocData['MACAddress'] ?? null), 
                                '-'
                            )));
                            $aIp = trim((string)$this->extractScalar(
                                $assocData['AssociatedDeviceIPAddress'] 
                                ?? ($assocData['IPAddress'] ?? null), 
                                '-'
                            ));
                            $aAuthState = $this->extractScalar($assocData['AssociatedDeviceAuthenticationState'] ?? null);
                            $aActive = $aAuthState !== null ? filter_var($aAuthState, FILTER_VALIDATE_BOOLEAN) : true;

                            if (($aIp !== '-' && $aIp !== '') || ($aMac !== '-' && $aMac !== '')) {
                                $normMac = ($aMac !== '-' && $aMac !== '') ? $aMac : $aIp;
                                if (!isset($seenMacs[$normMac])) {
                                    $seenMacs[$normMac] = true;
                                    $aName = isset($customNames[$aMac]) ? $customNames[$aMac] : 'Perangkat-WiFi';
                                    $connectedHosts[] = [
                                        'hostname' => $aName,
                                        'ip_address' => $aIp ?: '-',
                                        'mac_address' => $aMac ?: '-',
                                        'is_active' => $aActive,
                                        'interface_type' => '802.11',
                                        'lease_time' => null,
                                    ];
                                }
                            }
                        }
                    }
                }

                $rawVolt = $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_CT-COM_EponInterfaceConfig']['SupplyVottage'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_CMCC_EponInterfaceConfig']['SupplyVottage'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_CMCC_GponInterfaceConfig']['SupplyVottage'] ?? null);
                $voltage = ($rawVolt !== null && is_numeric($rawVolt)) ? round(floatval($rawVolt) / 10000.0, 2) : null;

                $rawTemp = $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_CT-COM_EponInterfaceConfig']['TransceiverTemperature'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_CMCC_EponInterfaceConfig']['TransceiverTemperature'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_CMCC_GponInterfaceConfig']['TransceiverTemperature'] ?? null);
                $temperature = ($rawTemp !== null && is_numeric($rawTemp)) ? round(floatval($rawTemp) / 256.0, 1) : null;

                $rawBias = $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_CT-COM_EponInterfaceConfig']['BiasCurrent'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_CMCC_EponInterfaceConfig']['BiasCurrent'] ?? null)
                    ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['X_CMCC_GponInterfaceConfig']['BiasCurrent'] ?? null);
                $biasCurrent = ($rawBias !== null && is_numeric($rawBias)) ? round(floatval($rawBias) / 1000.0, 2) : null;

                $rawParams = [
                    'acs_device_id' => $acsDeviceId,
                    'hosts' => $connectedHosts,
                    'wan' => [
                        'external_ip' => $ip,
                        'service_list' => $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['WANConnectionDevice']['5']['WANIPConnection']['1']['X_CT-COM_ServiceList'] ?? null),
                        'subnet_mask' => $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['WANConnectionDevice']['5']['WANIPConnection']['1']['SubnetMask'] ?? null),
                        'access_type' => $this->extractScalar($cpe['InternetGatewayDevice']['DeviceInfo']['AccessType'] ?? null)
                            ?? $this->extractScalar($cpe['InternetGatewayDevice']['WANDevice']['1']['WANCommonInterfaceConfig']['WANAccessType'] ?? null)
                            ?? 'EPON/GPON',
                        'uptime_seconds' => $this->extractNumeric($cpe['InternetGatewayDevice']['DeviceInfo']['UpTime'] ?? null),
                    ],
                    'optical' => [
                        'rx_power' => $rxPower,
                        'tx_power' => $txPower,
                        'voltage' => $voltage,
                        'temperature' => $temperature,
                        'bias_current' => $biasCurrent,
                    ],
                    'wifi' => [
                        'ssid' => $ssid,
                        'password' => $wifiPassword,
                        'channel' => (int) ($this->extractNumeric($cpe['InternetGatewayDevice']['LANDevice']['1']['WLANConfiguration']['1']['Channel'] ?? null, 6) ?: 6),
                        'security' => $this->extractScalar($cpe['InternetGatewayDevice']['LANDevice']['1']['WLANConfiguration']['1']['BeaconType'] ?? null, 'WPA2-PSK'),
                        'enabled' => filter_var($this->extractScalar($cpe['InternetGatewayDevice']['LANDevice']['1']['WLANConfiguration']['1']['Enable'] ?? null, 'true'), FILTER_VALIDATE_BOOLEAN),
                    ]
                ];

                $activeHostsCount = count(array_filter($connectedHosts, fn($h) => $h['is_active']));

                // Auto tag in GenieACS if untagged (Only in Cloud Managed mode)
                if ($setting && $setting->connection_mode !== 'self_hosted') {
                    $tenantTag = !empty($setting->acs_username) ? $setting->acs_username : 'tenant_' . $tenantId;
                    $currentTags = $cpe['_tags'] ?? [];
                    if (!in_array($tenantTag, $currentTags)) {
                        $this->tagDevice($acsDeviceId ?? $serial, $tenantTag, $tenantId);
                    }
                }

                // Auto-queue refreshObject task if device has no parameters yet (skip discovery probes)
                if (!isset($cpe['InternetGatewayDevice']) && !isset($cpe['Device'])) {
                    if (!empty($serial) && !str_starts_with(strtoupper($serial), 'DISCOVERY') && !str_starts_with(strtoupper($acsDeviceId ?? ''), 'DISCOVERY')) {
                        $this->refreshParameters($acsDeviceId ?? $serial, $tenantId);
                    }
                }

                $ont = OntDevice::where('tenant_id', $tenantId)
                    ->where(function ($q) use ($serial, $acsDeviceId) {
                        $q->where('serial_number', $serial);
                        if ($acsDeviceId) {
                            $q->orWhere('serial_number', $acsDeviceId);
                        }
                    })
                    ->first();

                // Auto-match customer by IP (ARP/Static IP) or MAC address if not yet connected
                $matchedCustomerId = $ont?->customer_id;
                if (!$matchedCustomerId) {
                    if (!empty($ip)) {
                        $matchedCust = Customer::where('tenant_id', $tenantId)
                            ->where('ip_address', trim($ip))
                            ->first();
                        if ($matchedCust) {
                            $matchedCustomerId = $matchedCust->id;
                        }
                    }
                    if (!$matchedCustomerId && !empty($macAddress)) {
                        $cleanMac = strtolower(str_replace([':', '-', '.'], '', trim($macAddress)));
                        $matchedCust = Customer::where('tenant_id', $tenantId)
                            ->whereRaw("LOWER(REPLACE(REPLACE(REPLACE(mac_address, ':', ''), '-', ''), '.', '')) = ?", [$cleanMac])
                            ->first();
                        if ($matchedCust) {
                            $matchedCustomerId = $matchedCust->id;
                        }
                    }
                }

                if ($ont) {
                    $ont->update([
                        'serial_number' => $serial,
                        'manufacturer' => $manufacturer,
                        'model_name' => $model,
                        'hardware_version' => $hardwareVersion ?? $ont->hardware_version,
                        'software_version' => $softwareVersion ?? $ont->software_version,
                        'ip_address' => $ip ?? $ont->ip_address,
                        'mac_address' => $macAddress ?? $ont->mac_address,
                        'rx_power' => $rxPower ?? $ont->rx_power,
                        'tx_power' => $txPower ?? $ont->tx_power,
                        'optical_voltage' => $voltage ?? $ont->optical_voltage,
                        'optical_temp' => $temperature ?? $ont->optical_temp,
                        'wifi_ssid' => $ssid ?? $ont->wifi_ssid,
                        'wifi_password' => $wifiPassword ?? $ont->wifi_password,
                        'connected_devices_count' => $activeHostsCount > 0 ? $activeHostsCount : count($connectedHosts),
                        'raw_parameters' => $rawParams,
                        'last_inform_at' => $lastInform,
                        'customer_id' => $matchedCustomerId,
                        'status' => 'ONLINE',
                    ]);
                    $updated++;
                } else {
                    OntDevice::create([
                        'tenant_id' => $tenantId,
                        'serial_number' => $serial,
                        'manufacturer' => $manufacturer,
                        'model_name' => $model,
                        'hardware_version' => $hardwareVersion,
                        'software_version' => $softwareVersion,
                        'ip_address' => $ip,
                        'mac_address' => $macAddress,
                        'rx_power' => $rxPower,
                        'tx_power' => $txPower,
                        'optical_voltage' => $voltage,
                        'optical_temp' => $temperature,
                        'wifi_ssid' => $ssid,
                        'wifi_password' => $wifiPassword,
                        'connected_devices_count' => $activeHostsCount > 0 ? $activeHostsCount : count($connectedHosts),
                        'raw_parameters' => $rawParams,
                        'status' => 'ONLINE',
                        'customer_id' => $matchedCustomerId,
                        'last_inform_at' => $lastInform,
                        'registered_at' => now(),
                    ]);
                    $imported++;
                }
            }
        } catch (\Exception $e) {
            Log::error("GenieACS sync error: " . $e->getMessage());
        }

        return ['imported' => $imported, 'updated' => $updated];
    }
}