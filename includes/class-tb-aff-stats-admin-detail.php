<?php
/**
 * Affiliate detail — funnel metric cards + referred users table.
 *
 * @package WPAM_Affiliate_Stats
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class TB_Aff_Stats_Admin_Detail
{
    public static function init(): void
    {
        add_action('all_admin_notices', [self::class, 'render_detail_panel']);
    }

    private static function can_manage(): bool
    {
        return current_user_can('manage_options') || current_user_can('wpam_admin');
    }

    private static function current_affiliate_id(): int
    {
        if (!isset($_GET['page']) || sanitize_text_field(wp_unslash((string) $_GET['page'])) !== 'wpam-affiliates') {
            return 0;
        }
        if (!isset($_GET['viewDetail'])) {
            return 0;
        }

        return (int) sanitize_text_field(wp_unslash((string) $_GET['viewDetail']));
    }

    public static function render_detail_panel(): void
    {
        if (!self::can_manage()) {
            return;
        }

        $aff_id = self::current_affiliate_id();
        if ($aff_id <= 0) {
            return;
        }

        $stats = TB_Aff_Stats_Query::get_stats($aff_id);
        $page = isset($_GET['tb_ref_paged']) ? max(1, (int) $_GET['tb_ref_paged']) : 1;
        $referred = TB_Aff_Stats_Query::query_referred_users($aff_id, $page, 50);
        $total_pages = (int) max(1, (int) ceil($referred['total'] / 50));

        $cards = [
            [
                'label' => __('Clicks', 'wpam-aff-stats'),
                'value' => (string) (int) $stats['visits'],
            ],
            [
                'label' => __('Unique', 'wpam-aff-stats'),
                'value' => (string) (int) $stats['unique_visits'],
            ],
            [
                'label' => __('Signups', 'wpam-aff-stats'),
                'value' => (string) (int) $stats['signups'],
            ],
            [
                'label' => __('Free', 'wpam-aff-stats'),
                'value' => (string) (int) $stats['free'],
            ],
            [
                'label' => __('Paid', 'wpam-aff-stats'),
                'value' => (string) (int) $stats['paid'],
            ],
            [
                'label' => __('Advanced', 'wpam-aff-stats'),
                'value' => (string) (int) $stats['advanced'],
            ],
            [
                'label' => __('Pro', 'wpam-aff-stats'),
                'value' => (string) (int) $stats['pro'],
            ],
            [
                'label' => __('Click → Signup', 'wpam-aff-stats'),
                'value' => TB_Aff_Stats_Query::format_percent((float) $stats['click_to_signup']),
            ],
            [
                'label' => __('Signup → Paid', 'wpam-aff-stats'),
                'value' => TB_Aff_Stats_Query::format_percent((float) $stats['signup_to_paid']),
            ],
            [
                'label' => __('EPC', 'wpam-aff-stats'),
                'value' => TB_Aff_Stats_Query::format_epc((float) $stats['epc'], (int) $stats['visits']),
            ],
        ];

        echo '<div class="tb-aff-stats-detail wrap">';
        echo '<div class="tb-aff-stats-detail__header">';
        echo '<h2>' . esc_html__('Referral funnel', 'wpam-aff-stats') . '</h2>';
        echo '<p class="description">' . esc_html__(
            'Click → Signup → Paid funnel for this affiliate (current membership snapshot).',
            'wpam-aff-stats'
        ) . '</p>';
        echo '</div>';

        echo '<div class="tb-aff-stats-cards">';
        foreach ($cards as $card) {
            echo '<div class="tb-aff-stats-card">';
            echo '<div class="tb-aff-stats-card__value">' . esc_html($card['value']) . '</div>';
            echo '<div class="tb-aff-stats-card__label">' . esc_html($card['label']) . '</div>';
            echo '</div>';
        }
        echo '</div>';

        echo '<div class="tb-aff-stats-referred">';
        echo '<h3>' . esc_html__('Referred users', 'wpam-aff-stats') . '</h3>';

        if ($referred['items'] === []) {
            echo '<p>' . esc_html__('No referred signups attributed yet.', 'wpam-aff-stats') . '</p>';
        } else {
            echo '<table class="widefat striped tb-aff-stats-referred__table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('User', 'wpam-aff-stats') . '</th>';
            echo '<th>' . esc_html__('Email', 'wpam-aff-stats') . '</th>';
            echo '<th>' . esc_html__('Plan', 'wpam-aff-stats') . '</th>';
            echo '<th>' . esc_html__('Registered', 'wpam-aff-stats') . '</th>';
            echo '</tr></thead><tbody>';

            foreach ($referred['items'] as $row) {
                $edit = get_edit_user_link((int) $row['user_id']);
                $login = (string) $row['login'];
                $email = (string) $row['email'];
                $tier = (string) $row['tier'];
                $registered = (string) $row['registered'];
                $registered_label = $registered !== ''
                    ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($registered))
                    : '—';

                echo '<tr>';
                echo '<td>';
                if (is_string($edit) && $edit !== '') {
                    echo '<a href="' . esc_url($edit) . '">' . esc_html($login) . '</a>';
                } else {
                    echo esc_html($login);
                }
                echo '</td>';
                echo '<td>' . esc_html($email) . '</td>';
                echo '<td>' . esc_html($tier) . '</td>';
                echo '<td>' . esc_html($registered_label) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';

            if ($total_pages > 1) {
                $base = admin_url('admin.php?page=wpam-affiliates&viewDetail=' . $aff_id);
                echo '<div class="tb-aff-stats-referred__pager">';
                if ($page > 1) {
                    $prev = add_query_arg('tb_ref_paged', $page - 1, $base);
                    echo '<a class="button" href="' . esc_url($prev) . '">' . esc_html__('Previous', 'wpam-aff-stats') . '</a> ';
                }
                echo '<span>' . esc_html(
                    sprintf(
                        /* translators: 1: current page, 2: total pages */
                        __('Page %1$d of %2$d', 'wpam-aff-stats'),
                        $page,
                        $total_pages
                    )
                ) . '</span> ';
                if ($page < $total_pages) {
                    $next = add_query_arg('tb_ref_paged', $page + 1, $base);
                    echo '<a class="button" href="' . esc_url($next) . '">' . esc_html__('Next', 'wpam-aff-stats') . '</a>';
                }
                echo '</div>';
            }
        }

        echo '</div></div>';
    }
}
