<?php
/**
 * WPAM list-table subclass with Funnel column (loaded after parent exists).
 *
 * @package WPAM_Affiliate_Stats
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('TB_Aff_Stats_List_Table', false)) {
    return;
}

require_once __DIR__ . '/trait-tb-aff-stats-list-table.php';

if (class_exists('TB_Wpam_List_Affiliates_Table', false)) {
    /**
     * Extends theme Type/Amount table.
     */
    class TB_Aff_Stats_List_Table extends TB_Wpam_List_Affiliates_Table
    {
        use TB_Aff_Stats_List_Table_Trait;
    }

    return;
}

if (class_exists('WPAM_List_Affiliates_Table', false)) {
    /**
     * Extends stock WPAM table when theme subclass is unavailable.
     */
    class TB_Aff_Stats_List_Table extends WPAM_List_Affiliates_Table
    {
        use TB_Aff_Stats_List_Table_Trait;

        /**
         * @return array<string, string>
         */
        public function get_columns()
        {
            return [
                'cb' => '<input type="checkbox" />',
                'affiliateId' => __('Affiliate ID', 'affiliates-manager'),
                'status' => __('Status', 'affiliates-manager'),
                'balance' => __('Balance', 'affiliates-manager'),
                'earnings' => __('Earnings', 'affiliates-manager'),
                'affiliateName' => __('Name', 'wpam-aff-stats'),
                'email' => __('Email', 'affiliates-manager'),
                'bountyType' => __('Type', 'wpam-aff-stats'),
                'bountyAmount' => __('Amount', 'wpam-aff-stats'),
                'funnel' => __('Funnel', 'wpam-aff-stats'),
                'dateCreated' => __('Date Joined', 'affiliates-manager'),
                'viewDetail' => '',
            ];
        }

        /**
         * @param array<string, mixed> $item
         */
        public function column_bountyType($item): string
        {
            if (function_exists('tb_format_wpam_bounty_type_label')) {
                $label = tb_format_wpam_bounty_type_label((string) ($item['bountyType'] ?? ''));

                return esc_html($label !== '' ? $label : '—');
            }

            return esc_html((string) ($item['bountyType'] ?? '—'));
        }

        /**
         * @param array<string, mixed> $item
         */
        public function column_bountyAmount($item): string
        {
            if (function_exists('tb_format_wpam_bounty_amount_label')) {
                $label = tb_format_wpam_bounty_amount_label(
                    (string) ($item['bountyType'] ?? ''),
                    (string) ($item['bountyAmount'] ?? '')
                );

                return esc_html($label !== '' ? $label : '—');
            }

            return esc_html((string) ($item['bountyAmount'] ?? '—'));
        }
    }
}
