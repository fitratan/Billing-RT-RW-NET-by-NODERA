<?php
/*
 * Copyright (C) Nodera Mikhmon Bandwidth Management
 * FUP (Fair Usage Policy) & Traffic Control Engine
 */

if (!function_exists('fup_get_config_path')) {
    function fup_get_config_path($session) {
        $cleanSession = preg_replace('/[^a-zA-Z0-9_-]/', '', $session);
        $dir = __DIR__ . '/../config';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . '/fup_' . $cleanSession . '.json';
    }
}

if (!function_exists('fup_load_config')) {
    function fup_load_config($session, $API = null) {
        $path = fup_get_config_path($session);
        $config = null;

        if (file_exists($path)) {
            $raw = @file_get_contents($path);
            if (!empty($raw)) {
                $decoded = @json_decode($raw, true);
                if (is_array($decoded)) {
                    $config = $decoded;
                }
            }
        }

        // Fallback to RouterOS system script store if local file is missing
        if ($config === null && $API !== null && is_object($API)) {
            try {
                $getScript = $API->comm('/system/script/print', array(
                    '?name' => 'nodera-fup-config'
                ));
                if (is_array($getScript) && !empty($getScript[0]['comment'])) {
                    $decoded = @json_decode($getScript[0]['comment'], true);
                    if (is_array($decoded)) {
                        $config = $decoded;
                        @file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT));
                    }
                }
            } catch (Exception $e) {
                // Silently fallback to default
            }
        }

        if (!is_array($config)) {
            $config = array(
                'enabled' => true,
                'profiles' => array(),
                'updated_at' => date('Y-m-d H:i:s')
            );
        }

        if (!isset($config['enabled'])) {
            $config['enabled'] = true;
        }
        if (!isset($config['profiles']) || !is_array($config['profiles'])) {
            $config['profiles'] = array();
        }

        return $config;
    }
}

if (!function_exists('fup_save_config')) {
    function fup_save_config($session, $config, $API = null) {
        $path = fup_get_config_path($session);
        $config['updated_at'] = date('Y-m-d H:i:s');
        $json = json_encode($config, JSON_PRETTY_PRINT);
        @file_put_contents($path, $json);

        if ($API !== null && is_object($API)) {
            try {
                // 1. Sync config payload into RouterOS /system/script
                $getScript = $API->comm('/system/script/print', array(
                    '?name' => 'nodera-fup-config'
                ));
                $compactJson = json_encode($config);
                if (is_array($getScript) && !empty($getScript[0]['.id'])) {
                    $API->comm('/system/script/set', array(
                        '.id' => $getScript[0]['.id'],
                        'comment' => $compactJson,
                        'source' => '# Nodera FUP Configuration Data Store'
                    ));
                } else {
                    $API->comm('/system/script/add', array(
                        'name' => 'nodera-fup-config',
                        'comment' => $compactJson,
                        'source' => '# Nodera FUP Configuration Data Store',
                        'policy' => 'read,write,policy,test'
                    ));
                }

                // 2. Build and upload RouterOS Autonomous Monitoring Script
                fup_sync_routeros_scripts($API, $config);
            } catch (Exception $e) {
                // API operation error handled gracefully
            }
        }

        return true;
    }
}

