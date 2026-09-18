<?php
/**
 * My Affiliates list — compact Funnel column.
 *
 * @package WPAM_Affiliate_Stats
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class TB_Aff_Stats_Admin_List
{
    public static function init(): void
    {
        add_filter('tb_wpam_affiliates_list_table_class', [self::class, 'filter_table_class']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
    }

    /**
     * Prefer our Funnel-aware subclass when the theme filter is present.
     */
    public static function filter_table_class(string $class): string
    {
        self::define_list_table_if_needed();

        if (class_exists('TB_Aff_Stats_List_Table', false)) {
            return 'TB_Aff_Stats_List_Table';
        }

        return $class;
    }

    public static function enqueue_admin_assets(string $hook): void
    {
        if (!isset($_GET['page']) || sanitize_text_field(wp_unslash((string) $_GET['page'])) !== 'wpam-affiliates') {
            return;
        }

        wp_enqueue_style(
            'tb-aff-stats-admin',
            TB_AFF_STATS_PLUGIN_URL . 'assets/admin-affiliate-stats.css',
            [],
            TB_AFF_STATS_VERSION
        );
    }

    /**
     * Define list-table subclass once parent WPAM / theme table is available.
     */
    public static function define_list_table_if_needed(): void
    {
        if (class_exists('TB_Aff_Stats_List_Table', false)) {
            return;
        }

        if (!class_exists('WPAM_List_Affiliates_Table', false) && defined('WPAM_BASE_DIRECTORY')) {
            $list_file = WPAM_BASE_DIRECTORY . '/classes/ListAffiliatesTable.php';
            if (is_readable($list_file)) {
                include_once $list_file;
            }
        }

        if (function_exists('tb_wpam_define_list_affiliates_table_if_needed')) {
            tb_wpam_define_list_affiliates_table_if_needed();
        }

        require_once TB_AFF_STATS_PLUGIN_DIR . 'includes/class-tb-aff-stats-list-table.php';
    }
}
