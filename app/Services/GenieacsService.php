<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GenieacsService — HTTP client wrapper for GenieACS TR-069 REST API.
 */
class GenieacsService
{
    private ConfigService $config;
    private string $cacheDir;
    private int $cacheExpiry = 15; // seconds

    public function __construct()
    {
        $this->config = new ConfigService();
        $this->cacheDir = storage_path('framework/cache/genieacs/');
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    public function getBaseUrl(): string
    {
        $url = \App\Models\Setting::getValue('GENIEACS_URL')
            ?: ($this->config->get('GENIEACS_URL', config('app.genieacs_url', '')));
        return rtrim(trim((string) $url), '/');
    }

    public function getAuthHeader(): string
    {
        $username = \App\Models\Setting::getValue('GENIEACS_USERNAME')
            ?: $this->config->get('GENIEACS_USERNAME', config('app.genieacs_username'));
        $password = \App\Models\Setting::getValue('GENIEACS_PASSWORD')
            ?: $this->config->get('GENIEACS_PASSWORD', config('app.genieacs_password'));
        $token = \App\Models\Setting::getValue('GENIEACS_TOKEN')
            ?: $this->config->get('GENIEACS_TOKEN', config('app.genieacs_token'));

        if (!empty($username) && !empty($password)) {
            return 'Basic ' . base64_encode("{$username}:{$password}");
        }
        if (!empty($token)) {
            return 'Bearer ' . $token;
        }
        return '';
    }

    /**
     * Check if GenieACS URL is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->getBaseUrl());
    }

    /**
     * Get list of devices with clean summaries
     */
    public function getDevices(bool $useCache = true): array
    {
        $cacheKey = 'devices_list';

        if ($useCache) {
            $cached = $this->getCache($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $projection = $this->getProjectionFields();
        $result = $this->request('GET', '/devices', ['projection' => $projection]);

        if (isset($result['code']) && $result['code'] === 200 && is_array($result['body'])) {
            $summarized = array_map([$this, 'extractDeviceSummary'], $result['body']);
            $result['summaries'] = $summarized;
            $this->setCache($cacheKey, $result);
        }

        return $result;
    }

    /**
     * Find a device by serial number
     */
    public function getDevice(string $serial, bool $useCache = false): array
    {
        $cacheKey = 'device_' . md5($serial);

        if ($useCache) {
            $cached = $this->getCache($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $projection = $this->getProjectionFields();

        $query = json_encode([
            '$or' => [
                ['_id' => ['$regex' => $serial, '$options' => 'i']],
                ['_deviceId._SerialNumber' => ['$regex' => $serial, '$options' => 'i']],
                ['VirtualParameters.pppoeUsername' => ['$regex' => $serial, '$options' => 'i']],
            ],
        ]);

        $result = $this->request('GET', '/devices', ['query' => $query, 'projection' => $projection]);
        $device = $result['body'][0] ?? [];

        if (!empty($device)) {
            $device['summary'] = $this->extractDeviceSummary($device);
            $this->setCache($cacheKey, $device);
        }

        return $device;
    }

    /**
     * Extract structured device summary matching field-tested TR-069 parameters
     */
    public function extractDeviceSummary(array $d): array
    {
        $deviceId = $d['_id'] ?? '';
        $rawSerial = $this->getParameterValue($d, '_deviceId._SerialNumber')
            ?: ($this->getParameterValue($d, 'InternetGatewayDevice.DeviceInfo.SerialNumber')
            ?: ($this->getParameterValue($d, 'VirtualParameters.getSerialNumber') ?: $deviceId));

        $manufacturer = $this->getParameterValue($d, '_deviceId._Manufacturer')
            ?: ($this->getParameterValue($d, 'InternetGatewayDevice.DeviceInfo.Manufacturer') ?: '-');

        $model = $this->getParameterValue($d, '_deviceId._ProductClass')
            ?: ($this->getParameterValue($d, 'InternetGatewayDevice.DeviceInfo.ModelName')
            ?: ($this->getParameterValue($d, 'InternetGatewayDevice.DeviceInfo.ProductClass') ?: '-'));

        $rxPower = $this->getParameterValue($d, 'VirtualParameters.RXPower')
            ?: ($this->getParameterValue($d, 'VirtualParameters.rxPower')
            ?: ($this->getParameterValue($d, 'VirtualParameters.pon_rx')
            ?: ($this->getParameterValue($d, 'InternetGatewayDevice.WANDevice.1.WANPONInterfaceConfig.RXPower')
            ?: ($this->getParameterValue($d, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.X_ALU-COM_RxPower')
            ?: ($this->getParameterValue($d, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.X_CMCC_RxPower')
            ?: ($this->getParameterValue($d, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.X_CT-COM_RxPower')
            ?: ($this->getParameterValue($d, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.X_CU_RxPower')
            ?: ($this->getParameterValue($d, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.X_FH_RxPower')
            ?: ($this->getParameterValue($d, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.X_ZTE-COM_RxPower')
            ?: ($this->getParameterValue($d, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.X_CT-COM_RxPower')
            ?: ($this->getParameterValue($d, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.X_CMCC_RxPower')
            ?: $this->getParameterValue($d, 'Device.Optical.Interface.1.RxPower'))))))))))));

        $pppoeUser = $this->getParameterValue($d, 'VirtualParameters.pppoeUsername')
            ?: ($this->getParameterValue($d, 'VirtualParameters.pppoeUsername2')
            ?: ($this->getParameterValue($d, 'VirtualParameters.pppoe_user')
            ?: ($this->getParameterValue($d, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username')
            ?: ($this->getParameterValue($d, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANPPPConnection.1.Username')
            ?: ($this->getParameterValue($d, 'Device.PPP.Interface.1.Username')
            ?: $this->getParameterValue($d, 'VirtualParameters.pppoeUsername2'))))));

        $pppoeIp = $this->getParameterValue($d, 'VirtualParameters.pppoeIP')
            ?: ($this->getParameterValue($d, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.ExternalIPAddress')
            ?: ($this->getParameterValue($d, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANPPPConnection.1.ExternalIPAddress')
            ?: ($this->getParameterValue($d, 'Device.PPP.Interface.1.IPCP.LocalIPAddress')
            ?: $this->getParameterValue($d, 'VirtualParameters.IPTR069'))));

        $uptime = $this->getParameterValue($d, 'VirtualParameters.getdeviceuptime')
            ?: ($this->getParameterValue($d, 'VirtualParameters.uptime')
            ?: ($this->getParameterValue($d, 'InternetGatewayDevice.DeviceInfo.UpTime')
            ?: $this->getParameterValue($d, 'Device.DeviceInfo.UpTime')));

        $temp = $this->getParameterValue($d, 'VirtualParameters.gettemp')
            ?: ($this->getParameterValue($d, 'VirtualParameters.temp')
            ?: ($this->getParameterValue($d, 'InternetGatewayDevice.DeviceInfo.TemperatureStatus.TemperatureValue')
            ?: $this->getParameterValue($d, 'Device.DeviceInfo.TemperatureStatus.TemperatureSensor.1.Value')));

        $ssid = $this->getParameterValue($d, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID')
            ?: ($this->getParameterValue($d, 'Device.WiFi.SSID.1.SSID')
            ?: $this->getParameterValue($d, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.SSID'));

        $wifiClients = $this->getParameterValue($d, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.TotalAssociations')
            ?: ($this->getParameterValue($d, 'VirtualParameters.activedevices')
            ?: ($this->getParameterValue($d, 'VirtualParameters.useraktif')
            ?: $this->getParameterValue($d, 'Device.WiFi.AccessPoint.1.AssociatedDeviceNumberOfEntries')));

        $ponMode = $this->getParameterValue($d, 'VirtualParameters.getponmode') ?: 'XPON';

        $lastInform = $d['_lastInform'] ?? null;
        $isOnline = false;
        if ($lastInform) {
            $timeDiff = time() - (is_string($lastInform) ? strtotime($lastInform) : (int) ($lastInform / 1000));
            $isOnline = $timeDiff < 300; // within 5 mins
        }

        return [
            'id' => $deviceId,
            'serial_number' => $rawSerial,
            'manufacturer' => $manufacturer,
            'model' => $model,
            'rx_power' => $rxPower ? round((float) $rxPower, 2) : null,
            'pppoe_username' => $pppoeUser,
            'pppoe_ip' => $pppoeIp,
            'uptime' => $uptime,
            'temperature' => $temp ? (float) $temp : null,
            'ssid' => $ssid,
            'wifi_clients' => is_numeric($wifiClients) ? (int) $wifiClients : null,
            'pon_mode' => $ponMode,
            'online' => $isOnline,
            'last_inform' => $lastInform,
            'tags' => $d['tags'] ?? [],
        ];
    }

    /**
     * Reboot a device
     */
    public function rebootDevice(string $serial): array
    {
        $device = $this->getDevice($serial, false);
        $deviceId = $device['_id'] ?? $serial;
        $encoded = urlencode($deviceId);
        return $this->request('POST', "/devices/{$encoded}/tasks?connection_request", ['name' => 'reboot']);
    }

    /**
     * Factory reset a device
     */
    public function factoryReset(string $serial): array
    {
        $device = $this->getDevice($serial, false);
        $deviceId = $device['_id'] ?? $serial;
        $encoded = urlencode($deviceId);
        return $this->request('POST', "/devices/{$encoded}/tasks?connection_request", ['name' => 'factoryReset']);
    }

    /**
     * Refresh / synchronize parameters from device
     */
    public function refreshParameters(string $serial): array
    {
        $device = $this->getDevice($serial, false);
        $deviceId = $device['_id'] ?? $serial;
        $encoded = urlencode($deviceId);
        return $this->request('POST', "/devices/{$encoded}/tasks?connection_request", [
            'name' => 'refreshObject',
            'objectName' => '',
        ]);
    }

    /**
     * Sanitize SSID string (max 32 chars, strip control chars)
     */
    public static function sanitizeSsid(string $ssid): string
    {
        return substr(preg_replace('/[\x00-\x1F\x7F]/', '', trim($ssid)), 0, 32);
    }

    /**
     * Sanitize Wi-Fi password (strip control chars, max 63 chars)
     */
    public static function sanitizeWifiPassword(string $password): string
    {
        return substr(preg_replace('/[\x00-\x1F\x7F\r\n]/', '', trim($password)), 0, 63);
    }

    /**
     * Sanitize PPPoE credentials
     */
    public static function sanitizePppoeParam(string $val): string
    {
        return substr(preg_replace('/[\x00-\x20\x7F"\';`$\|\\\\]/', '', trim($val)), 0, 64);
    }

    /**
     * Set Wi-Fi SSID and Password
     */
    public function setWifi(string $serial, string $ssid, string $password): array
    {
        $this->clearDeviceCache($serial);
        $device = $this->getDevice($serial, false);
        if (empty($device) || !isset($device['_id'])) {
            return ['code' => 404, 'error' => "Device {$serial} tidak ditemukan di GenieACS"];
        }

        $deviceId = $device['_id'];
        $parameterValues = [];

        $cleanSsid = self::sanitizeSsid($ssid);
        $cleanPass = self::sanitizeWifiPassword($password);

        if (!empty($cleanSsid)) {
            $parameterValues[] = ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID', $cleanSsid, 'xsd:string'];
        }
        if (!empty($cleanPass)) {
            $parameterValues[] = ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase', $cleanPass, 'xsd:string'];
        }

        $task = [
            'name' => 'setParameterValues',
            'parameterValues' => $parameterValues,
        ];

        $encoded = urlencode($deviceId);
        return $this->request('POST', "/devices/{$encoded}/tasks?connection_request", $task);
    }

    /**
     * Set PPPoE Account
     */
    public function setPppoe(string $serial, string $username, string $password): array
    {
        $this->clearDeviceCache($serial);
        $device = $this->getDevice($serial, false);
        if (empty($device) || !isset($device['_id'])) {
            return ['code' => 404, 'error' => "Device {$serial} tidak ditemukan di GenieACS"];
        }

        $deviceId = $device['_id'];
        $cleanUser = self::sanitizePppoeParam($username);
        $cleanPass = self::sanitizePppoeParam($password);

        $parameterValues = [
            ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username', $cleanUser, 'xsd:string'],
            ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Password', $cleanPass, 'xsd:string'],
        ];

        $task = [
            'name' => 'setParameterValues',
            'parameterValues' => $parameterValues,
        ];

        $encoded = urlencode($deviceId);
        return $this->request('POST', "/devices/{$encoded}/tasks?connection_request", $task);
    }

    /**
     * Delete device from GenieACS
     */
    public function deleteDevice(string $serial): array
    {
        $this->clearDeviceCache($serial);
        $device = $this->getDevice($serial, false);
        $deviceId = $device['_id'] ?? $serial;
        $encoded = urlencode($deviceId);
        return $this->request('DELETE', "/devices/{$encoded}");
    }
    /**
     * Set a single parameter value on device
     */
    public function setParameter(string $serial, string $parameter, mixed $value, string $type = 'xsd:string'): array
    {
        $this->clearDeviceCache($serial);
        $device = $this->getDevice($serial, false);
        if (empty($device) || !isset($device['_id'])) {
            return ['code' => 404, 'error' => "Device {$serial} tidak ditemukan di GenieACS"];
        }

        $deviceId = $device['_id'];
        $task = [
            'name' => 'setParameterValues',
            'parameterValues' => [
                [$parameter, (string) $value, $type],
            ],
        ];

        $encoded = urlencode($deviceId);
        return $this->request('POST', "/devices/{$encoded}/tasks?connection_request", $task);
    }

    /**
     * Find a device by PPPoE Username
     */
    public function getDeviceByPppoeUsername(string $username): ?array
    {
        if (empty($username)) {
            return null;
        }

        $device = $this->getDevice($username, false);
        return !empty($device) ? $device : null;
    }

    /**
     * Clear all GenieACS local caches
     */
    public function clearCache(): void
    {
        if (is_dir($this->cacheDir)) {
            $files = glob($this->cacheDir . '*.cache');
            if ($files) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
        }
    }

    public function clearDeviceCache(?string $serial = null): void
    {
        if ($serial) {
            @unlink($this->cacheDir . md5('device_' . md5($serial)) . '.cache');
        }
        @unlink($this->cacheDir . md5('devices_list') . '.cache');
    }

    private function getCache(string $key): ?array
    {
        $file = $this->cacheDir . md5($key) . '.cache';
        if (!file_exists($file)) {
            return null;
        }

        $data = json_decode(file_get_contents($file), true);
        if (!$data || !isset($data['expires']) || $data['expires'] < time()) {
            @unlink($file);
            return null;
        }

        return $data['value'];
    }

    private function setCache(string $key, array $value): void
    {
        $file = $this->cacheDir . md5($key) . '.cache';
        $data = [
            'expires' => time() + $this->cacheExpiry,
            'value' => $value,
        ];
        file_put_contents($file, json_encode($data));
    }

    public function request(string $method, string $path, mixed $data = []): array
    {
        $baseUrl = $this->getBaseUrl();
        if ($baseUrl === '') {
            return ['code' => 0, 'body' => [], 'error' => 'GenieACS belum dikonfigurasi (URL kosong)'];
        }

        $authHeader = $this->getAuthHeader();
        $urlsToTry = [$baseUrl . $path];

        // If baseUrl uses localhost/127.0.0.1 and we are in Docker, add docker host fallbacks
        if (str_contains($baseUrl, 'localhost') || str_contains($baseUrl, '127.0.0.1')) {
            $urlsToTry[] = str_replace(['localhost', '127.0.0.1'], '172.17.0.1', $baseUrl) . $path;
            $urlsToTry[] = str_replace(['localhost', '127.0.0.1'], 'host.docker.internal', $baseUrl) . $path;
        }

        // Also try /api prefix fallback if path starts with /devices
        if (str_starts_with($path, '/devices')) {
            $apiPath = '/api' . $path;
            $urlsToTry[] = $baseUrl . $apiPath;
        }

        $lastError = null;
        foreach ($urlsToTry as $url) {
            try {
                $http = Http::withoutVerifying()
                    ->timeout(12)
                    ->connectTimeout(4)
                    ->withHeaders(['Content-Type' => 'application/json', 'Accept' => 'application/json']);

                if ($authHeader) {
                    $http->withHeader('Authorization', $authHeader);
                }

                $response = $method === 'GET'
                    ? $http->get($url, $data)
                    : ($method === 'DELETE'
                        ? $http->delete($url)
                        : $http->withBody(json_encode($data), 'application/json')->send($method, $url));

                if ($response->status() >= 200 && $response->status() < 400) {
                    return [
                        'code' => $response->status(),
                        'body' => $response->json() ?? [],
                        'error' => null,
                    ];
                }

                if ($response->status() === 404 && count($urlsToTry) > 1) {
                    continue;
                }

                return [
                    'code' => $response->status(),
                    'body' => $response->json() ?? [],
                    'error' => "GenieACS HTTP {$response->status()}",
                ];
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        Log::error("GenieACS request failed all candidates: " . $lastError);
        return [
            'code' => 0,
            'body' => null,
            'error' => $lastError ?: 'Gagal terhubung ke server GenieACS',
        ];
    }

    private function getProjectionFields(): string
    {
        return implode(',', [
            '_deviceId._SerialNumber',
            '_deviceId._ProductClass',
            '_deviceId._Manufacturer',
            '_registered',
            '_lastInform',
            'VirtualParameters.pppoeUsername',
            'VirtualParameters.pppoeUsername2',
            'VirtualParameters.pppoe_user',
            'VirtualParameters.gettemp',
            'VirtualParameters.temp',
            'VirtualParameters.RXPower',
            'VirtualParameters.rxPower',
            'VirtualParameters.pon_rx',
            'VirtualParameters.pppoeIP',
            'VirtualParameters.IPTR069',
            'VirtualParameters.getponmode',
            'VirtualParameters.getdeviceuptime',
            'VirtualParameters.uptime',
            'VirtualParameters.getSerialNumber',
            'VirtualParameters.activedevices',
            'VirtualParameters.useraktif',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.TotalAssociations',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.SSID',
            'InternetGatewayDevice.DeviceInfo.SerialNumber',
            'InternetGatewayDevice.DeviceInfo.ModelName',
            'InternetGatewayDevice.DeviceInfo.Manufacturer',
            'InternetGatewayDevice.DeviceInfo.UpTime',
            'InternetGatewayDevice.DeviceInfo.TemperatureStatus.TemperatureValue',
            'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username',
            'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.ExternalIPAddress',
            'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.X_ALU-COM_RxPower',
            'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.X_CMCC_RxPower',
            'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.X_CT-COM_RxPower',
            'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.X_CU_RxPower',
            'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.X_FH_RxPower',
            'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.X_ZTE-COM_RxPower',
            'InternetGatewayDevice.WANDevice.1.WANPONInterfaceConfig.RXPower',
            'Device.Optical.Interface.1.RxPower',
            'Device.PPP.Interface.1.Username',
            'Device.WiFi.SSID.1.SSID',
            'tags',
        ]);
    }

    private function getParameterValue(array $device, string $path): ?string
    {
        $parts = explode('.', $path);
        $current = $device;

        foreach ($parts as $part) {
            if (!isset($current[$part])) {
                return null;
            }
            $current = $current[$part];
        }

        if (is_array($current) && isset($current['_value'])) {
            return (string) $current['_value'];
        }

        if (is_string($current) || is_numeric($current)) {
            return (string) $current;
        }

        return null;
    }
}
