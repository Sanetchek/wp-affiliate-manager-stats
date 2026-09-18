<?php
/**
 * Smoke tests for WP Affiliate Manager Stats plugin.
 *
 * Run: php tests/smoke/TbAffiliateStatsTest.php
 *
 * @package WPAM_Affiliate_Stats
 */

declare(strict_types=1);

$failed = 0;
$root = dirname(__DIR__, 2);

$required = [
    'wp-affiliate-manager-stats.php',
    'includes/class-tb-aff-stats-query.php',
    'includes/class-tb-aff-stats-attribution.php',
    'includes/class-tb-aff-stats-commission.php',
    'includes/class-tb-aff-stats-admin-list.php',
    'includes/class-tb-aff-stats-admin-detail.php',
    'includes/class-tb-aff-stats-partner.php',
    'includes/class-tb-aff-stats-export.php',
    'includes/class-tb-aff-stats-docs.php',
    'includes/class-tb-aff-stats-list-table.php',
    'includes/trait-tb-aff-stats-list-table.php',
    'templates/admin/affiliates_list.php',
    'templates/affiliate_cp_home.php',
    'docs/developers.md',
    'assets/admin-affiliate-stats.css',
    'assets/partner-affiliate-stats.css',
];

foreach ($required as $rel) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing file: {$rel}\n");
        $failed++;
    }
}

$bootstrap = (string) file_get_contents($root . '/wp-affiliate-manager-stats.php');
foreach (
    [
        'Plugin Name: WP Affiliate Manager Stats',
        'Text Domain: wpam-aff-stats',
        'Requires Plugins: affiliates-manager, paid-memberships-pro',
        'TB_WPAM_REFERRER_META_KEY',
        "add_action('plugins_loaded', 'tb_aff_stats_bootstrap'",
        'tb_aff_stats_filter_template_files',
        'class-tb-aff-stats-query.php',
        'class-tb-aff-stats-attribution.php',
        'class-tb-aff-stats-commission.php',
        'TB_Aff_Stats_Commission::init',
        'class-tb-aff-stats-docs.php',
        'TB_Aff_Stats_Docs::init',
    ] as $needle
) {
    if (strpos($bootstrap, $needle) === false) {
        fwrite(STDERR, "Bootstrap missing: {$needle}\n");
        $failed++;
    }
}

$docs = (string) file_get_contents($root . '/includes/class-tb-aff-stats-docs.php');
foreach (
    [
        'plugin_action_links_',
        'Documentation',
        'wpam-aff-stats-docs',
        'markdown_to_html',
        'docs/developers.md',
    ] as $needle
) {
    if (strpos($docs, $needle) === false) {
        fwrite(STDERR, "Docs class missing: {$needle}\n");
        $failed++;
    }
}

$devMd = (string) file_get_contents($root . '/docs/developers.md');
foreach (
    [
        'Developer documentation',
        'pmpro_after_checkout',
        'wpam_process_affiliate_commission',
        'tb_aff_stats_enable_pmpro_commission',
        'tb_affiliate_stats_overview',
    ] as $needle
) {
    if (strpos($devMd, $needle) === false) {
        fwrite(STDERR, "developers.md missing: {$needle}\n");
        $failed++;
    }
}

// Pure markdown converter smoke (no WP bootstrap).
if (!function_exists('esc_html')) {
    function esc_html($t)
    {
        return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
    }
}
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}
if (!defined('TB_AFF_STATS_PLUGIN_FILE')) {
    define('TB_AFF_STATS_PLUGIN_FILE', $root . '/wp-affiliate-manager-stats.php');
}
if (!defined('TB_AFF_STATS_PLUGIN_DIR')) {
    define('TB_AFF_STATS_PLUGIN_DIR', $root . '/');
}
require_once $root . '/includes/class-tb-aff-stats-docs.php';
$sample = TB_Aff_Stats_Docs::markdown_to_html("# Title\n\nHello **world** and `code`.\n\n```php\necho 1;\n```\n");
if (strpos($sample, '<h1>') === false || strpos($sample, '<strong>world</strong>') === false || strpos($sample, '<pre>') === false) {
    fwrite(STDERR, "markdown_to_html failed basic conversion\n");
    $failed++;
}

$commission = (string) file_get_contents($root . '/includes/class-tb-aff-stats-commission.php');
foreach (
    [
        'tb_aff_stats_enable_pmpro_commission',
        "add_action('pmpro_after_checkout'",
        'wpam_process_affiliate_commission',
        'level_is_paid',
        'award_commission',
        'tb_wpam_award_on_pmpro_after_checkout',
    ] as $needle
) {
    if (strpos($commission, $needle) === false) {
        fwrite(STDERR, "Commission missing: {$needle}\n");
        $failed++;
    }
}

$attr = (string) file_get_contents($root . '/includes/class-tb-aff-stats-attribution.php');
foreach (
    [
        "add_action('user_register'",
        'tb_wpam_persist_referrer_for_user',
        'persist_referrer',
        'wpam_id',
    ] as $needle
) {
    if (strpos($attr, $needle) === false) {
        fwrite(STDERR, "Attribution missing: {$needle}\n");
        $failed++;
    }
}

$query = (string) file_get_contents($root . '/includes/class-tb-aff-stats-query.php');
foreach (
    [
        'function empty_stats',
        'function paid_pmpro_level_ids',
        'function get_stats',
        'function get_stats_for_affiliates',
        'function format_percent',
        'function format_epc',
        'function query_referred_users',
        'unique_visits',
        'signup_to_paid',
        'click_to_signup',
        'click_to_paid',
    ] as $needle
) {
    if (strpos($query, $needle) === false) {
        fwrite(STDERR, "Query class missing: {$needle}\n");
        $failed++;
    }
}

