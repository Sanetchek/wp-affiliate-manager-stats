<?php
/**
 * Plugin Name: WP Affiliate Manager Stats
 * Description: Compact referral funnel stats for WP Affiliate Manager — Click → Signup → Paid, EPC, and readable admin/partner cards.
 * Version: 1.2.0
 * Author: Sanetchek
 * Author URI: https://github.com/Sanetchek
 * Text Domain: wpam-aff-stats
 * Requires Plugins: affiliates-manager, paid-memberships-pro
 *
 * @package WPAM_Affiliate_Stats
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('TB_AFF_STATS_VERSION', '1.2.0');
define('TB_AFF_STATS_PLUGIN_FILE', __FILE__);
define('TB_AFF_STATS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TB_AFF_STATS_PLUGIN_URL', plugin_dir_url(__FILE__));

/** Same first-touch meta key as typical theme purchase bridges. */
if (!defined('TB_WPAM_REFERRER_META_KEY')) {
    define('TB_WPAM_REFERRER_META_KEY', 'tb_wpam_referrer_id');
}

require_once TB_AFF_STATS_PLUGIN_DIR . 'includes/class-tb-aff-stats-query.php';
require_once TB_AFF_STATS_PLUGIN_DIR . 'includes/class-tb-aff-stats-attribution.php';
require_once TB_AFF_STATS_PLUGIN_DIR . 'includes/class-tb-aff-stats-commission.php';
require_once TB_AFF_STATS_PLUGIN_DIR . 'includes/class-tb-aff-stats-admin-list.php';
require_once TB_AFF_STATS_PLUGIN_DIR . 'includes/class-tb-aff-stats-admin-detail.php';
require_once TB_AFF_STATS_PLUGIN_DIR . 'includes/class-tb-aff-stats-partner.php';
require_once TB_AFF_STATS_PLUGIN_DIR . 'includes/class-tb-aff-stats-export.php';
require_once TB_AFF_STATS_PLUGIN_DIR . 'includes/class-tb-aff-stats-docs.php';

/**
 * Whether WP Affiliate Manager is available.
 */
function tb_aff_stats_wpam_ready(): bool
{
    return defined('WPAM_VERSION') || class_exists('WPAM_Plugin', false);
}

/**
 * Bootstrap after WPAM / PMPro have loaded.
 */
function tb_aff_stats_bootstrap(): void
{
    // Docs + Plugins-screen link always (even if WPAM temporarily missing).
    TB_Aff_Stats_Docs::init();

    if (!tb_aff_stats_wpam_ready()) {
        return;
    }

    TB_Aff_Stats_Attribution::init();
    TB_Aff_Stats_Commission::init();
    TB_Aff_Stats_Admin_List::init();
    TB_Aff_Stats_Admin_Detail::init();
    TB_Aff_Stats_Partner::init();
    TB_Aff_Stats_Export::init();

    add_filter('wpam_load_template_files', 'tb_aff_stats_filter_template_files', 20, 2);
}

/**
 * Insert plugin WPAM templates just before the stock plugin path (theme still wins).
 *
 * @param list<string> $template_files
 * @return list<string>
 */
function tb_aff_stats_filter_template_files(array $template_files, string $template_name): array
{
    $map = [
        'admin/affiliates_list.php' => TB_AFF_STATS_PLUGIN_DIR . 'templates/admin/affiliates_list.php',
        'affiliate_cp_home.php' => TB_AFF_STATS_PLUGIN_DIR . 'templates/affiliate_cp_home.php',
    ];

    if (!isset($map[$template_name]) || !is_readable($map[$template_name])) {
        return $template_files;
    }

    $ours = $map[$template_name];
    $out = [];
    $inserted = false;
    foreach ($template_files as $file) {
        if (!$inserted && is_string($file) && str_contains($file, '/affiliates-manager/html/')) {
            $out[] = $ours;
            $inserted = true;
        }
        $out[] = $file;
    }
    if (!$inserted) {
        $out[] = $ours;
    }

    return $out;
}

add_action('plugins_loaded', 'tb_aff_stats_bootstrap', 30);
