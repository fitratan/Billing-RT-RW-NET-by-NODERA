import React, { useState, useEffect, useRef } from 'react';
import { Head, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout/app-layout';
import {
  Wifi,
  Radio,
  CheckCircle2,
  AlertTriangle,
  AlertCircle,
  RefreshCw,
  RotateCw,
  Key,
  User,
  Search,
  BookOpen,
  Copy,
  Check,
  Eye,
  EyeOff,
  Settings,
  Server,
  X,
  Smartphone,
  Laptop,
  Globe,
  Cpu,
  Thermometer,
  Zap,
  Activity,
  Layers,
  ChevronRight,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { adminSidebarItems, adminNavItems, adminBrand } from '@/lib/admin-nav';
import { MetricCard } from '@/components/tailadmin/MetricCard';
import { ViewModeSwitcher } from '@/components/ui/view-mode-switcher';

interface Customer {
  id: number;
  name: string;
  username: string;
  service_type: string;
  phone?: string;
  ip_address?: string;
}

interface ConnectedHost {
  hostname: string;
  ip_address: string;
  mac_address: string;
  is_active: boolean;
  interface_type: string;
  lease_time?: number | null;
}

interface OntDevice {
  id: number;
  tenant_id: number;
  customer_id: number | null;
  customer: Customer | null;
  serial_number: string;
  manufacturer: string;
  model_name: string;
  hardware_version: string | null;
  software_version: string | null;
  ip_address: string | null;
  mac_address: string | null;
  rx_power: number | null;
  tx_power: number | null;
  optical_voltage: number | null;
  optical_temp: number | null;
  wifi_ssid: string | null;
  wifi_password: string | null;
  wifi_channel: number | null;
  wifi_enabled: boolean;
  connected_devices_count: number;
  status: 'ONLINE' | 'OFFLINE' | 'WARNING' | 'CRITICAL';
  last_inform_at: string | null;
  registered_at: string | null;
  raw_parameters?: {
    hosts?: ConnectedHost[];
    wan?: {
      external_ip?: string | null;
      subnet_mask?: string | null;
      service_list?: string | null;
      access_type?: string | null;
      uptime_seconds?: number | null;
    };
    optical?: {
      rx_power?: number | null;
      tx_power?: number | null;
      voltage?: number | null;
      temperature?: number | null;
      bias_current?: number | null;
    };
    wifi?: {
      ssid?: string | null;
      password?: string | null;
      channel?: number | null;
      security?: string | null;
      enabled?: boolean;
    };
  } | null;
}

interface AcsSettings {
  id: number;
  tenant_id: number;
  is_enabled: boolean;
  connection_mode?: 'cloud' | 'self_hosted';
  acs_username: string;
  acs_password: string;
  server_url: string;
  nbi_url?: string;
  daily_rate_per_ont: number;
  free_tier_quota: number;
}

interface Props {
  devices: {
    data: OntDevice[];
    current_page: number;
    last_page: number;
    per_page?: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
  };
  metrics: {
    total: number;
    online: number;
    warning: number;
    critical: number;
    offline: number;
  };
  acsSettings: AcsSettings;
  customers: Customer[];
  filters: {
    search?: string;
    manufacturer?: string;
    status?: string;
  };
}

export default function OntDevices({ devices, metrics, acsSettings, customers, filters }: Props) {
  const [search, setSearch] = useState(filters.search || '');
  const [manufacturer, _setManufacturer] = useState(filters.manufacturer || 'all');
  const [statusFilter, setStatusFilter] = useState(filters.status || 'all');

  // Modals state
  const [guideModalOpen, setGuideModalOpen] = useState(false);
  const [settingsModalOpen, setSettingsModalOpen] = useState(false);
  const [wifiModalOpen, setWifiModalOpen] = useState(false);
  const [assignModalOpen, setAssignModalOpen] = useState(false);
  const [rebootModalOpen, setRebootModalOpen] = useState(false);
  const [managingDevice, setManagingDevice] = useState<OntDevice | null>(null);
  const [manageModalTab, setManageModalTab] = useState<'info' | 'hosts'>('info');

  // Active Device for Action
  const [selectedDevice, setSelectedDevice] = useState<OntDevice | null>(null);

  // Form states
  const [newSsid, setNewSsid] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [selectedCustomerId, setSelectedCustomerId] = useState<string>('');
  const [customerSearch, setCustomerSearch] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [copiedKey, setCopiedKey] = useState<string | null>(null);
  const [viewMode, setViewMode] = useState<'table' | 'grid'>('table');

  // Auto Sync Countdown (30s)
  const [countdown, setCountdown] = useState(30);
  const [isAutoSyncPaused, setIsAutoSyncPaused] = useState(false);

  // Dual Scroll Synchronization for Wide Table
  const topScrollRef = useRef<HTMLDivElement>(null);
  const tableScrollRef = useRef<HTMLDivElement>(null);
  const [tableScrollWidth, setTableScrollWidth] = useState(1350);
  const isSyncingTop = useRef(false);
  const isSyncingTable = useRef(false);

  const handleTopScroll = () => {
    if (isSyncingTop.current) {
      isSyncingTop.current = false;
      return;
    }
    if (topScrollRef.current && tableScrollRef.current) {
      isSyncingTable.current = true;
      tableScrollRef.current.scrollLeft = topScrollRef.current.scrollLeft;
    }
  };

  const handleTableScroll = () => {
    if (isSyncingTable.current) {
      isSyncingTable.current = false;
      return;
    }
    if (topScrollRef.current && tableScrollRef.current) {
      isSyncingTop.current = true;
      topScrollRef.current.scrollLeft = tableScrollRef.current.scrollLeft;
    }
  };

  useEffect(() => {
    const updateScrollWidth = () => {
      if (tableScrollRef.current) {
        setTableScrollWidth(tableScrollRef.current.scrollWidth);
      }
    };
    updateScrollWidth();
    window.addEventListener('resize', updateScrollWidth);
    return () => window.removeEventListener('resize', updateScrollWidth);
  }, [devices.data, viewMode]);

  useEffect(() => {
    if (isAutoSyncPaused) return;

    const timer = setInterval(() => {
      setCountdown((prev) => {
        if (prev <= 1) {
          router.reload({
            only: ['devices', 'metrics'],
          });
          return 30;
        }
        return prev - 1;
      });
    }, 1000);

    return () => clearInterval(timer);
  }, [isAutoSyncPaused]);

  // Mode settings form state
  const [connectionMode, setConnectionMode] = useState<'cloud' | 'self_hosted'>(acsSettings.connection_mode || 'cloud');
  const [serverUrl, setServerUrl] = useState(acsSettings.server_url || 'http://127.0.0.1:7547');
  const [nbiUrl, setNbiUrl] = useState(acsSettings.nbi_url || 'http://127.0.0.1:7557');
  const [acsUsername, setAcsUsername] = useState(acsSettings.acs_username || 'admin');
  const [acsPassword, setAcsPassword] = useState(acsSettings.acs_password || 'admin');

  const handleCopy = (text: string, key: string) => {
    navigator.clipboard.writeText(text);
    setCopiedKey(key);
    setTimeout(() => setCopiedKey(null), 2000);
  };

  const formatDateTime = (dateStr: string | null | undefined) => {
    if (!dateStr) return '-';
    try {
      const d = new Date(dateStr);
      if (isNaN(d.getTime())) return dateStr;
      return d.toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
      }).replace(/\./g, ':');
    } catch {
      return dateStr;
    }
  };

  const toSafeString = (val: any, fallback: string = ''): string => {
    if (val === null || val === undefined) return fallback;
    if (typeof val === 'string') return val.trim() || fallback;
    if (typeof val === 'number' || typeof val === 'boolean') return String(val);
    if (typeof val === 'object') {
      if (val._value !== undefined && typeof val._value === 'string') return val._value.trim() || fallback;
      if (val.value !== undefined && typeof val.value === 'string') return val.value.trim() || fallback;
      return fallback;
    }
    return fallback;
  };

  const handleFilter = () => {
    router.get(
      '/admin/ont-devices',
      {
        search: search || undefined,
        manufacturer: manufacturer !== 'all' ? manufacturer : undefined,
        status: statusFilter !== 'all' ? statusFilter : undefined,
      },
      { preserveState: true, replace: true }
    );
  };

  const handleStatusFilterChange = (newStatus: string) => {
    const nextStatus = statusFilter === newStatus ? 'all' : newStatus;
    setStatusFilter(nextStatus);
    router.get(
      '/admin/ont-devices',
      {
        search: search || undefined,
        manufacturer: manufacturer !== 'all' ? manufacturer : undefined,
        status: nextStatus !== 'all' ? nextStatus : undefined,
      },
      { preserveState: true, replace: true }
    );
  };

  const handleSyncAcs = () => {
    setIsSubmitting(true);
    setCountdown(30);
    router.post(
      '/admin/ont-devices/sync-acs',
      {},
      {
        preserveScroll: true,
        onFinish: () => setIsSubmitting(false),
      }
    );
  };

  const openWifiModal = (device: OntDevice) => {
    setSelectedDevice(device);
    setNewSsid(device.wifi_ssid || '');
    setNewPassword(device.wifi_password || '');
    setWifiModalOpen(true);
  };

  const submitWifi = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedDevice) return;
    setIsSubmitting(true);
    router.post(
      `/admin/ont-devices/${selectedDevice.id}/wifi`,
      {
        ssid: newSsid,
        password: newPassword,
      },
      {
        onSuccess: () => {
          setWifiModalOpen(false);
          setIsSubmitting(false);
        },
        onError: () => setIsSubmitting(false),
      }
    );
  };

  const openAssignModal = (device: OntDevice) => {
    setSelectedDevice(device);
    setSelectedCustomerId(device.customer_id ? String(device.customer_id) : '');
    setAssignModalOpen(true);
  };

  const submitAssign = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedDevice) return;
    setIsSubmitting(true);
    router.post(
      `/admin/ont-devices/${selectedDevice.id}/assign`,
      {
        customer_id: selectedCustomerId ? Number(selectedCustomerId) : null,
      },
      {
        onSuccess: () => {
          setAssignModalOpen(false);
          setIsSubmitting(false);
        },
        onError: () => setIsSubmitting(false),
      }
    );
  };

  const openRebootModal = (device: OntDevice) => {
    setSelectedDevice(device);
    setRebootModalOpen(true);
  };

  const confirmReboot = () => {
    if (!selectedDevice) return;
    setIsSubmitting(true);
    router.post(
      `/admin/ont-devices/${selectedDevice.id}/reboot`,
      {},
      {
        onSuccess: () => {
          setRebootModalOpen(false);
          setIsSubmitting(false);
        },
        onError: () => setIsSubmitting(false),
      }
    );
  };

  const refreshDevice = (device: OntDevice) => {
    router.post(`/admin/ont-devices/${device.id}/refresh`, {}, { preserveScroll: true });
  };

  const submitSettings = (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    router.post(
      '/admin/ont-devices/settings',
      {
        connection_mode: connectionMode,
        server_url: serverUrl,
        nbi_url: nbiUrl,
        acs_username: acsUsername,
        acs_password: acsPassword,
      },
      {
        onSuccess: () => {
          setSettingsModalOpen(false);
          setIsSubmitting(false);
        },
        onError: () => setIsSubmitting(false),
      }
    );
  };

  const filteredCustomers = customers.filter((c) => {
    const q = customerSearch.trim().toLowerCase();
    if (!q) return true;
    return (
      (c.name || '').toLowerCase().includes(q) ||
      (c.username || '').toLowerCase().includes(q) ||
      (c.phone || '').includes(q) ||
      (c.ip_address || '').includes(q)
    );
  });

  return (
    <AppLayout
      title="Manajemen ONT"
      brand={adminBrand}
      sidebarItems={adminSidebarItems}
      navItems={adminNavItems}
    >
      <Head title="Manajemen Modem ONT (TR-069) - NODERA" />

      <div className="space-y-4 sm:space-y-6 w-full min-w-0 max-w-full">
        {/* ── TOP 4 KPI METRICS (CLICKABLE TO FILTER STATUS) ── */}
        <div className="grid grid-cols-2 gap-2.5 sm:gap-4 lg:grid-cols-4">
          <MetricCard
            title="Total ONT"
            value={`${metrics.total} Unit`}
            icon={<Wifi className="h-5 w-5 sm:h-6 sm:w-6 text-brand-500" />}
            sub={statusFilter === 'all' || !statusFilter ? 'Semua status' : 'Reset filter'}
            onClick={() => handleStatusFilterChange('all')}
            isActive={statusFilter === 'all' || !statusFilter}
          />
          <MetricCard
            title="ONT Online"
            value={`${metrics.online} Unit`}
            icon={<CheckCircle2 className="h-5 w-5 sm:h-6 sm:w-6 text-emerald-500" />}
            sub={statusFilter === 'ONLINE' ? 'Filter Online' : 'Sesi TR-069 aktif'}
            onClick={() => handleStatusFilterChange('ONLINE')}
            isActive={statusFilter === 'ONLINE'}
          />
          <MetricCard
            title="Redaman Waspada"
            value={`${metrics.warning} Unit`}
            icon={<AlertTriangle className="h-5 w-5 sm:h-6 sm:w-6 text-amber-500" />}
            sub={statusFilter === 'WARNING' ? 'Filter Waspada' : '-25 s/d -27 dBm'}
            onClick={() => handleStatusFilterChange('WARNING')}
            isActive={statusFilter === 'WARNING'}
          />
          <MetricCard
            title="Redaman Kritis"
            value={`${metrics.critical} Unit`}
            icon={<AlertCircle className="h-5 w-5 sm:h-6 sm:w-6 text-rose-500" />}
            sub={statusFilter === 'CRITICAL' ? 'Filter Kritis' : 'Sinyal > -27 dBm'}
            onClick={() => handleStatusFilterChange('CRITICAL')}
            isActive={statusFilter === 'CRITICAL'}
          />
        </div>

        {/* ── 1-LINE RESPONSIVE INTEGRATED TOOLBAR ── */}
        <div className="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-xs dark:border-gray-800 dark:bg-white/[0.03] flex flex-col gap-2.5 sm:gap-3 lg:flex-row lg:items-center lg:justify-between w-full min-w-0">
          <div className="flex items-center gap-2 sm:gap-3 flex-1 min-w-0 w-full">
            <div className="relative flex-1 min-w-0">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && handleFilter()}
                placeholder="Cari Serial Number, Pelanggan, IP..."
                className="h-10 w-full rounded-xl border border-gray-200 bg-gray-50/75 pl-9 pr-8 text-xs text-gray-800 placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-200 dark:placeholder:text-gray-500 font-medium transition shadow-2xs"
              />
              {search && (
                <button
                  type="button"
                  onClick={() => {
                    setSearch('');
                    router.get('/admin/ont-devices', {
                      status: statusFilter !== 'all' ? statusFilter : undefined,
                    });
                  }}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                >
                  <X className="h-3.5 w-3.5" />
                </button>
              )}
            </div>
          </div>

          <div className="flex items-center gap-1.5 sm:gap-2 w-full lg:w-auto justify-between lg:justify-end min-w-0">
            {/* Auto-Sync Countdown (Solid Brand Theme Badge) */}
            <button
              type="button"
              onClick={() => {
                setCountdown(30);
                handleSyncAcs();
              }}
              title="Klik untuk sinkronisasi instan"
              className="h-10 inline-flex items-center gap-1 sm:gap-1.5 px-2.5 sm:px-3.5 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-white font-mono text-[11px] sm:text-xs font-bold shadow-xs whitespace-nowrap shrink-0 transition cursor-pointer"
            >
              <RefreshCw className={cn('h-3.5 w-3.5 text-white shrink-0', !isAutoSyncPaused && 'animate-spin')} style={{ animationDuration: '3s' }} />
              <span><span className="hidden xs:inline">Auto </span>Sync <strong className="text-white font-black">{countdown}s</strong></span>
            </button>

            <div className="shrink-0">
              <ViewModeSwitcher value={viewMode} onChange={setViewMode} size="sm" />
            </div>

            <button
              type="button"
              onClick={() => setSettingsModalOpen(true)}
              className="h-10 inline-flex items-center justify-center gap-1.5 px-2.5 sm:px-3.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-200 transition shadow-2xs cursor-pointer shrink-0"
              title={connectionMode === 'self_hosted' ? 'Mode: Server Mandiri' : 'Mode: Cloud Managed'}
            >
              <Settings className="h-3.5 w-3.5 text-gray-500 shrink-0" />
              <span className="hidden md:inline">{connectionMode === 'self_hosted' ? 'Server Mandiri' : 'Cloud Managed'}</span>
            </button>

            <button
              type="button"
              onClick={handleSyncAcs}
              disabled={isSubmitting}
              className="h-10 px-3 sm:px-4 rounded-xl bg-brand-500 hover:bg-brand-600 active:scale-95 text-xs font-bold text-white shadow-xs inline-flex items-center justify-center gap-1.5 flex-1 sm:flex-none shrink-0 cursor-pointer transition whitespace-nowrap"
            >
              <RefreshCw className={cn('h-3.5 w-3.5 sm:h-4 sm:w-4 shrink-0', isSubmitting && 'animate-spin')} />
              <span><span className="hidden xs:inline">Sinkronkan </span>ACS</span>
            </button>
          </div>
        </div>

        {/* ── ONT DEVICES CONTENT (TABLE OR GRID) ── */}
        {devices.data.length === 0 ? (
          <div className="rounded-2xl border border-gray-200 bg-white p-6 sm:p-12 text-center shadow-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="flex flex-col items-center justify-center gap-2 max-w-md mx-auto">
              <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-brand-500 dark:bg-blue-500/10">
                <Wifi className="h-6 w-6" />
              </div>
              <p className="font-bold text-xs sm:text-sm text-gray-800 dark:text-white">Belum ada modem ONT terdeteksi di GenieACS</p>
              <p className="text-[11px] sm:text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                {connectionMode === 'cloud'
                  ? 'Pastikan Inform URL CWMP (acs.dgtlnetsolution.com) sudah dimasukkan pada menu TR-069 modem pelanggan.'
                  : 'Pastikan server GenieACS mandiri Anda aktif dan modem sudah dikonfigurasi ke server Anda.'}
              </p>
              <div className="mt-3 flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setGuideModalOpen(true)}
                  className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-50 hover:bg-brand-100 text-brand-600 dark:bg-brand-500/15 dark:hover:bg-brand-500/25 dark:text-brand-400 text-xs font-bold transition cursor-pointer"
                >
                  <BookOpen className="h-3.5 w-3.5" />
                  <span>Lihat Panduan TR-069</span>
                </button>
                <button
                  type="button"
                  onClick={handleSyncAcs}
                  disabled={isSubmitting}
                  className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-semibold transition cursor-pointer"
                >
                  <RefreshCw className={cn('h-3.5 w-3.5', isSubmitting && 'animate-spin')} />
                  <span>Cek Ulang</span>
                </button>
              </div>
            </div>
          </div>
        ) : viewMode === 'grid' ? (
          <div className="grid items-start gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 2xl:grid-cols-4">
            {devices.data.map((device) => {
              const rx = device.rx_power;
              const isRxGood = rx !== null && rx >= -24.5;
              const isRxWarn = rx !== null && rx < -24.5 && rx >= -27;
              const isRxCrit = rx !== null && rx < -27;

              return (
                <div
                  key={device.id}
                  className="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 transition shadow-xs dark:border-gray-800 dark:bg-white/[0.02] space-y-3.5"
                >
                  {/* Top Header Card */}
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex items-center gap-3 min-w-0 flex-1">
                      <div className="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-brand-500 dark:bg-blue-500/10 font-bold">
                        <Wifi className="h-5 w-5" />
                        <span
                          className={cn(
                            'absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full ring-2 ring-white dark:ring-gray-900',
                            device.status === 'ONLINE' ? 'bg-emerald-500' : 'bg-slate-400'
                          )}
                        />
                      </div>
                      <div className="min-w-0 flex-1">
                        <h4 className="text-xs font-mono font-bold text-gray-900 dark:text-white truncate">
                          {toSafeString(device.serial_number)}
                        </h4>
                        <div className="text-[11px] text-gray-500 dark:text-gray-400 truncate flex items-center gap-1.5">
                          <span>{toSafeString(device.manufacturer, 'XPON')} {toSafeString(device.model_name, 'ONT')}</span>
                          <span>•</span>
                          <span className="inline-flex items-center gap-0.5 text-brand-600 dark:text-brand-400 font-medium">
                            <Smartphone className="h-3 w-3" />
                            {device.connected_devices_count || device.raw_parameters?.hosts?.length || 0}
                          </span>
                        </div>
                      </div>
                    </div>

                    {/* Status Badge */}
                    <span
                      className={cn(
                        'inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold text-white shadow-xs whitespace-nowrap',
                        device.status === 'ONLINE' ? 'bg-emerald-500' : 'bg-slate-500'
                      )}
                    >
                      {device.status === 'ONLINE' ? 'Online' : 'Offline'}
                    </span>
                  </div>

                  {/* Customer row */}
                  <div className="rounded-xl border border-gray-100 bg-gray-50/80 p-2.5 text-xs dark:border-gray-800 dark:bg-gray-900/50">
                    <div className="text-[10px] text-gray-400 dark:text-gray-500 mb-0.5">Pelanggan Terhubung</div>
                    {device.customer ? (
                      <div className="flex items-center justify-between gap-2">
                        <span className="font-bold text-gray-900 dark:text-white truncate">
                          {toSafeString(device.customer.name)}
                        </span>
                        <span className="font-mono text-[11px] text-brand-600 dark:text-brand-400 shrink-0">
                          {toSafeString(device.customer.username)}
                        </span>
                      </div>
                    ) : (
                      <button
                        type="button"
                        onClick={() => openAssignModal(device)}
                        className="text-xs font-semibold text-gray-400 hover:text-brand-600 dark:hover:text-brand-400 cursor-pointer underline decoration-dashed"
                      >
                        + Hubungkan Pelanggan
                      </button>
                    )}
                  </div>

                  {/* Spec 2-col Sunken Box */}
                  <div className="grid grid-cols-2 gap-2 rounded-xl border border-gray-100 bg-gray-50/80 p-2.5 text-xs dark:border-gray-800 dark:bg-gray-900/50">
                    <div>
                      <span className="text-[10px] text-gray-400 dark:text-gray-500 block mb-0.5">Redaman Rx</span>
                      {rx !== null ? (
                        <span
                          className={cn(
                            'inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold font-mono text-white shadow-xs',
                            isRxGood && 'bg-emerald-500',
                            isRxWarn && 'bg-amber-500',
                            isRxCrit && 'bg-rose-500'
                          )}
                        >
                          {rx.toFixed(2)} dBm
                        </span>
                      ) : (
                        <span className="font-mono text-gray-400">-</span>
                      )}
                    </div>
                    <div className="text-right">
                      <span className="text-[10px] text-gray-400 dark:text-gray-500 block mb-0.5">Wi-Fi (SSID)</span>
                      <strong className="text-gray-800 dark:text-gray-200 text-xs truncate block font-medium">
                        {toSafeString(device.wifi_ssid, '-')}
                      </strong>
                    </div>
                  </div>

                  {/* Action Footer */}
                  <div className="pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between gap-1.5">
                    <span className="text-[10px] font-mono text-gray-400 dark:text-gray-500 truncate">
                      {toSafeString(device.ip_address, 'No IP')}
                    </span>

                    <button
                      type="button"
                      onClick={() => setManagingDevice(device)}
                      className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                    >
                      <Settings className="h-3.5 w-3.5" />
                      <span>Kelola</span>
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        ) : (
          <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-white/[0.03] min-w-0 max-w-full">
            {/* ── TOP HORIZONTAL SCROLL RUNWAY (Geser Tabel dari Atas) ── */}
            <div
              ref={topScrollRef}
              onScroll={handleTopScroll}
              className="overflow-x-auto table-scrollbar w-full border-b border-gray-100 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-900/50"
            >
              <div style={{ width: `${tableScrollWidth}px`, height: '10px' }} />
            </div>

            <div
              ref={tableScrollRef}
              onScroll={handleTableScroll}
              className="overflow-x-auto table-scrollbar w-full"
            >
              <table className="w-full min-w-[1300px] text-left text-xs whitespace-nowrap border-collapse">
                <thead>
                  <tr className="border-b border-gray-200 dark:border-gray-800 bg-gray-50/75 dark:bg-white/[0.02] text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider text-[11px]">
                    <th className="w-12 px-3.5 py-3.5 text-center whitespace-nowrap">#</th>
                    <th className="px-3.5 py-3.5 whitespace-nowrap">Serial Number</th>
                    <th className="px-3.5 py-3.5 whitespace-nowrap">Pelanggan Terhubung</th>
                    <th className="px-3.5 py-3.5 whitespace-nowrap">Merek & Model</th>
                    <th className="px-3.5 py-3.5 whitespace-nowrap">IP Address</th>
                    <th className="px-3.5 py-3.5 text-center whitespace-nowrap">Redaman Optik (Rx)</th>
                    <th className="px-3.5 py-3.5 text-center whitespace-nowrap">Daya Pancar (Tx)</th>
                    <th className="px-3.5 py-3.5 whitespace-nowrap">Wi-Fi (SSID)</th>
                    <th className="px-3.5 py-3.5 text-center whitespace-nowrap">Klien Aktif</th>
                    <th className="px-3.5 py-3.5 text-center whitespace-nowrap">Status Sesi</th>
                    <th className="px-3.5 py-3.5 whitespace-nowrap">Terakhir Online</th>
                    <th className="px-3.5 py-3.5 text-center whitespace-nowrap">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800/80">
                  {devices.data.map((device, idx) => {
                    const rx = device.rx_power;
                    const tx = device.tx_power;
                    const isRxGood = rx !== null && rx >= -24.5;
                    const isRxWarn = rx !== null && rx < -24.5 && rx >= -27;
                    const isRxCrit = rx !== null && rx < -27;

                    return (
                      <tr
                        key={device.id}
                        className="transition-colors duration-150 even:bg-gray-50/40 dark:even:bg-[#151C28]/30 hover:bg-blue-50/40 dark:hover:bg-[#162030]/60 align-middle"
                      >
                        {/* 1. No / ID */}
                        <td className="w-12 px-3.5 py-3 text-center text-xs text-gray-400 font-mono align-middle">
                          {(devices.current_page - 1) * (devices.per_page || 15) + idx + 1}
                        </td>

                        {/* 2. Serial Number */}
                        <td className="px-3.5 py-3 whitespace-nowrap align-middle">
                          <div className="flex items-center gap-1.5">
                            <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-50 text-brand-500 dark:bg-blue-500/10 shrink-0">
                              <Radio className="h-3.5 w-3.5" />
                            </div>
                            <span className="font-mono font-bold text-gray-900 dark:text-white text-xs">
                              {toSafeString(device.serial_number)}
                            </span>
                            <button
                              type="button"
                              onClick={() => handleCopy(toSafeString(device.serial_number), `sn_${device.id}`)}
                              title="Salin Serial Number"
                              className="p-1 rounded-md text-gray-400 hover:text-brand-600 hover:bg-gray-100 dark:hover:bg-gray-800 dark:hover:text-brand-400 transition cursor-pointer"
                            >
                              {copiedKey === `sn_${device.id}` ? (
                                <Check className="h-3 w-3 text-emerald-500" />
                              ) : (
                                <Copy className="h-3 w-3" />
                              )}
                            </button>
                          </div>
                        </td>

                        {/* 3. Pelanggan Terhubung */}
                        <td className="px-3.5 py-3 whitespace-nowrap align-middle">
                          {device.customer ? (
                            <div>
                              <div className="font-bold text-gray-900 dark:text-white text-xs">
                                {toSafeString(device.customer.name)}
                              </div>
                              <div className="font-mono text-[11px] text-brand-600 dark:text-brand-400">
                                {toSafeString(device.customer.username)}
                              </div>
                            </div>
                          ) : (
                            <button
                              type="button"
                              onClick={() => openAssignModal(device)}
                              className="text-xs font-semibold text-gray-400 hover:text-brand-600 dark:hover:text-brand-400 cursor-pointer underline decoration-dashed"
                            >
                              + Hubungkan Pelanggan
                            </button>
                          )}
                        </td>

                        {/* 4. Merek & Model */}
                        <td className="px-3.5 py-3 whitespace-nowrap align-middle">
                          <div className="text-xs font-semibold text-gray-900 dark:text-white">
                            {toSafeString(device.manufacturer, 'XPON')} {toSafeString(device.model_name, 'ONT')}
                          </div>
                          {(device.software_version || device.hardware_version) && (
                            <div className="text-[10px] font-mono text-gray-400 dark:text-gray-500">
                              v{toSafeString(device.software_version || device.hardware_version)}
                            </div>
                          )}
                        </td>

                        {/* 5. IP Address */}
                        <td className="px-3.5 py-3 whitespace-nowrap align-middle">
                          <span className="font-mono text-xs text-gray-700 dark:text-gray-300">
                            {toSafeString(device.ip_address, '-')}
                          </span>
                        </td>

                        {/* 6. Redaman Optik (Rx) */}
                        <td className="px-3.5 py-3 text-center whitespace-nowrap align-middle">
                          {rx !== null ? (
                            <span
                              className={cn(
                                'inline-flex items-center px-2.5 py-0.5 rounded-md text-[11px] font-bold font-mono text-white shadow-xs',
                                isRxGood && 'bg-emerald-500',
                                isRxWarn && 'bg-amber-500',
                                isRxCrit && 'bg-rose-500'
                              )}
                            >
                              {rx.toFixed(2)} dBm
                            </span>
                          ) : (
                            <span className="text-gray-400 font-mono text-xs">-</span>
                          )}
                        </td>

                        {/* 7. Daya Pancar (Tx) */}
                        <td className="px-3.5 py-3 text-center whitespace-nowrap align-middle">
                          {tx !== null ? (
                            <span className="font-mono text-xs font-semibold text-gray-700 dark:text-gray-300">
                              {tx > 0 ? '+' : ''}{tx.toFixed(2)} dBm
                            </span>
                          ) : (
                            <span className="text-gray-400 font-mono text-xs">-</span>
                          )}
                        </td>

                        {/* 8. Wi-Fi (SSID) */}
                        <td className="px-3.5 py-3 whitespace-nowrap align-middle">
                          <div className="flex items-center gap-1.5 font-medium text-gray-900 dark:text-white text-xs">
                            <Wifi className="h-3.5 w-3.5 text-brand-500 shrink-0" />
                            <span>{toSafeString(device.wifi_ssid, '-')}</span>
                          </div>
                        </td>

                        {/* 9. Klien Aktif */}
                        <td className="px-3.5 py-3 text-center whitespace-nowrap align-middle">
                          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-[11px] font-semibold text-gray-700 dark:text-gray-300">
                            <Smartphone className="h-3 w-3 text-brand-500" />
                            {device.connected_devices_count || device.raw_parameters?.hosts?.length || 0} Klien
                          </span>
                        </td>

                        {/* 10. Status Sesi */}
                        <td className="px-3.5 py-3 text-center whitespace-nowrap align-middle">
                          <span
                            className={cn(
                              'inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold text-white shadow-xs whitespace-nowrap',
                              device.status === 'ONLINE' ? 'bg-emerald-500' : 'bg-slate-500'
                            )}
                          >
                            {device.status === 'ONLINE' ? 'Online' : 'Offline'}
                          </span>
                        </td>

                        {/* 11. Terakhir Online */}
                        <td className="px-3.5 py-3 whitespace-nowrap text-xs text-gray-600 dark:text-gray-400 align-middle">
                          {formatDateTime(device.last_inform_at)}
                        </td>

                        {/* 12. Aksi */}
                        <td className="px-3.5 py-3 text-center whitespace-nowrap align-middle">
                          <button
                            type="button"
                            onClick={() => setManagingDevice(device)}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold text-xs transition cursor-pointer"
                          >
                            <Settings className="h-3.5 w-3.5" />
                            <span>Kelola</span>
                          </button>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* ── MODAL: PANDUAN INPUT TR-069 DI MODEM (TAILADMIN CLEAN) ── */}
        {guideModalOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4">
            <div className="w-full max-w-lg rounded-2xl bg-white shadow-xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800 overflow-hidden">
              <div className="border-b border-gray-200 px-6 py-4 dark:border-gray-800 flex items-center justify-between">
                <div className="flex items-center gap-2.5">
                  <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-50 text-brand-500 dark:bg-blue-500/10">
                    <BookOpen className="h-4 w-4" />
                  </div>
                  <h3 className="font-bold text-gray-900 dark:text-white text-sm">
                    Parameter TR-069 Modem ONT
                  </h3>
                </div>
                <button
                  type="button"
                  onClick={() => setGuideModalOpen(false)}
                  className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                >
                  <X className="h-4 w-4" />
                </button>
              </div>

              <div className="p-6 space-y-4">
                <p className="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                  Masukkan parameter berikut pada menu <strong>Network &gt; TR-069</strong> di modem ONT pelanggan:
                </p>

                <div className="space-y-3">
                  <div className="rounded-xl border border-gray-200 bg-gray-50/75 p-3 dark:border-gray-800 dark:bg-gray-800/40">
                    <div className="flex items-center justify-between mb-1">
                      <span className="text-[11px] font-bold text-gray-500 uppercase tracking-wider">ACS Inform URL</span>
                      <button
                        type="button"
                        onClick={() => handleCopy(acsSettings.server_url + '/', 'acs_url')}
                        title="Salin URL"
                        className="p-1 rounded-md text-gray-400 hover:text-brand-600 hover:bg-gray-200/60 dark:hover:bg-gray-700 dark:hover:text-brand-400 transition cursor-pointer"
                      >
                        {copiedKey === 'acs_url' ? <Check className="h-3.5 w-3.5 text-emerald-500" /> : <Copy className="h-3.5 w-3.5" />}
                      </button>
                    </div>
                    <div className="font-mono text-xs font-bold text-gray-900 dark:text-white break-all">
                      {acsSettings.server_url}/
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-3">
                    <div className="rounded-xl border border-gray-200 bg-gray-50/75 p-3 dark:border-gray-800 dark:bg-gray-800/40">
                      <div className="flex items-center justify-between mb-1">
                        <span className="text-[11px] font-bold text-gray-500 uppercase tracking-wider">ACS User Name</span>
                        <button
                          type="button"
                          onClick={() => handleCopy(acsSettings.acs_username || '', 'acs_user')}
                          title="Salin Username"
                          className="p-1 rounded-md text-gray-400 hover:text-brand-600 hover:bg-gray-200/60 dark:hover:bg-gray-700 dark:hover:text-brand-400 transition cursor-pointer"
                        >
                          {copiedKey === 'acs_user' ? <Check className="h-3.5 w-3.5 text-emerald-500" /> : <Copy className="h-3.5 w-3.5" />}
                        </button>
                      </div>
                      <div className="font-mono text-xs font-bold text-gray-900 dark:text-white truncate">
                        {acsSettings.acs_username}
                      </div>
                    </div>

                    <div className="rounded-xl border border-gray-200 bg-gray-50/75 p-3 dark:border-gray-800 dark:bg-gray-800/40">
                      <div className="flex items-center justify-between mb-1">
                        <span className="text-[11px] font-bold text-gray-500 uppercase tracking-wider">ACS Password</span>
                        <button
                          type="button"
                          onClick={() => handleCopy(acsSettings.acs_password || 'nodera123', 'acs_pass')}
                          title="Salin Password"
                          className="p-1 rounded-md text-gray-400 hover:text-brand-600 hover:bg-gray-200/60 dark:hover:bg-gray-700 dark:hover:text-brand-400 transition cursor-pointer"
                        >
                          {copiedKey === 'acs_pass' ? <Check className="h-3.5 w-3.5 text-emerald-500" /> : <Copy className="h-3.5 w-3.5" />}
                        </button>
                      </div>
                      <div className="font-mono text-xs font-bold text-gray-900 dark:text-white truncate">
                        {acsSettings.acs_password || 'nodera123'}
                      </div>
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-3">
                    <div className="rounded-xl border border-gray-200 bg-gray-50/75 p-3 dark:border-gray-800 dark:bg-gray-800/40">
                      <span className="text-[11px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Periodic Inform</span>
                      <div className="text-xs font-bold text-emerald-600 dark:text-emerald-400">
                        Enable (Centang)
                      </div>
                    </div>

                    <div className="rounded-xl border border-gray-200 bg-gray-50/75 p-3 dark:border-gray-800 dark:bg-gray-800/40">
                      <span className="text-[11px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Periodic Interval</span>
                      <div className="font-mono text-xs font-bold text-gray-900 dark:text-white">
                        600 (detik)
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div className="border-t border-gray-200 px-6 py-3.5 dark:border-gray-800 flex justify-end">
                <button
                  type="button"
                  onClick={() => setGuideModalOpen(false)}
                  className="rounded-xl bg-brand-500 hover:bg-brand-600 px-5 py-2 text-xs font-bold text-white shadow-xs cursor-pointer"
                >
                  Selesai
                </button>
              </div>
            </div>
          </div>
        )}

        {/* ── MODAL: PENGATURAN SERVER MODE (CLOUD VS MANDIRI) ── */}
        {settingsModalOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4">
            <div className="w-full max-w-lg rounded-2xl bg-white shadow-xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800 overflow-hidden">
              <form onSubmit={submitSettings}>
                <div className="border-b border-gray-200 px-6 py-4 dark:border-gray-800 flex items-center justify-between">
                  <div className="flex items-center gap-2.5">
                    <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-50 text-brand-500 dark:bg-blue-500/10">
                      <Settings className="h-4 w-4" />
                    </div>
                    <h3 className="font-bold text-gray-900 dark:text-white text-sm">
                      Mode Koneksi GenieACS
                    </h3>
                  </div>
                  <button
                    type="button"
                    onClick={() => setSettingsModalOpen(false)}
                    className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                  >
                    <X className="h-4 w-4" />
                  </button>
                </div>

                <div className="p-6 space-y-4">
                  <div className="grid grid-cols-2 gap-3">
                    <div
                      onClick={() => setConnectionMode('cloud')}
                      className={cn(
                        'p-4 rounded-xl border-2 transition cursor-pointer space-y-1',
                        connectionMode === 'cloud'
                          ? 'border-brand-500 bg-blue-50/50 dark:bg-blue-900/20'
                          : 'border-gray-200 dark:border-gray-800 hover:border-gray-300'
                      )}
                    >
                      <div className="flex items-center gap-2 font-bold text-xs text-gray-900 dark:text-white">
                        <Radio className="h-4 w-4 text-brand-500" />
                        Cloud Managed
                      </div>
                      <p className="text-[11px] text-gray-500">Server dikelola oleh Nodera Cloud.</p>
                    </div>

                    <div
                      onClick={() => setConnectionMode('self_hosted')}
                      className={cn(
                        'p-4 rounded-xl border-2 transition cursor-pointer space-y-1',
                        connectionMode === 'self_hosted'
                          ? 'border-emerald-500 bg-emerald-50/50 dark:bg-emerald-900/20'
                          : 'border-gray-200 dark:border-gray-800 hover:border-gray-300'
                      )}
                    >
                      <div className="flex items-center gap-2 font-bold text-xs text-gray-900 dark:text-white">
                        <Server className="h-4 w-4 text-emerald-600" />
                        Server Mandiri (GRATIS)
                      </div>
                      <p className="text-[11px] text-gray-500">Gunakan server GenieACS Anda sendiri.</p>
                    </div>
                  </div>

                  {connectionMode === 'self_hosted' && (
                    <div className="space-y-3 p-4 rounded-xl border border-emerald-200 bg-emerald-50/30 dark:border-emerald-800/50 dark:bg-emerald-950/20">
                      <div>
                        <label className="block text-[11px] font-bold text-gray-700 dark:text-gray-300 mb-1">
                          Inform URL CWMP (Diinput ke Modem)
                        </label>
                        <input
                          type="text"
                          required
                          value={serverUrl}
                          onChange={(e) => setServerUrl(e.target.value)}
                          placeholder="http://ip-server:7547"
                          className="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-mono dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:outline-hidden focus:border-emerald-500"
                        />
                      </div>

                      <div>
                        <label className="block text-[11px] font-bold text-gray-700 dark:text-gray-300 mb-1">
                          GenieACS NBI API URL (Port 7557)
                        </label>
                        <input
                          type="text"
                          required
                          value={nbiUrl}
                          onChange={(e) => setNbiUrl(e.target.value)}
                          placeholder="http://ip-server:7557"
                          className="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-mono dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:outline-hidden focus:border-emerald-500"
                        />
                      </div>

                      <div className="grid grid-cols-2 gap-3">
                        <div>
                          <label className="block text-[11px] font-bold text-gray-700 dark:text-gray-300 mb-1">
                            NBI Username
                          </label>
                          <input
                            type="text"
                            value={acsUsername}
                            onChange={(e) => setAcsUsername(e.target.value)}
                            placeholder="admin"
                            className="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-mono dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:outline-hidden focus:border-emerald-500"
                          />
                        </div>
                        <div>
                          <label className="block text-[11px] font-bold text-gray-700 dark:text-gray-300 mb-1">
                            NBI Password
                          </label>
                          <input
                            type="password"
                            value={acsPassword}
                            onChange={(e) => setAcsPassword(e.target.value)}
                            placeholder="admin"
                            className="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-mono dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:outline-hidden focus:border-emerald-500"
                          />
                        </div>
                      </div>
                    </div>
                  )}

                  {/* Tombol Buka Panduan TR-069 (Hanya untuk Cloud Managed Addon) */}
                  {connectionMode === 'cloud' && (
                    <button
                      type="button"
                      onClick={() => {
                        setSettingsModalOpen(false);
                        setGuideModalOpen(true);
                      }}
                      className="w-full py-2.5 px-3 rounded-xl border border-brand-200 bg-brand-50/50 hover:bg-brand-100/50 dark:border-brand-500/30 dark:bg-brand-500/10 dark:hover:bg-brand-500/20 text-xs font-bold text-brand-600 dark:text-brand-400 flex items-center justify-center gap-2 transition cursor-pointer"
                    >
                      <BookOpen className="h-4 w-4" />
                      <span>Parameter & Panduan TR-069 Cloud</span>
                    </button>
                  )}
                </div>

                <div className="border-t border-gray-200 px-6 py-3.5 dark:border-gray-800 flex justify-end gap-2">
                  <button
                    type="button"
                    onClick={() => setSettingsModalOpen(false)}
                    className="rounded-xl border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 cursor-pointer"
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    disabled={isSubmitting}
                    className="rounded-xl bg-brand-500 hover:bg-brand-600 px-5 py-2 text-xs font-bold text-white shadow-xs cursor-pointer flex items-center gap-1.5"
                  >
                    {isSubmitting ? 'Menyimpan...' : 'Simpan Mode'}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* ── MODAL: GANTI NAMA & PASSWORD WIFI ── */}
        {wifiModalOpen && selectedDevice && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4">
            <div className="w-full max-w-md rounded-2xl bg-white shadow-xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800 overflow-hidden">
              <form onSubmit={submitWifi}>
                <div className="border-b border-gray-200 px-6 py-4 dark:border-gray-800 flex items-center justify-between">
                  <div className="flex items-center gap-2.5">
                    <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-50 text-brand-500 dark:bg-blue-500/10">
                      <Key className="h-4 w-4" />
                    </div>
                    <div>
                      <h3 className="font-bold text-gray-900 dark:text-white text-sm">
                        Ganti Wi-Fi Modem
                      </h3>
                      <p className="text-[11px] text-gray-500">{selectedDevice.serial_number}</p>
                    </div>
                  </div>
                  <button
                    type="button"
                    onClick={() => setWifiModalOpen(false)}
                    className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                  >
                    <X className="h-4 w-4" />
                  </button>
                </div>

                <div className="p-6 space-y-4">
                  <div>
                    <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                      Nama Sinyal Wi-Fi (SSID)
                    </label>
                    <input
                      type="text"
                      required
                      value={newSsid}
                      onChange={(e) => setNewSsid(e.target.value)}
                      placeholder="Contoh: WiFi Rumah - 5G"
                      className="w-full rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-xs text-gray-900 focus:bg-white dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-hidden focus:border-brand-500"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                      Password Baru (Minimal 8 Karakter)
                    </label>
                    <div className="relative">
                      <input
                        type={showPassword ? 'text' : 'password'}
                        required
                        minLength={8}
                        value={newPassword}
                        onChange={(e) => setNewPassword(e.target.value)}
                        placeholder="••••••••"
                        className="w-full rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 pr-10 text-xs text-gray-900 focus:bg-white dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-hidden focus:border-brand-500"
                      />
                      <button
                        type="button"
                        onClick={() => setShowPassword(!showPassword)}
                        className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                      >
                        {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                      </button>
                    </div>
                  </div>
                </div>

                <div className="border-t border-gray-200 px-6 py-3.5 dark:border-gray-800 flex justify-end gap-2">
                  <button
                    type="button"
                    onClick={() => setWifiModalOpen(false)}
                    className="rounded-xl border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 cursor-pointer"
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    disabled={isSubmitting}
                    className="rounded-xl bg-brand-500 hover:bg-brand-600 px-5 py-2 text-xs font-bold text-white shadow-xs cursor-pointer flex items-center gap-1.5"
                  >
                    {isSubmitting ? 'Mengirim ke Modem...' : 'Terapkan ke Modem'}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* ── MODAL: HUBUNGKAN KE PELANGGAN ── */}
        {assignModalOpen && selectedDevice && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4">
            <div className="w-full max-w-md rounded-2xl bg-white shadow-xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800 overflow-hidden">
              <form onSubmit={submitAssign}>
                <div className="border-b border-gray-200 px-6 py-4 dark:border-gray-800 flex items-center justify-between">
                  <div className="flex items-center gap-2.5">
                    <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-50 text-brand-500 dark:bg-blue-500/10">
                      <User className="h-4 w-4" />
                    </div>
                    <div>
                      <h3 className="font-bold text-gray-900 dark:text-white text-sm">
                        Hubungkan Modem ke Pelanggan
                      </h3>
                      <p className="text-[11px] text-gray-500">{selectedDevice.serial_number}</p>
                    </div>
                  </div>
                  <button
                    type="button"
                    onClick={() => setAssignModalOpen(false)}
                    className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 cursor-pointer"
                  >
                    <X className="h-4 w-4" />
                  </button>
                </div>

                <div className="p-6 space-y-4">
                  <div>
                    <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                      Pilih Pelanggan / PPPoE:
                    </label>
                    <input
                      type="text"
                      value={customerSearch}
                      onChange={(e) => setCustomerSearch(e.target.value)}
                      placeholder="Ketik nama atau user PPPoE..."
                      className="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-xs mb-2 text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-hidden focus:border-brand-500"
                    />
                    <select
                      value={selectedCustomerId}
                      onChange={(e) => setSelectedCustomerId(e.target.value)}
                      className="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:outline-hidden focus:border-brand-500"
                    >
                      <option value="">-- Lepas Sambungan (Tanpa Pelanggan) --</option>
                      {filteredCustomers.map((c) => {
                        const label = c.name === c.username 
                          ? `${c.name}${c.ip_address ? ` [${c.ip_address}]` : ''}` 
                          : `${c.name} (${c.username})${c.ip_address ? ` [${c.ip_address}]` : ''}`;
                        return (
                          <option key={c.id} value={c.id}>
                            {label}
                          </option>
                        );
                      })}
                    </select>
                  </div>
                </div>

                <div className="border-t border-gray-200 px-6 py-3.5 dark:border-gray-800 flex justify-end gap-2">
                  <button
                    type="button"
                    onClick={() => setAssignModalOpen(false)}
                    className="rounded-xl border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 cursor-pointer"
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    disabled={isSubmitting}
                    className="rounded-xl bg-brand-500 hover:bg-brand-600 px-5 py-2 text-xs font-bold text-white shadow-xs cursor-pointer flex items-center gap-1.5"
                  >
                    {isSubmitting ? 'Menyimpan...' : 'Simpan Sambungan'}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* ── MODAL: REBOOT MODEM ── */}
        {rebootModalOpen && selectedDevice && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4">
            <div className="w-full max-w-sm rounded-2xl bg-white shadow-xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-6 text-center space-y-4">
              <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 mx-auto dark:bg-rose-500/10">
                <RotateCw className="h-6 w-6" />
              </div>
              <div>
                <h3 className="font-bold text-gray-900 dark:text-white text-base">
                  Reboot Modem ONT?
                </h3>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                  Perangkat <strong className="font-mono">{selectedDevice.serial_number}</strong> akan melakukan restart dan koneksi internet pelanggan terputus selama 1-2 menit.
                </p>
              </div>
              <div className="flex items-center justify-center gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setRebootModalOpen(false)}
                  className="rounded-xl border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="button"
                  onClick={confirmReboot}
                  disabled={isSubmitting}
                  className="rounded-xl bg-rose-600 hover:bg-rose-700 px-5 py-2 text-xs font-bold text-white shadow-xs cursor-pointer"
                >
                  {isSubmitting ? 'Mereboot...' : 'Ya, Reboot Sekarang'}
                </button>
              </div>
            </div>
          </div>
        )}

        {/* ── MODAL: KELOLA PERANGKAT ONT ── */}
        {managingDevice && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div className="relative w-full max-w-xl max-h-[92vh] overflow-y-auto rounded-2xl border border-gray-200 bg-white p-6 shadow-2xl dark:border-gray-800 dark:bg-gray-900 space-y-4">
              {/* Header */}
              <div className="flex items-start justify-between gap-3 border-b border-gray-100 pb-4 dark:border-gray-800">
                <div className="flex items-center gap-3">
                  <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-brand-600 dark:bg-blue-500/10 dark:text-brand-400 font-bold text-sm shadow-xs">
                    <Wifi className="h-5 w-5" />
                  </div>
                  <div>
                    <h3 className="text-base font-bold text-gray-800 dark:text-white">
                      {toSafeString(managingDevice.manufacturer, 'XPON')} {toSafeString(managingDevice.model_name, 'ONT')}
                    </h3>
                    <div className="flex flex-wrap items-center gap-2 mt-1">
                      <span
                        className={cn(
                          'inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold text-white shadow-xs whitespace-nowrap',
                          managingDevice.status === 'ONLINE' ? 'bg-emerald-500' : 'bg-slate-500'
                        )}
                      >
                        {managingDevice.status === 'ONLINE' ? 'Online' : 'Offline'}
                      </span>
                      <div className="flex items-center gap-1 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-md">
                        <span className="font-mono text-xs text-gray-500 dark:text-gray-400">SN:</span>
                        <span className="font-mono text-xs font-bold text-brand-600 dark:text-brand-400">{toSafeString(managingDevice.serial_number)}</span>
                        <button
                          type="button"
                          onClick={() => handleCopy(toSafeString(managingDevice.serial_number), `modal-sn-${managingDevice.id}`)}
                          className="p-0.5 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition cursor-pointer ml-0.5"
                          title="Salin Serial Number"
                        >
                          {copiedKey === `modal-sn-${managingDevice.id}` ? (
                            <Check className="h-3 w-3 text-emerald-500" />
                          ) : (
                            <Copy className="h-3 w-3" />
                          )}
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => setManagingDevice(null)}
                  className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 dark:text-gray-500 transition cursor-pointer"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              {/* Tab Switcher (2 Tabs Saja) */}
              <div className="flex items-center gap-1 rounded-xl bg-gray-100 dark:bg-gray-800/80 p-1 text-xs font-semibold">
                <button
                  type="button"
                  onClick={() => setManageModalTab('info')}
                  className={cn(
                    'flex-1 py-1.5 px-3 rounded-lg text-center transition cursor-pointer flex items-center justify-center gap-1.5',
                    manageModalTab === 'info'
                      ? 'bg-white text-brand-600 shadow-xs dark:bg-gray-700 dark:text-white'
                      : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200'
                  )}
                >
                  <Activity className="h-3.5 w-3.5" />
                  <span>Telemetri & Info</span>
                </button>
                <button
                  type="button"
                  onClick={() => setManageModalTab('hosts')}
                  className={cn(
                    'flex-1 py-1.5 px-3 rounded-lg text-center transition cursor-pointer flex items-center justify-center gap-1.5',
                    manageModalTab === 'hosts'
                      ? 'bg-white text-brand-600 shadow-xs dark:bg-gray-700 dark:text-white'
                      : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200'
                  )}
                >
                  <Smartphone className="h-3.5 w-3.5" />
                  <span>Klien Terhubung</span>
                  <span className="ml-1 inline-flex items-center justify-center px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-brand-500 text-white">
                    {managingDevice.raw_parameters?.hosts?.length || managingDevice.connected_devices_count || 0}
                  </span>
                </button>
              </div>

              {/* Tab 1: Telemetri & Info */}
              {manageModalTab === 'info' && (
                <div className="space-y-3">
                  <div className="grid grid-cols-2 gap-2 text-xs">
                    <div className="p-3 rounded-xl border border-gray-100 bg-gray-50/70 dark:border-gray-800 dark:bg-gray-800/40 space-y-1">
                      <span className="text-[11px] text-gray-500 dark:text-gray-400">Redaman Optik (Rx)</span>
                      <div>
                        {managingDevice.rx_power !== null ? (
                          <span
                            className={cn(
                              'inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold font-mono text-white shadow-xs',
                              managingDevice.rx_power >= -24.5
                                ? 'bg-emerald-500'
                                : managingDevice.rx_power >= -27.0
                                ? 'bg-amber-500'
                                : 'bg-rose-500'
                            )}
                          >
                            {managingDevice.rx_power.toFixed(2)} dBm
                          </span>
                        ) : (
                          <span className="text-gray-400 font-mono">-</span>
                        )}
                      </div>
                    </div>

                    <div className="p-3 rounded-xl border border-gray-100 bg-gray-50/70 dark:border-gray-800 dark:bg-gray-800/40 space-y-1">
                      <span className="text-[11px] text-gray-500 dark:text-gray-400">Daya Pancar (Tx)</span>
                      <div className="font-mono text-xs font-bold text-gray-900 dark:text-white">
                        {managingDevice.tx_power !== null ? `${managingDevice.tx_power > 0 ? '+' : ''}${managingDevice.tx_power.toFixed(2)} dBm` : '-'}
                      </div>
                    </div>

                    <div className="p-3 rounded-xl border border-gray-100 bg-gray-50/70 dark:border-gray-800 dark:bg-gray-800/40 space-y-1">
                      <span className="text-[11px] text-gray-500 dark:text-gray-400">Voltase Modem</span>
                      <div className="font-mono text-xs font-bold text-gray-900 dark:text-white">
                        {managingDevice.optical_voltage || managingDevice.raw_parameters?.optical?.voltage ? `${managingDevice.optical_voltage || managingDevice.raw_parameters?.optical?.voltage} V` : '-'}
                      </div>
                    </div>

                    <div className="p-3 rounded-xl border border-gray-100 bg-gray-50/70 dark:border-gray-800 dark:bg-gray-800/40 space-y-1">
                      <span className="text-[11px] text-gray-500 dark:text-gray-400">Suhu Modem</span>
                      <div className="font-mono text-xs font-bold text-gray-900 dark:text-white">
                        {managingDevice.optical_temp || managingDevice.raw_parameters?.optical?.temperature ? `${managingDevice.optical_temp || managingDevice.raw_parameters?.optical?.temperature} °C` : '-'}
                      </div>
                    </div>
                  </div>

                  <div className="rounded-xl border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-900/50 p-3 space-y-2 text-xs">
                    <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                      <span className="text-gray-500 dark:text-gray-400 font-medium">IP Address WAN</span>
                      <span className="font-mono text-gray-900 dark:text-white font-bold">
                        {toSafeString(managingDevice.ip_address || managingDevice.raw_parameters?.wan?.external_ip, '-')}
                      </span>
                    </div>
                    <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                      <span className="text-gray-500 dark:text-gray-400 font-medium">MAC Address</span>
                      <span className="font-mono text-gray-900 dark:text-white font-medium">
                        {toSafeString(managingDevice.mac_address, '-')}
                      </span>
                    </div>
                    <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                      <span className="text-gray-500 dark:text-gray-400 font-medium">Versi Hardware / Software</span>
                      <span className="text-gray-800 dark:text-gray-200 font-medium">
                        {managingDevice.hardware_version ? `HW: ${toSafeString(managingDevice.hardware_version)}` : ''}
                        {managingDevice.hardware_version && managingDevice.software_version ? ' • ' : ''}
                        {managingDevice.software_version ? `FW: v${toSafeString(managingDevice.software_version)}` : (!managingDevice.hardware_version ? '-' : '')}
                      </span>
                    </div>
                    <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                      <span className="text-gray-500 dark:text-gray-400 font-medium">Tipe Akses WAN</span>
                      <span className="font-medium text-gray-800 dark:text-gray-200">
                        {toSafeString(managingDevice.raw_parameters?.wan?.access_type, 'EPON / GPON (XPON)')}
                      </span>
                    </div>
                    <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                      <span className="text-gray-500 dark:text-gray-400 font-medium">Nama SSID Wi-Fi</span>
                      <span className="font-bold text-brand-600 dark:text-brand-400">
                        {toSafeString(managingDevice.wifi_ssid || managingDevice.raw_parameters?.wifi?.ssid, '-')}
                      </span>
                    </div>
                    <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                      <span className="text-gray-500 dark:text-gray-400 font-medium">Password Wi-Fi</span>
                      <span className="font-mono font-semibold text-gray-900 dark:text-white">
                        {toSafeString(managingDevice.wifi_password || managingDevice.raw_parameters?.wifi?.password, 'Tersimpan di Modem')}
                      </span>
                    </div>
                    <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                      <span className="text-gray-500 dark:text-gray-400 font-medium">Keamanan Wi-Fi</span>
                      <span className="text-gray-700 dark:text-gray-300">
                        {toSafeString(managingDevice.raw_parameters?.wifi?.security, 'WPA2-PSK (AES)')}
                      </span>
                    </div>
                    <div className="flex justify-between items-center py-1 border-b border-gray-200/50 dark:border-gray-800/50">
                      <span className="text-gray-500 dark:text-gray-400 font-medium">Pelanggan Terhubung</span>
                      <span className="font-bold text-gray-900 dark:text-white">
                        {managingDevice.customer ? `${toSafeString(managingDevice.customer.name)} (${toSafeString(managingDevice.customer.username)})` : 'Belum Terhubung'}
                      </span>
                    </div>
                    <div className="flex justify-between items-center py-1">
                      <span className="text-gray-500 dark:text-gray-400 font-medium">Inform Terakhir</span>
                      <span className="text-gray-900 dark:text-white font-medium text-xs">
                        {formatDateTime(managingDevice.last_inform_at)}
                      </span>
                    </div>
                  </div>
                </div>
              )}

              {/* Tab 2: Connected Hosts */}
              {manageModalTab === 'hosts' && (
                <div className="space-y-3">
                  <div className="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 px-1">
                    <span>Daftar Perangkat yang Terkoneksi ke Modem</span>
                    <span className="font-semibold text-gray-700 dark:text-gray-300">
                      {managingDevice.raw_parameters?.hosts?.length || 0} Host Ditemukan
                    </span>
                  </div>

                  <div className="max-h-60 overflow-y-auto space-y-2 pr-1">
                    {managingDevice.raw_parameters?.hosts && managingDevice.raw_parameters.hosts.length > 0 ? (
                      managingDevice.raw_parameters.hosts.map((host, idx) => (
                        <div
                          key={idx}
                          className="flex items-center justify-between p-2.5 rounded-xl border border-gray-100 bg-gray-50/70 dark:border-gray-800 dark:bg-gray-800/50 hover:bg-white dark:hover:bg-gray-800 transition"
                        >
                          <div className="flex items-center gap-2.5">
                            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-brand-600 dark:bg-blue-500/10 dark:text-brand-400">
                              {(() => {
                                const hName = toSafeString(host.hostname).toLowerCase();
                                return hName.includes('laptop') || hName.includes('pc') || hName.includes('desktop') ? (
                                  <Laptop className="h-4 w-4" />
                                ) : (
                                  <Smartphone className="h-4 w-4" />
                                );
                              })()}
                            </div>
                            <div>
                              <div className="flex items-center gap-1.5">
                                <span className="text-xs font-bold text-gray-900 dark:text-white">
                                  {toSafeString(host.hostname, 'Perangkat Tanpa Nama')}
                                </span>
                                {host.is_active && (
                                  <span className="h-2 w-2 rounded-full bg-emerald-500" title="Aktif" />
                                )}
                              </div>
                              <div className="flex items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400 font-mono">
                                <span>{toSafeString(host.ip_address, '-')}</span>
                                <span>•</span>
                                <span>{toSafeString(host.mac_address, '-')}</span>
                              </div>
                            </div>
                          </div>
                          <div className="text-right">
                            <span
                              className={cn(
                                'inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold text-white shadow-xs',
                                host.is_active ? 'bg-emerald-500' : 'bg-slate-400 dark:bg-slate-600'
                              )}
                            >
                              {host.is_active ? 'Aktif' : 'Tersimpan'}
                            </span>
                            <div className="text-[10px] text-gray-400 mt-0.5">
                              {toSafeString(host.interface_type) === '802.11' ? 'Wi-Fi' : 'LAN'}
                            </div>
                          </div>
                        </div>
                      ))
                    ) : (
                      <div className="text-center py-8 text-xs text-gray-400">
                        Belum ada riwayat perangkat klien yang terdeteksi di tabel DHCP modem.
                      </div>
                    )}
                  </div>
                </div>
              )}

              {/* Bottom Quick Action Bar (By Icon Aja) */}
              <div className="flex items-center justify-between pt-3 border-t border-gray-100 dark:border-gray-800">
                <span className="text-xs font-bold text-gray-500 dark:text-gray-400">Tindakan Cepat</span>
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={() => {
                      const dev = managingDevice;
                      if (dev) {
                        setManagingDevice(null);
                        openWifiModal(dev);
                      }
                    }}
                    title="Ganti Wi-Fi (SSID & Password)"
                    className="h-9 w-9 inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white hover:bg-blue-50 hover:border-blue-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-blue-900/20 text-brand-500 shadow-2xs transition cursor-pointer"
                  >
                    <Key className="h-4 w-4" />
                  </button>

                  <button
                    type="button"
                    onClick={() => {
                      const dev = managingDevice;
                      if (dev) {
                        setManagingDevice(null);
                        openAssignModal(dev);
                      }
                    }}
                    title="Tautkan Pelanggan (Binding)"
                    className="h-9 w-9 inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 shadow-2xs transition cursor-pointer"
                  >
                    <User className="h-4 w-4" />
                  </button>

                  <button
                    type="button"
                    onClick={() => {
                      const dev = managingDevice;
                      if (dev) {
                        setManagingDevice(null);
                        refreshDevice(dev);
                      }
                    }}
                    title="Refresh Parameter & Redaman"
                    className="h-9 w-9 inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white hover:bg-emerald-50 hover:border-emerald-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-emerald-900/20 text-emerald-500 shadow-2xs transition cursor-pointer"
                  >
                    <RefreshCw className="h-4 w-4" />
                  </button>

                  <button
                    type="button"
                    onClick={() => {
                      const dev = managingDevice;
                      if (dev) {
                        setManagingDevice(null);
                        openRebootModal(dev);
                      }
                    }}
                    title="Reboot Modem ONT"
                    className="h-9 w-9 inline-flex items-center justify-center rounded-xl border border-rose-200 bg-rose-50/50 hover:bg-rose-100 hover:border-rose-300 dark:border-rose-900/40 dark:bg-rose-950/20 dark:hover:bg-rose-900/40 text-rose-500 shadow-2xs transition cursor-pointer"
                  >
                    <RotateCw className="h-4 w-4" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
}