<?php
/**
 * CSV export intercept with full funnel stats.
 *
 * @package WPAM_Affiliate_Stats
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class TB_Aff_Stats_Export
{
    public static function init(): void
    {
        add_action('admin_init', [self::class, 'maybe_export'], 9);
        add_filter('wpam_export_columns', [self::class, 'filter_export_columns'], 20);
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public static function filter_export_columns(array $columns): array
    {
        $columns['visits'] = __('Visits', 'wpam-aff-stats');
        $columns['unique_visits'] = __('Unique visits', 'wpam-aff-stats');
        $columns['signups'] = __('Signups', 'wpam-aff-stats');
        $columns['free'] = __('Free', 'wpam-aff-stats');
        $columns['paid'] = __('Paid', 'wpam-aff-stats');
        $columns['advanced'] = __('Advanced', 'wpam-aff-stats');
        $columns['pro'] = __('Pro', 'wpam-aff-stats');
        $columns['click_to_signup'] = __('Click→Signup %', 'wpam-aff-stats');
        $columns['signup_to_paid'] = __('Signup→Paid %', 'wpam-aff-stats');
        $columns['click_to_paid'] = __('Click→Paid %', 'wpam-aff-stats');
        $columns['epc'] = __('EPC', 'wpam-aff-stats');

        return $columns;
    }

    public static function maybe_export(): void
    {
        if (!isset($_POST['wpam-export-affiliates-to-csv'])) {
            return;
        }

        if (!current_user_can('manage_options') && !current_user_can('wpam_admin')) {
            return;
        }

        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['_wpnonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'wpam-export-affiliates-to-csv-nonce')) {
            return;
        }

        if (!defined('WPAM_BASE_DIRECTORY')) {
            return;
        }

        include_once WPAM_BASE_DIRECTORY . '/classes/ListAffiliatesTable.php';
        TB_Aff_Stats_Admin_List::define_list_table_if_needed();

        $table_class = class_exists('TB_Aff_Stats_List_Table', false)
            ? 'TB_Aff_Stats_List_Table'
            : (class_exists('TB_Wpam_List_Affiliates_Table', false)
                ? 'TB_Wpam_List_Affiliates_Table'
                : 'WPAM_List_Affiliates_Table');

        /** @var WPAM_List_Affiliates_Table $affiliates */
        $affiliates = new $table_class();
        $affiliates->prepare_items(true);

        $items = is_array($affiliates->items) ? $affiliates->items : [];
        $ids = [];
        foreach ($items as $item) {
            $aid = (int) ($item['affiliateId'] ?? 0);
            if ($aid > 0) {
                $ids[] = $aid;
            }
        }
        $stats_map = TB_Aff_Stats_Query::get_stats_for_affiliates($ids);

        foreach ($items as $index => $item) {
            $aid = (int) ($item['affiliateId'] ?? 0);
            $stats = $stats_map[$aid] ?? TB_Aff_Stats_Query::empty_stats();
            $items[$index]['visits'] = (string) (int) $stats['visits'];
            $items[$index]['unique_visits'] = (string) (int) $stats['unique_visits'];
            $items[$index]['signups'] = (string) (int) $stats['signups'];
            $items[$index]['free'] = (string) (int) $stats['free'];
            $items[$index]['paid'] = (string) (int) $stats['paid'];
            $items[$index]['advanced'] = (string) (int) $stats['advanced'];
            $items[$index]['pro'] = (string) (int) $stats['pro'];
            $items[$index]['click_to_signup'] = TB_Aff_Stats_Query::format_percent((float) $stats['click_to_signup']);
            $items[$index]['signup_to_paid'] = TB_Aff_Stats_Query::format_percent((float) $stats['signup_to_paid']);
            $items[$index]['click_to_paid'] = TB_Aff_Stats_Query::format_percent((float) $stats['click_to_paid']);
            $items[$index]['epc'] = TB_Aff_Stats_Query::format_epc((float) $stats['epc'], (int) $stats['visits']);
            if (function_exists('tb_format_wpam_bounty_type_label') && isset($item['bountyType'])) {
                $items[$index]['bountyType'] = tb_format_wpam_bounty_type_label((string) $item['bountyType']);
            }
            if (function_exists('tb_format_wpam_bounty_amount_label') && isset($item['bountyType'], $item['bountyAmount'])) {
                $items[$index]['bountyAmount'] = tb_format_wpam_bounty_amount_label(
                    (string) $item['bountyType'],
                    (string) $item['bountyAmount']
                );
            }
        }

        $export_keys = [
            'affiliateId' => 'Affiliate ID',
            'status' => 'Status',
            'balance' => 'Balance',
            'earnings' => 'Earnings',
            'firstName' => 'First Name',
            'lastName' => 'Last Name',
            'email' => 'Email',
            'companyName' => 'Company',
            'dateCreated' => 'Date Joined',
            'websiteUrl' => 'Website',
            'phoneNumber' => 'Phone',
        ];
        $export_keys = apply_filters('wpam_export_columns', $export_keys);

        self::output_csv($items, $export_keys, 'MyAffiliates.csv');
        exit;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, string> $export_keys
     */
    private static function output_csv(array $items, array $export_keys, string $filename): void
    {
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        if ($output === false) {
            return;
        }

        fputcsv($output, array_values($export_keys));
        foreach ($items as $item) {
            $line = [];
            foreach ($export_keys as $key => $_label) {
                $line[] = isset($item[$key]) ? (string) $item[$key] : '';
            }
            fputcsv($output, $line);
        }

        fclose($output);
    }
}