if (!function_exists('fup_sync_routeros_scripts')) {
    function fup_sync_routeros_scripts($API, $config) {
        if (!is_object($API)) return;

        $hasActiveProfile = false;
        $profileRules = array();

        if (!empty($config['enabled']) && !empty($config['profiles'])) {
            foreach ($config['profiles'] as $pName => $pRule) {
                if (!empty($pRule['enabled']) && !empty($pRule['threshold_bytes']) && !empty($pRule['throttled_rate'])) {
                    $hasActiveProfile = true;
                    $profileRules[] = array(
                        'name' => $pName,
                        'threshold' => (float)$pRule['threshold_bytes'],
                        'rate' => trim($pRule['throttled_rate']),
                        'cycle' => $pRule['reset_cycle'] ?? 'manual'
                    );
                }
            }
        }

        $getMonScript = $API->comm('/system/script/print', array('?name' => 'nodera-fup-monitor'));
        $getMonSched = $API->comm('/system/scheduler/print', array('?name' => 'nodera-fup-monitor'));
        $getResetSched = $API->comm('/system/scheduler/print', array('?name' => 'nodera-fup-reset'));

        if (!$hasActiveProfile) {
            // Disable or remove existing scripts if FUP is disabled
            if (is_array($getMonSched) && !empty($getMonSched[0]['.id'])) {
                $API->comm('/system/scheduler/remove', array('.id' => $getMonSched[0]['.id']));
            }
            if (is_array($getResetSched) && !empty($getResetSched[0]['.id'])) {
                $API->comm('/system/scheduler/remove', array('.id' => $getResetSched[0]['.id']));
            }
            if (is_array($getMonScript) && !empty($getMonScript[0]['.id'])) {
                $API->comm('/system/script/set', array(
                    '.id' => $getMonScript[0]['.id'],
                    'source' => '# FUP Disabled'
                ));
            }
            return;
        }

        // Generate RouterOS Script body
        $rosScriptLines = array();
        $rosScriptLines[] = '# Nodera FUP Autonomous Monitor';
        $rosScriptLines[] = ':do {';
        $rosScriptLines[] = '  :foreach act in=[/ip hotspot active find] do={';
        $rosScriptLines[] = '    :local u [/ip hotspot active get $act user];';
        $rosScriptLines[] = '    :local uid [/ip hotspot user find where name=$u];';
        $rosScriptLines[] = '    :if ([:len $uid] > 0) do={';
        $rosScriptLines[] = '      :local p [/ip hotspot user get $uid profile];';
        $rosScriptLines[] = '      :local bin [/ip hotspot user get $uid bytes-in];';
        $rosScriptLines[] = '      :local bout [/ip hotspot user get $uid bytes-out];';
        $rosScriptLines[] = '      :local tot ($bin + $bout);';

        foreach ($profileRules as $rule) {
            $pEscaped = addcslashes($rule['name'], '"\\');
            $thresh = sprintf('%.0f', $rule['threshold']);
            $rate = addcslashes($rule['rate'], '"\\');
            $rosScriptLines[] = '      :if ($p = "' . $pEscaped . '" and $tot >= ' . $thresh . ') do={';
            $rosScriptLines[] = '        :local qid [/queue simple find where name=("<hotspot-" . $u . ">")];';
            $rosScriptLines[] = '        :if ([:len $qid] > 0) do={';
            $rosScriptLines[] = '          /queue simple set $qid max-limit="' . $rate . '";';
            $rosScriptLines[] = '        };';
            $rosScriptLines[] = '      };';
        }

        $rosScriptLines[] = '    };';
        $rosScriptLines[] = '  };';
        $rosScriptLines[] = '} on-error={};';

        $rosSource = implode("\n", $rosScriptLines);

        // Upload / Update Monitor Script
        if (is_array($getMonScript) && !empty($getMonScript[0]['.id'])) {
            $API->comm('/system/script/set', array(
                '.id' => $getMonScript[0]['.id'],
                'source' => $rosSource,
                'comment' => 'Nodera FUP Monitor Script'
            ));
        } else {
            $API->comm('/system/script/add', array(
                'name' => 'nodera-fup-monitor',
                'source' => $rosSource,
                'comment' => 'Nodera FUP Monitor Script',
                'policy' => 'read,write,policy,test'
            ));
        }

        // Upload / Update Monitor Scheduler (Runs every 5 minutes)
        if (is_array($getMonSched) && !empty($getMonSched[0]['.id'])) {
            $API->comm('/system/scheduler/set', array(
                '.id' => $getMonSched[0]['.id'],
                'interval' => '00:05:00',
                'on-event' => 'nodera-fup-monitor',
                'disabled' => 'no'
            ));
        } else {
            $API->comm('/system/scheduler/add', array(
                'name' => 'nodera-fup-monitor',
                'start-time' => '00:01:00',
                'interval' => '00:05:00',
                'on-event' => 'nodera-fup-monitor',
                'comment' => 'Nodera FUP Monitor Scheduler',
                'policy' => 'read,write,policy,test',
                'disabled' => 'no'
            ));
        }

        // Check Daily / Monthly Reset Schedulers
        $needsDaily = false;
        $needsMonthly = false;
        $dailyProfiles = array();
        $monthlyProfiles = array();

        foreach ($profileRules as $rule) {
            if ($rule['cycle'] === 'daily') {
                $needsDaily = true;
                $dailyProfiles[] = $rule['name'];
            } elseif ($rule['cycle'] === 'monthly') {
                $needsMonthly = true;
                $monthlyProfiles[] = $rule['name'];
            }
        }

        if ($needsDaily || $needsMonthly) {
            $resetLines = array();
            $resetLines[] = '# Nodera FUP Auto Reset';
            $resetLines[] = ':do {';
            $resetLines[] = '  :local date [/system clock get date];';
            $resetLines[] = '  :local day "";';
            $resetLines[] = '  :if ([:pick $date 4] = "-") do={ :set day [:pick $date 8 10]; } else={ :set day [:pick $date 4 6]; };';

            if (!empty($dailyProfiles)) {
                foreach ($dailyProfiles as $dp) {
                    $dpEsc = addcslashes($dp, '"\\');
                    $resetLines[] = '  :foreach u in=[/ip hotspot user find where profile="' . $dpEsc . '"] do={ /ip hotspot user reset-counters $u; };';
                }
            }
            if (!empty($monthlyProfiles)) {
                $resetLines[] = '  :if ($day = "01" or $day = "1") do={';
                foreach ($monthlyProfiles as $mp) {
                    $mpEsc = addcslashes($mp, '"\\');
                    $resetLines[] = '    :foreach u in=[/ip hotspot user find where profile="' . $mpEsc . '"] do={ /ip hotspot user reset-counters $u; };';
                }
                $resetLines[] = '  };';
            }
            $resetLines[] = '} on-error={};';
            $resetSource = implode("\n", $resetLines);

            // Script nodera-fup-reset
            $getResetScript = $API->comm('/system/script/print', array('?name' => 'nodera-fup-reset'));
            if (is_array($getResetScript) && !empty($getResetScript[0]['.id'])) {
                $API->comm('/system/script/set', array(
                    '.id' => $getResetScript[0]['.id'],
                    'source' => $resetSource
                ));
            } else {
                $API->comm('/system/script/add', array(
                    'name' => 'nodera-fup-reset',
                    'source' => $resetSource,
                    'policy' => 'read,write,policy,test'
                ));
            }

            // Scheduler nodera-fup-reset (Runs daily at 00:00:05)
            if (is_array($getResetSched) && !empty($getResetSched[0]['.id'])) {
                $API->comm('/system/scheduler/set', array(
                    '.id' => $getResetSched[0]['.id'],
                    'start-time' => '00:00:05',
                    'interval' => '1d',
                    'on-event' => 'nodera-fup-reset',
                    'disabled' => 'no'
                ));
            } else {
                $API->comm('/system/scheduler/add', array(
                    'name' => 'nodera-fup-reset',
                    'start-time' => '00:00:05',
                    'interval' => '1d',
                    'on-event' => 'nodera-fup-reset',
                    'comment' => 'Nodera FUP Daily/Monthly Reset Scheduler',
                    'policy' => 'read,write,policy,test',
                    'disabled' => 'no'
                ));
            }
        } else {
            if (is_array($getResetSched) && !empty($getResetSched[0]['.id'])) {
                $API->comm('/system/scheduler/remove', array('.id' => $getResetSched[0]['.id']));
            }
        }
    }
}

