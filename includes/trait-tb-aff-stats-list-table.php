<?php
/**
 * Shared Funnel column behavior for WPAM affiliate list tables.
 *
 * @package TipsBattle_Affiliate_Stats
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

trait TB_Aff_Stats_List_Table_Trait
{
    /**
     * @return array<string, string>
     */
    public function get_columns()
    {
        $columns = parent::get_columns();
        $rebuilt = [];

        foreach ($columns as $key => $label) {
            if ($key === 'firstName') {
                $rebuilt['affiliateName'] = __('Name', 'tipsbattle-aff-stats');
                continue;
            }
            if ($key === 'lastName') {
                continue;
            }
            $rebuilt[$key] = $label;
            if ($key === 'bountyAmount' || ($key === 'websiteUrl' && !isset($rebuilt['funnel']))) {
                $rebuilt['funnel'] = __('Funnel', 'tipsbattle-aff-stats');
            }
        }

        if (!isset($rebuilt['funnel'])) {
            $rebuilt['funnel'] = __('Funnel', 'tipsbattle-aff-stats');
        }

        return $rebuilt;
    }

    /**
     * @param bool $ignore_pagination
     */
    public function prepare_items($ignore_pagination = false)
    {
        parent::prepare_items($ignore_pagination);

        if (empty($this->items) || !is_array($this->items)) {
            return;
        }

        $ids = [];
        foreach ($this->items as $item) {
            $aid = (int) ($item['affiliateId'] ?? 0);
            if ($aid > 0) {
                $ids[] = $aid;
            }
        }

        $stats = TB_Aff_Stats_Query::get_stats_for_affiliates($ids);
        foreach ($this->items as $index => $item) {
            $aid = (int) ($item['affiliateId'] ?? 0);
            $this->items[$index]['_tb_aff_stats'] = $stats[$aid] ?? TB_Aff_Stats_Query::empty_stats();
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    public function column_affiliateName($item): string
    {
        $first = isset($item['firstName']) ? (string) $item['firstName'] : '';
        $last = isset($item['lastName']) ? (string) $item['lastName'] : '';
        $name = trim($first . ' ' . $last);

        return esc_html($name !== '' ? $name : '—');
    }

    /**
     * @param array<string, mixed> $item
     */
    public function column_funnel($item): string
    {
        $stats = isset($item['_tb_aff_stats']) && is_array($item['_tb_aff_stats'])
            ? $item['_tb_aff_stats']
            : TB_Aff_Stats_Query::empty_stats();

        $visits = (int) ($stats['visits'] ?? 0);
        $signups = (int) ($stats['signups'] ?? 0);
        $paid = (int) ($stats['paid'] ?? 0);
        $signup_to_paid = TB_Aff_Stats_Query::format_percent((float) ($stats['signup_to_paid'] ?? 0));
        $epc = TB_Aff_Stats_Query::format_epc((float) ($stats['epc'] ?? 0), $visits);

        $html = '<div class="tb-aff-funnel-cell">';
        $html .= '<div class="tb-aff-funnel-cell__flow">';
        $html .= esc_html((string) $visits) . ' → ' . esc_html((string) $signups) . ' → ' . esc_html((string) $paid);
        $html .= '</div>';
        $html .= '<div class="tb-aff-funnel-cell__meta">';
        $html .= esc_html__('clicks · signups · paid', 'tipsbattle-aff-stats');
        $html .= '</div>';
        $html .= '<div class="tb-aff-funnel-cell__rates">';
        $html .= esc_html(
            sprintf(
                /* translators: 1: signup-to-paid percent, 2: EPC amount */
                __('Signup→Paid %1$s · EPC %2$s', 'tipsbattle-aff-stats'),
                $signup_to_paid,
                $epc
            )
        );
        $html .= '</div></div>';

        return $html;
    }
}