$adminList = (string) file_get_contents($root . '/includes/class-tb-aff-stats-admin-list.php');
foreach (
    [
        "add_filter('tb_wpam_affiliates_list_table_class'",
        'TB_Aff_Stats_List_Table',
        'define_list_table_if_needed',
    ] as $needle
) {
    if (strpos($adminList, $needle) === false) {
        fwrite(STDERR, "Admin list missing: {$needle}\n");
        $failed++;
    }
}

$detail = (string) file_get_contents($root . '/includes/class-tb-aff-stats-admin-detail.php');
foreach (
    [
        "add_action('all_admin_notices'",
        'Referral funnel',
        'tb_ref_paged',
        'query_referred_users',
    ] as $needle
) {
    if (strpos($detail, $needle) === false) {
        fwrite(STDERR, "Admin detail missing: {$needle}\n");
        $failed++;
    }
}

$partner = (string) file_get_contents($root . '/includes/class-tb-aff-stats-partner.php');
foreach (
    [
        "add_action('tb_affiliate_stats_overview'",
        'All time',
        'Signup → Paid',
        'EPC',
    ] as $needle
) {
    if (strpos($partner, $needle) === false) {
        fwrite(STDERR, "Partner UI missing: {$needle}\n");
        $failed++;
    }
}

$export = (string) file_get_contents($root . '/includes/class-tb-aff-stats-export.php');
foreach (
    [
        "add_action('admin_init'",
        'wpam-export-affiliates-to-csv',
        'unique_visits',
        'click_to_paid',
        'wp_verify_nonce',
    ] as $needle
) {
    if (strpos($export, $needle) === false) {
        fwrite(STDERR, "Export missing: {$needle}\n");
        $failed++;
    }
}

$cssAdmin = (string) file_get_contents($root . '/assets/admin-affiliate-stats.css');
foreach (['tb-aff-funnel-cell', 'tb-aff-stats-cards', 'tb-aff-stats-detail', 'grid-template-columns'] as $needle) {
    if (strpos($cssAdmin, $needle) === false) {
        fwrite(STDERR, "Admin CSS missing: {$needle}\n");
        $failed++;
    }
}

$cssPartner = (string) file_get_contents($root . '/assets/partner-affiliate-stats.css');
foreach (['tb-aff-stats-partner', 'tb-aff-stats-card__value', 'auto-fit'] as $needle) {
    if (strpos($cssPartner, $needle) === false) {
        fwrite(STDERR, "Partner CSS missing: {$needle}\n");
        $failed++;
    }
}

// Pure formatter behavior (stub WP i18n).
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}
if (!function_exists('__')) {
    function __($t, $d = null)
    {
        return $t;
    }
}
if (!function_exists('number_format_i18n')) {
    function number_format_i18n($number, $decimals = 0)
    {
        return number_format((float) $number, (int) $decimals, '.', '');
    }
}
if (!function_exists('wpam_format_money')) {
    function wpam_format_money($amount, $add_currency = true)
    {
        return '$' . number_format((float) $amount, 2, '.', '');
    }
}

require_once $root . '/includes/class-tb-aff-stats-query.php';

if (TB_Aff_Stats_Query::format_percent(0.0) !== '0%') {
    fwrite(STDERR, "format_percent(0) should be 0%\n");
    $failed++;
}
if (TB_Aff_Stats_Query::format_percent(0.25) !== '25.0%') {
    fwrite(STDERR, 'format_percent(0.25) should be 25.0%, got ' . TB_Aff_Stats_Query::format_percent(0.25) . "\n");
    $failed++;
}
if (TB_Aff_Stats_Query::format_epc(1.2, 0) !== '—') {
    fwrite(STDERR, "format_epc with 0 visits should be em dash\n");
    $failed++;
}
if (TB_Aff_Stats_Query::format_epc(1.2, 10) !== '$1.20') {
    fwrite(STDERR, 'format_epc(1.2,10) should be $1.20, got ' . TB_Aff_Stats_Query::format_epc(1.2, 10) . "\n");
    $failed++;
}

$empty = TB_Aff_Stats_Query::empty_stats();
foreach (['visits', 'signups', 'free', 'paid', 'epc', 'signup_to_paid'] as $key) {
    if (!array_key_exists($key, $empty)) {
        fwrite(STDERR, "empty_stats missing key {$key}\n");
        $failed++;
    }
}

// Theme bridges (outside plugin).
$theme = dirname($root, 2) . '/themes/Tipsbattle';
$list = $theme . '/affiliates-manager/admin/affiliates_list.php';
$home = $theme . '/affiliates-manager/affiliate_cp_home.php';
if (is_readable($list)) {
    $listSrc = (string) file_get_contents($list);
    if (strpos($listSrc, "apply_filters('tb_wpam_affiliates_list_table_class'") === false) {
        fwrite(STDERR, "Theme affiliates_list.php missing tb_wpam_affiliates_list_table_class filter\n");
        $failed++;
    }
} else {
    fwrite(STDERR, "Theme affiliates_list.php not readable (skip bridge check)\n");
}
if (is_readable($home)) {
    $homeSrc = (string) file_get_contents($home);
    if (strpos($homeSrc, "do_action('tb_affiliate_stats_overview')") === false) {
        fwrite(STDERR, "Theme affiliate_cp_home.php missing tb_affiliate_stats_overview action\n");
        $failed++;
    }
} else {
    fwrite(STDERR, "Theme affiliate_cp_home.php not readable (skip bridge check)\n");
}

if ($failed > 0) {
    fwrite(STDERR, "TbAffiliateStatsTest FAILED ({$failed})\n");
    exit(1);
}

fwrite(STDOUT, "TbAffiliateStatsTest OK\n");
exit(0);
