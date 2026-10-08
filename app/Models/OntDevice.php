<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\TenantAware;

class OntDevice extends Model
{
    use HasFactory, TenantAware;

    protected $table = 'ont_devices';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'serial_number',
        'manufacturer',
        'model_name',
        'hardware_version',
        'software_version',
        'ip_address',
        'mac_address',
        'rx_power',
        'tx_power',
        'optical_voltage',
        'optical_temp',
        'wifi_ssid',
        'wifi_password',
        'wifi_channel',
        'wifi_enabled',
        'connected_devices_count',
        'status',
        'last_inform_at',
        'registered_at',
        'raw_parameters',
    ];

    protected $casts = [
        'rx_power' => 'float',
        'tx_power' => 'float',
        'optical_voltage' => 'float',
        'optical_temp' => 'float',
        'wifi_enabled' => 'boolean',
        'connected_devices_count' => 'integer',
        'last_inform_at' => 'datetime',
        'registered_at' => 'datetime',
        'raw_parameters' => 'array',
    ];

    /**
     * Relationship to Customer (PPPoE / Hotspot subscriber)
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Optical Health Status Helper
     */
    public function getOpticalStatusAttribute(): string
    {
        if (is_null($this->rx_power)) {
            return 'UNKNOWN';
        }
        if ($this->rx_power >= -23.0 && $this->rx_power <= -12.0) {
            return 'EXCELLENT'; // 🟢
        }
        if ($this->rx_power >= -27.0 && $this->rx_power < -23.0) {
            return 'WARNING'; // 🟡
        }
        return 'CRITICAL'; // 🔴 Redaman drop / putus
    }

    protected function cleanScalar(mixed $val, ?string $fallback = null): ?string
    {
        if ($val === null) return $fallback;
        if (is_scalar($val)) {
            $s = trim((string) $val);
            return $s !== '' ? $s : $fallback;
        }
        if (is_array($val)) {
            if (isset($val['_value']) && is_scalar($val['_value'])) {
                $s = trim((string) $val['_value']);
                return $s !== '' ? $s : $fallback;
            }
            if (isset($val['value']) && is_scalar($val['value'])) {
                $s = trim((string) $val['value']);
                return $s !== '' ? $s : $fallback;
            }
        }
        return $fallback;
    }

    public function getManufacturerAttribute($value): string
    {
        return $this->cleanScalar($value, 'XPON') ?? 'XPON';
    }

    public function getModelNameAttribute($value): string
    {
        return $this->cleanScalar($value, 'ONT') ?? 'ONT';
    }

    public function getSerialNumberAttribute($value): string
    {
        return $this->cleanScalar($value, '') ?? '';
    }

    public function getHardwareVersionAttribute($value): ?string
    {
        return $this->cleanScalar($value);
    }

    public function getSoftwareVersionAttribute($value): ?string
    {
        return $this->cleanScalar($value);
    }

    public function getIpAddressAttribute($value): ?string
    {
        return $this->cleanScalar($value);
    }

    public function getMacAddressAttribute($value): ?string
    {
        return $this->cleanScalar($value);
    }

    public function getWifiSsidAttribute($value): ?string
    {
        return $this->cleanScalar($value);
    }

    public function getWifiPasswordAttribute($value): ?string
    {
        return $this->cleanScalar($value);
    }

    public function getRawParametersAttribute($value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        if (!is_array($decoded)) {
            return [];
        }

        if (isset($decoded['hosts']) && is_array($decoded['hosts'])) {
            $cleanedHosts = [];
            foreach ($decoded['hosts'] as $h) {
                if (!is_array($h)) continue;
                $cleanedHosts[] = [
                    'hostname' => $this->cleanScalar($h['hostname'] ?? null, 'Perangkat-WiFi') ?? 'Perangkat-WiFi',
                    'ip_address' => $this->cleanScalar($h['ip_address'] ?? null, '-') ?? '-',
                    'mac_address' => $this->cleanScalar($h['mac_address'] ?? null, '-') ?? '-',
                    'is_active' => filter_var($this->cleanScalar($h['is_active'] ?? null, '0'), FILTER_VALIDATE_BOOLEAN),
                    'interface_type' => $this->cleanScalar($h['interface_type'] ?? null, '802.11') ?? '802.11',
                    'lease_time' => is_numeric($h['lease_time'] ?? null) ? (int)$h['lease_time'] : null,
                ];
            }
            $decoded['hosts'] = $cleanedHosts;
        }

        if (isset($decoded['wan']) && is_array($decoded['wan'])) {
            $decoded['wan']['external_ip'] = $this->cleanScalar($decoded['wan']['external_ip'] ?? null);
            $decoded['wan']['service_list'] = $this->cleanScalar($decoded['wan']['service_list'] ?? null);
            $decoded['wan']['subnet_mask'] = $this->cleanScalar($decoded['wan']['subnet_mask'] ?? null);
            $decoded['wan']['access_type'] = $this->cleanScalar($decoded['wan']['access_type'] ?? null, 'EPON/GPON');
            $decoded['wan']['uptime_seconds'] = is_numeric($decoded['wan']['uptime_seconds'] ?? null) ? (int)$decoded['wan']['uptime_seconds'] : null;
        }

        if (isset($decoded['optical']) && is_array($decoded['optical'])) {
            $decoded['optical']['rx_power'] = is_numeric($decoded['optical']['rx_power'] ?? null) ? (float)$decoded['optical']['rx_power'] : null;
            $decoded['optical']['tx_power'] = is_numeric($decoded['optical']['tx_power'] ?? null) ? (float)$decoded['optical']['tx_power'] : null;
            $decoded['optical']['voltage'] = is_numeric($decoded['optical']['voltage'] ?? null) ? (float)$decoded['optical']['voltage'] : null;
            $decoded['optical']['temperature'] = is_numeric($decoded['optical']['temperature'] ?? null) ? (float)$decoded['optical']['temperature'] : null;
            $decoded['optical']['bias_current'] = is_numeric($decoded['optical']['bias_current'] ?? null) ? (float)$decoded['optical']['bias_current'] : null;
        }

        if (isset($decoded['wifi']) && is_array($decoded['wifi'])) {
            $decoded['wifi']['ssid'] = $this->cleanScalar($decoded['wifi']['ssid'] ?? null);
            $decoded['wifi']['password'] = $this->cleanScalar($decoded['wifi']['password'] ?? null);
            $decoded['wifi']['channel'] = is_numeric($decoded['wifi']['channel'] ?? null) ? (int)$decoded['wifi']['channel'] : 6;
            $decoded['wifi']['security'] = $this->cleanScalar($decoded['wifi']['security'] ?? null, 'WPA2-PSK');
            $decoded['wifi']['enabled'] = filter_var($this->cleanScalar($decoded['wifi']['enabled'] ?? null, '1'), FILTER_VALIDATE_BOOLEAN);
        }

        return $decoded;
    }
}