if (!function_exists('fup_sync_enforce_now')) {
    function fup_sync_enforce_now($session, $API) {
        if (!is_object($API)) {
            return array('status' => false, 'message' => 'API is not connected');
        }

        $config = fup_load_config($session, $API);
        if (empty($config['enabled']) || empty($config['profiles'])) {
            return array('status' => true, 'throttled' => 0, 'restored' => 0, 'message' => 'FUP is disabled');
        }

        // Fetch active users and queues
        $activeUsers = $API->comm('/ip/hotspot/active/print', array(
            '.proplist' => '.id,user,address'
        ));
        $activeUsers = is_array($activeUsers) ? $activeUsers : array();

        $queues = $API->comm('/queue/simple/print', array(
            '.proplist' => '.id,name,max-limit'
        ));
        $queues = is_array($queues) ? $queues : array();
        $queueMap = array();
        foreach ($queues as $q) {
            if (!empty($q['name'])) {
                $queueMap[$q['name']] = $q;
            }
        }

        // Fetch user profiles for default rate-limits
        $allProfiles = $API->comm('/ip/hotspot/user/profile/print', array(
            '.proplist' => 'name,rate-limit'
        ));
        $allProfiles = is_array($allProfiles) ? $allProfiles : array();
        $profileRateMap = array();
        foreach ($allProfiles as $pr) {
            if (!empty($pr['name'])) {
                $profileRateMap[$pr['name']] = $pr['rate-limit'] ?? '';
            }
        }

        $throttledCount = 0;
        $restoredCount = 0;

        foreach ($activeUsers as $act) {
            $uName = $act['user'] ?? '';
            if (empty($uName)) continue;

            $getUser = $API->comm('/ip/hotspot/user/print', array(
                '?name' => $uName,
                '.proplist' => '.id,name,profile,bytes-in,bytes-out'
            ));
            if (!is_array($getUser) || empty($getUser[0])) continue;

            $uData = $getUser[0];
            $uProfile = $uData['profile'] ?? '';
            $bytesIn = (float)($uData['bytes-in'] ?? 0);
            $bytesOut = (float)($uData['bytes-out'] ?? 0);
            $totalBytes = $bytesIn + $bytesOut;

            $qName = '<hotspot-' . $uName . '>';
            $queueItem = $queueMap[$qName] ?? null;
            if (!$queueItem) continue;

            $fupRule = $config['profiles'][$uProfile] ?? null;
            if ($fupRule && !empty($fupRule['enabled']) && !empty($fupRule['threshold_bytes'])) {
                $threshold = (float)$fupRule['threshold_bytes'];
                $throttledRate = trim($fupRule['throttled_rate'] ?? '1M/1M');
                $normalRate = trim($profileRateMap[$uProfile] ?? '');

                if ($totalBytes >= $threshold) {
                    // Exceeds threshold: downgrade speed
                    $API->comm('/queue/simple/set', array(
                        '.id' => $queueItem['.id'],
                        'max-limit' => $throttledRate
                    ));
                    $throttledCount++;
                } else {
                    // Below threshold: restore to normal rate if available
                    if (!empty($normalRate) && $queueItem['max-limit'] !== $normalRate) {
                        $API->comm('/queue/simple/set', array(
                            '.id' => $queueItem['.id'],
                            'max-limit' => $normalRate
                        ));
                        $restoredCount++;
                    }
                }
            }
        }

        return array(
            'status' => true,
            'throttled' => $throttledCount,
            'restored' => $restoredCount,
            'message' => 'FUP enforcement synced successfully'
        );
    }
}
