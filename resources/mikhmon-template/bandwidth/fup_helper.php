<?php
/*
 * Copyright (C) Nodera Mikhmon Bandwidth Management
 * FUP (Fair Usage Policy) & Traffic Control Engine
 */

if (!function_exists('fup_ensure_translations')) {
    function fup_ensure_translations($lang = null) {
        if ($lang === null) {
            $lang = $_SESSION['lang'] ?? $_SESSION['m_lang'] ?? $_COOKIE['mikhmon_lang'] ?? $GLOBALS['langid'] ?? 'id';
        }
        $lang = strtolower(trim((string)$lang));
        if (!in_array($lang, array('fr', 'en', 'es', 'tl', 'id'))) {
            $lang = 'id';
        }

        $langFile = __DIR__ . '/../lang/' . $lang . '.php';
        if (file_exists($langFile)) {
            @include_once $langFile;
        }

        $dictionaries = array(
            'fr' => array(
                '_bandwidth_management' => "Gestion de Bande Passante",
                '_top_tier_voucher' => "Top Vouchers",
                '_fup_settings' => "Paramètres FUP",
                '_bandwidth_leaderboard_sub' => "(Classement d'Utilisation de Bande Passante)",
                '_fup_title_filter_all' => "Cliquer pour afficher tous les vouchers",
                '_fup_total_vouchers' => "Total Vouchers Suivis",
                '_fup_title_filter_normal' => "Cliquer pour filtrer les vouchers normaux",
                '_fup_normal_vouchers' => "Normal (Sous FUP)",
                '_fup_title_filter_throttled' => "Cliquer pour filtrer les vouchers ayant atteint le FUP",
                '_fup_active_vouchers' => "Atteint FUP (Limité)",
                '_fup_total_traffic' => "Trafic Total Consommé",
                '_fup_settings_title' => "Configurer les seuils FUP par profil",
                '_fup_apply_now' => "Appliquer FUP Maintenant",
                '_fup_apply_now_title' => "Forcer l'évaluation de la vitesse FUP sur les utilisateurs actifs",
                '_fup_confirm_apply_now' => "Appliquer et synchroniser les règles de limitation FUP à toutes les connexions actives maintenant ?",
                '_fup_filter_profile' => "Filtrer par Profil :",
                '_fup_all_profiles' => "Tous les Profils",
                '_fup_stat_all' => "Tous",
                '_fup_stat_throttled' => "Atteint FUP",
                '_fup_stat_online' => "En Ligne",
                '_fup_stat_normal' => "Normal",
                '_fup_search_placeholder' => "Rechercher compte / IP / MAC...",
                '_fup_th_rank' => "Rang",
                '_fup_th_voucher' => "Coupon / Compte",
                '_fup_th_profile' => "Profil",
                '_fup_th_connection' => "Connexion",
                '_fup_th_fup_status' => "Statut FUP",
                '_fup_th_speed' => "Vitesse Active (DL / UL)",
                '_fup_th_download' => "Téléchargement (DL)",
                '_fup_th_upload' => "Téléversement (UL)",
                '_fup_th_total_quota' => "Quota Total",
                '_fup_th_progress_remain' => "Progression / Restant",
                '_fup_th_uptime' => "Temps Écoulé",
                '_fup_th_action' => "Action",
                '_fup_no_data' => "Aucun voucher trouvé pour le profil sélectionné.",
                '_fup_badge_online_desc' => "Actuellement connecté",
                '_fup_badge_online' => "EN LIGNE",
                '_fup_badge_offline' => "HORS LIGNE",
                '_fup_badge_throttled' => "ATTEINT FUP",
                '_fup_badge_throttled_desc' => "Seuil de quota dépassé ! Vitesse réduite à %s",
                '_fup_badge_warning' => "ALERTE (%s%%)",
                '_fup_badge_warning_desc' => "Consommation atteignant %s%% du seuil FUP",
                '_fup_badge_normal' => "NORMAL",
                '_fup_badge_normal_desc' => "Vitesse normale complète (%s%% du seuil FUP)",
                '_fup_badge_no_fup' => "Sans FUP",
                '_fup_remaining' => "Reste",
                '_fup_limit_prefix' => "Limite :",
                '_fup_btn_reset' => "Réinitialiser",
                '_fup_btn_reset_title' => "Réinitialiser la consommation de ce compte à 0 Mo",
                '_fup_confirm_reset' => "Réinitialiser la consommation totale du compte '%s' à 0 Mo ?",
                '_fup_btn_kick' => "Déconnecter",
                '_fup_btn_kick_title' => "Déconnecter la session active de cet utilisateur",
                '_fup_confirm_kick' => "Déconnecter la session active de l'utilisateur '%s' ?",
                '_fup_engine_info' => "<b>Moteur Fair Usage Policy (FUP) :</b> Ce système surveille automatiquement la consommation des coupons. Lorsque l'utilisateur atteint le seuil défini, le débit est immédiatement réduit (downgrade speed) sans déconnecter la session du client. Les scripts de surveillance s'exécutent de façon autonome 24h/24 et 7j/7 sur MikroTik RouterOS.",
                '_fup_back_top_tier' => "Retour au Top Vouchers",
                '_fup_form_title' => "Formulaire de Configuration des Règles FUP par Profil",
                '_fup_select_profile' => "Sélectionner le Profil Hotspot :",
                '_fup_select_profile_ph' => "Sélectionner un Profil",
                '_fup_normal_rate_lbl' => "Débit Normal :",
                '_fup_profile_status' => "Statut FUP pour ce Profil :",
                '_fup_opt_enabled' => "Actif (Appliquer le FUP)",
                '_fup_opt_disabled' => "Désactivé (Bypass FUP)",
                '_fup_threshold_label' => "Seuil Limite de Quota FUP :",
                '_fup_threshold_hint' => "Exemple : 10 GB ou 5000 MB",
                '_fup_throttled_rate_label' => "Vitesse Après FUP (Débit Réduit) :",
                '_fup_throttled_rate_hint' => "Format MikroTik : Rx/Tx (ex: 512k/512k ou 1M/1M)",
                '_fup_reset_cycle_label' => "Cycle de Réinitialisation du Quota :",
                '_fup_cycle_manual' => "Manuel (Uniquement via bouton réinitialiser / expiration)",
                '_fup_cycle_daily' => "Quotidien (Réinitialisation auto chaque jour à 00:00:00)",
                '_fup_cycle_monthly' => "Mensuel (Réinitialisation auto le 1er du mois)",
                '_fup_cycle_manual_short' => "Manuel",
                '_fup_cycle_daily_short' => "Quotidien (00:00)",
                '_fup_cycle_monthly_short' => "Mensuel (1er du mois)",
                '_fup_reset_cycle_hint' => "Fréquence de restauration du quota et de la vitesse normale",
                '_fup_save_sync' => "Enregistrer et Synchroniser sur MikroTik",
                '_fup_rules_list_title' => "Liste des Règles FUP Actuellement Configurées",
                '_fup_th_rule_profile' => "Nom du Profil",
                '_fup_th_rule_threshold' => "Seuil de Quota (Threshold)",
                '_fup_th_rule_normal_rate' => "Vitesse Normale",
                '_fup_th_rule_throttled_rate' => "Vitesse Après FUP",
                '_fup_th_rule_cycle' => "Cycle de Réinitialisation",
                '_fup_rules_empty' => "Aucune règle FUP n'a encore été configurée. Sélectionnez un profil ci-dessus pour en ajouter une.",
                '_fup_resync_btn' => "Resynchroniser les Scripts & Planificateurs avec RouterOS",
                '_fup_resync_btn_title' => "Renvoyer les scripts et planificateurs vers MikroTik RouterOS",
                '_fup_confirm_delete' => "Supprimer la règle FUP pour le profil '%s' ?",
                '_fup_msg_rule_saved' => "Règle FUP pour le profil '%s' enregistrée et synchronisée sur MikroTik avec succès.",
                '_fup_msg_rule_deleted' => "Règle FUP pour le profil '%s' supprimée avec succès.",
                '_fup_msg_resync_success' => "Tous les scripts et planificateurs FUP ont été resynchronisés avec succès sur MikroTik RouterOS.",
                '_fup_notice_reset_success' => "La consommation du compte '%s' a été réinitialisée avec succès à 0 Mo.",
                '_fup_notice_kick_success' => "La session active de l'utilisateur '%s' a été déconnectée avec succès.",
                '_fup_notice_eval_success' => "Évaluation FUP terminée : %s utilisateur(s) limité(s), %s utilisateur(s) rétabli(s) au débit normal.",
                '_active' => "Actif",
                '_deactive' => "Désactivé",
                '_edit' => "Modifier",
                '_delete' => "Supprimer",
                '_unlimited' => "Illimité"
            ),
            'en' => array(
                '_bandwidth_management' => "Bandwidth Management",
                '_top_tier_voucher' => "Top Tier Vouchers",
                '_fup_settings' => "FUP Settings",
                '_bandwidth_leaderboard_sub' => "(Bandwidth Usage Leaderboard)",
                '_fup_title_filter_all' => "Click to show all vouchers",
                '_fup_total_vouchers' => "Total Monitored Vouchers",
                '_fup_title_filter_normal' => "Click to filter Normal vouchers",
                '_fup_normal_vouchers' => "Normal (Under FUP)",
                '_fup_title_filter_throttled' => "Click to filter vouchers hitting FUP",
                '_fup_active_vouchers' => "Hitting FUP (Throttled)",
                '_fup_total_traffic' => "Total Traffic Consumed",
                '_fup_settings_title' => "Configure FUP thresholds per profile",
                '_fup_apply_now' => "Apply FUP Now",
                '_fup_apply_now_title' => "Force evaluate FUP rate limits on active users now",
                '_fup_confirm_apply_now' => "Apply and sync FUP speed limits to all active connections now?",
                '_fup_filter_profile' => "Filter by Profile :",
                '_fup_all_profiles' => "All Profiles",
                '_fup_stat_all' => "All",
                '_fup_stat_throttled' => "Hitting FUP",
                '_fup_stat_online' => "Online",
                '_fup_stat_normal' => "Normal",
                '_fup_search_placeholder' => "Search voucher / IP / MAC...",
                '_fup_th_rank' => "Rank",
                '_fup_th_voucher' => "Voucher / Account",
                '_fup_th_profile' => "Profile",
                '_fup_th_connection' => "Connection",
                '_fup_th_fup_status' => "FUP Status",
                '_fup_th_speed' => "Active Speed (DL / UL)",
                '_fup_th_download' => "Download",
                '_fup_th_upload' => "Upload",
                '_fup_th_total_quota' => "Total Quota",
                '_fup_th_progress_remain' => "Progress / Remaining",
                '_fup_th_uptime' => "Uptime",
                '_fup_th_action' => "Action",
                '_fup_no_data' => "No voucher data found for the selected profile.",
                '_fup_badge_online_desc' => "Currently logged in",
                '_fup_badge_online' => "ONLINE",
                '_fup_badge_offline' => "OFFLINE",
                '_fup_badge_throttled' => "FUP THROTTLED",
                '_fup_badge_throttled_desc' => "Quota threshold exceeded! Speed downgraded to %s",
                '_fup_badge_warning' => "WARNING (%s%%)",
                '_fup_badge_warning_desc' => "Bandwidth usage reached %s%% of FUP threshold",
                '_fup_badge_normal' => "NORMAL",
                '_fup_badge_normal_desc' => "Full normal speed (%s%% of FUP)",
                '_fup_badge_no_fup' => "No FUP",
                '_fup_remaining' => "Remaining",
                '_fup_limit_prefix' => "Limit:",
                '_fup_btn_reset' => "Reset",
                '_fup_btn_reset_title' => "Reset quota usage of this account to 0 MB",
                '_fup_confirm_reset' => "Reset total usage of account '%s' back to 0 MB?",
                '_fup_btn_kick' => "Kick",
                '_fup_btn_kick_title' => "Disconnect active session of this user",
                '_fup_confirm_kick' => "Kick and disconnect active session of user '%s'?",
                '_fup_engine_info' => "<b>Fair Usage Policy (FUP) Engine:</b> Automatically monitors voucher quota usage. When a user reaches their threshold, bandwidth speed is downgraded without disconnecting client session. Monitor scripts run autonomously 24/7 on MikroTik RouterOS.",
                '_fup_back_top_tier' => "Back to Top Vouchers",
                '_fup_form_title' => "FUP Rules Configuration per Profile",
                '_fup_select_profile' => "Select Hotspot Profile :",
                '_fup_select_profile_ph' => "Select Profile",
                '_fup_normal_rate_lbl' => "Normal Rate :",
                '_fup_profile_status' => "FUP Status for this Profile :",
                '_fup_opt_enabled' => "Enabled (Apply FUP)",
                '_fup_opt_disabled' => "Disabled (Bypass FUP)",
                '_fup_threshold_label' => "FUP Quota Threshold :",
                '_fup_threshold_hint' => "Example: 10 GB or 5000 MB",
                '_fup_throttled_rate_label' => "Speed After FUP (Throttled Rate) :",
                '_fup_throttled_rate_hint' => "MikroTik format: Rx/Tx (example: 512k/512k or 1M/1M)",
                '_fup_reset_cycle_label' => "Quota Usage Reset Cycle :",
                '_fup_cycle_manual' => "Manual (Only via reset button / expired)",
                '_fup_cycle_daily' => "Daily (Auto reset every 00:00:00)",
                '_fup_cycle_monthly' => "Monthly (Auto reset on 1st of month)",
                '_fup_cycle_manual_short' => "Manual",
                '_fup_cycle_daily_short' => "Daily (00:00)",
                '_fup_cycle_monthly_short' => "Monthly (1st of month)",
                '_fup_reset_cycle_hint' => "Schedule for restoring quota and normal speed",
                '_fup_save_sync' => "Save & Sync to MikroTik",
                '_fup_rules_list_title' => "Currently Configured FUP Rules",
                '_fup_th_rule_profile' => "Profile Name",
                '_fup_th_rule_threshold' => "Quota Threshold",
                '_fup_th_rule_normal_rate' => "Normal Rate",
                '_fup_th_rule_throttled_rate' => "Speed After FUP",
                '_fup_th_rule_cycle' => "Reset Cycle",
                '_fup_rules_empty' => "No FUP rules configured yet. Select a profile in the form above to add one.",
                '_fup_resync_btn' => "Resync Scripts & Schedulers with RouterOS",
                '_fup_resync_btn_title' => "Upload scripts and schedulers to MikroTik RouterOS",
                '_fup_confirm_delete' => "Delete FUP rule for profile '%s'?",
                '_fup_msg_rule_saved' => "FUP rule for profile '%s' saved and synced to MikroTik successfully.",
                '_fup_msg_rule_deleted' => "FUP rule for profile '%s' deleted successfully.",
                '_fup_msg_resync_success' => "All FUP scripts and schedulers successfully resynced to MikroTik RouterOS.",
                '_fup_notice_reset_success' => "Usage for account '%s' was successfully reset to 0 MB.",
                '_fup_notice_kick_success' => "Active session for user '%s' was successfully kicked.",
                '_fup_notice_eval_success' => "FUP evaluation finished: %s user(s) throttled, %s user(s) restored to normal rate.",
                '_active' => "Active",
                '_deactive' => "Disabled",
                '_edit' => "Edit",
                '_delete' => "Delete",
                '_unlimited' => "Unlimited"
            ),
            'id' => array(
                '_bandwidth_management' => "Management Bandwidth",
                '_top_tier_voucher' => "Top Tier Voucher",
                '_fup_settings' => "Pengaturan FUP",
                '_bandwidth_leaderboard_sub' => "(Leaderboard Penggunaan Bandwidth)",
                '_fup_title_filter_all' => "Klik untuk tampilkan semua voucher",
                '_fup_total_vouchers' => "Total Voucher Terpantau",
                '_fup_title_filter_normal' => "Klik untuk filter voucher Normal",
                '_fup_normal_vouchers' => "Normal (Belum FUP)",
                '_fup_title_filter_throttled' => "Klik untuk filter voucher Kena FUP",
                '_fup_active_vouchers' => "Kena FUP (Throttled)",
                '_fup_total_traffic' => "Total Kuota Terpakai",
                '_fup_settings_title' => "Atur Threshold FUP per Profile",
                '_fup_apply_now' => "Terapkan FUP Sekarang",
                '_fup_apply_now_title' => "Paksa evaluasi kecepatan FUP pada user aktif",
                '_fup_confirm_apply_now' => "Terapkan dan sinkronkan aturan kecepatan FUP ke seluruh koneksi aktif sekarang?",
                '_fup_filter_profile' => "Filter Profil :",
                '_fup_all_profiles' => "Semua Profil",
                '_fup_stat_all' => "Semua",
                '_fup_stat_throttled' => "Kena FUP",
                '_fup_stat_online' => "Online",
                '_fup_stat_normal' => "Normal",
                '_fup_search_placeholder' => "Cari user / IP / MAC...",
                '_fup_th_rank' => "Rank",
                '_fup_th_voucher' => "Voucher / Akun",
                '_fup_th_profile' => "Profil",
                '_fup_th_connection' => "Koneksi",
                '_fup_th_fup_status' => "Status FUP",
                '_fup_th_speed' => "Speed Aktif (DL / UL)",
                '_fup_th_download' => "Download",
                '_fup_th_upload' => "Upload",
                '_fup_th_total_quota' => "Total Kuota",
                '_fup_th_progress_remain' => "Progres / Sisa Kuota",
                '_fup_th_uptime' => "Uptime",
                '_fup_th_action' => "Aksi",
                '_fup_no_data' => "Tidak ada data voucher pada profil yang dipilih.",
                '_fup_badge_online_desc' => "Sedang Login",
                '_fup_badge_online' => "ONLINE",
                '_fup_badge_offline' => "OFFLINE",
                '_fup_badge_throttled' => "KENA FUP",
                '_fup_badge_throttled_desc' => "Batas kuota tembus! Kecepatan diturunkan ke %s",
                '_fup_badge_warning' => "SIAGA (%s%%)",
                '_fup_badge_warning_desc' => "Pemakaian kuota mencapai %s%% dari batas FUP",
                '_fup_badge_normal' => "NORMAL",
                '_fup_badge_normal_desc' => "Kecepatan penuh normal (%s%% dari FUP)",
                '_fup_badge_no_fup' => "Tanpa FUP",
                '_fup_remaining' => "Sisa",
                '_fup_limit_prefix' => "Limit:",
                '_fup_btn_reset' => "Reset",
                '_fup_btn_reset_title' => "Reset kuota pemakaian akun ini ke 0 MB",
                '_fup_confirm_reset' => "Reset total pemakaian akun '%s' kembali ke 0 MB?",
                '_fup_btn_kick' => "Kick",
                '_fup_btn_kick_title' => "Putuskan sesi aktif user ini",
                '_fup_confirm_kick' => "Kick dan putuskan sesi aktif user '%s'?",
                '_fup_engine_info' => "<b>Fair Usage Policy (FUP) Engine:</b> Sistem ini mengawasi pemakaian kuota voucher secara otomatis. Ketika pengguna mencapai batas kuota (threshold), kecepatan internet langsung diturunkan (downgrade speed) tanpa memutus sesi login pelanggan. Konfigurasi dan script monitor berjalan mandiri di MikroTik RouterOS 24/7.",
                '_fup_back_top_tier' => "Kembali ke Top Tier",
                '_fup_form_title' => "Form Konfigurasi Aturan FUP per Profil",
                '_fup_select_profile' => "Pilih Profil Hotspot :",
                '_fup_select_profile_ph' => "Pilih Profil",
                '_fup_normal_rate_lbl' => "Rate Normal :",
                '_fup_profile_status' => "Status FUP Profil Ini :",
                '_fup_opt_enabled' => "Aktif (Terapkan FUP)",
                '_fup_opt_disabled' => "Nonaktif (Bypass FUP)",
                '_fup_threshold_label' => "Batas Kuota Threshold FUP :",
                '_fup_threshold_hint' => "Contoh: 10 GB atau 5000 MB",
                '_fup_throttled_rate_label' => "Kecepatan Setelah FUP (Throttled Rate) :",
                '_fup_throttled_rate_hint' => "Format MikroTik: Rx/Tx (contoh: 512k/512k atau 1M/1M)",
                '_fup_reset_cycle_label' => "Siklus Reset Pemakaian Kuota :",
                '_fup_cycle_manual' => "Manual (Hanya via tombol reset / expired)",
                '_fup_cycle_daily' => "Harian (Otomatis reset tiap 00:00:00)",
                '_fup_cycle_monthly' => "Bulanan (Otomatis reset tiap tanggal 1)",
                '_fup_cycle_manual_short' => "Manual",
                '_fup_cycle_daily_short' => "Harian (00:00)",
                '_fup_cycle_monthly_short' => "Bulanan (Tgl 1)",
                '_fup_reset_cycle_hint' => "Jadwal pengembalian kuota dan kecepatan normal",
                '_fup_save_sync' => "Simpan & Sinkronkan ke MikroTik",
                '_fup_rules_list_title' => "Daftar Aturan FUP yang Terdaftar Saat Ini",
                '_fup_th_rule_profile' => "Nama Profil",
                '_fup_th_rule_threshold' => "Batas Kuota (Threshold)",
                '_fup_th_rule_normal_rate' => "Kecepatan Normal",
                '_fup_th_rule_throttled_rate' => "Kecepatan Setelah FUP",
                '_fup_th_rule_cycle' => "Siklus Reset",
                '_fup_rules_empty' => "Belum ada aturan FUP yang dikonfigurasi. Pilih profil di formulir atas untuk menambahkan.",
                '_fup_resync_btn' => "Sinkronkan Ulang Script & Scheduler ke RouterOS",
                '_fup_resync_btn_title' => "Unggah ulang script dan scheduler ke MikroTik RouterOS",
                '_fup_confirm_delete' => "Hapus aturan FUP untuk profil '%s'?",
                '_fup_msg_rule_saved' => "Aturan FUP untuk profil '%s' berhasil disimpan dan disinkronkan ke MikroTik.",
                '_fup_msg_rule_deleted' => "Aturan FUP untuk profil '%s' berhasil dihapus.",
                '_fup_msg_resync_success' => "Seluruh script dan scheduler FUP berhasil disinkronkan ulang ke RouterOS MikroTik.",
                '_fup_notice_reset_success' => "Pemakaian kuota akun '%s' berhasil direset kembali ke 0 MB.",
                '_fup_notice_kick_success' => "Sesi aktif user '%s' berhasil diputuskan (kick).",
                '_fup_notice_eval_success' => "Evaluasi FUP selesai: %s user diturunkan kecepatannya, %s user dinormalkan.",
                '_active' => "Aktif",
                '_deactive' => "Nonaktif",
                '_edit' => "Edit",
                '_delete' => "Hapus",
                '_unlimited' => "Unlimited"
            )
        );

        $dictionaries['es'] = array_merge($dictionaries['en'], array(
            '_bandwidth_management' => "Gestión de Ancho de Banda",
            '_top_tier_voucher' => "Top Vales",
            '_fup_settings' => "Configuración FUP",
            '_fup_total_vouchers' => "Total Vales Monitoreados",
            '_fup_normal_vouchers' => "Normal (Bajo FUP)",
            '_fup_active_vouchers' => "Alcanzado FUP (Limitado)",
            '_fup_total_traffic' => "Tráfico Total Consumido",
            '_fup_save_sync' => "Guardar y Sincronizar",
            '_active' => "Activo",
            '_deactive' => "Desactivado",
            '_edit' => "Editar",
            '_delete' => "Eliminar",
            '_unlimited' => "Ilimitado"
        ));
        $dictionaries['tl'] = array_merge($dictionaries['en'], array(
            '_bandwidth_management' => "Pamamahala ng Bandwidth",
            '_top_tier_voucher' => "Nangungunang mga Voucher",
            '_fup_settings' => "Mga Setting ng FUP",
            '_active' => "Aktibo",
            '_deactive' => "Hindi Aktibo",
            '_edit' => "I-edit",
            '_delete' => "Tanggalin",
            '_unlimited' => "Walang Limitasyon"
        ));

        $activeDict = isset($dictionaries[$lang]) ? $dictionaries[$lang] : $dictionaries['id'];

        foreach ($activeDict as $k => $v) {
            if ($lang !== 'id') {
                $GLOBALS[$k] = $v;
            } else {
                if (!isset($GLOBALS[$k]) || $GLOBALS[$k] === '') {
                    $GLOBALS[$k] = $v;
                }
            }
        }

        return $activeDict;
    }
}

if (!function_exists('fup_t')) {
    function fup_t($key, $default = '') {
        $varName = ltrim($key, '$');
        if (isset($GLOBALS[$varName]) && $GLOBALS[$varName] !== '') {
            return $GLOBALS[$varName];
        }
        $lang = $_SESSION['lang'] ?? $_SESSION['m_lang'] ?? $_COOKIE['mikhmon_lang'] ?? $GLOBALS['langid'] ?? 'id';
        $dict = fup_ensure_translations($lang);
        return isset($dict[$varName]) ? $dict[$varName] : $default;
    }
}

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
