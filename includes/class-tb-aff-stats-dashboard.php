<?php
/**
 * Admin site-wide referral stats dashboard.
 *
 * @package WPAM_Affiliate_Stats
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class TB_Aff_Stats_Dashboard
{
    private const MENU_SLUG = 'wpam-aff-stats-dashboard';
    private const CACHE_TTL = 300;

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu'], 50);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function register_menu(): void
    {
        if (!self::can_manage()) {
            return;
        }

        $cap = current_user_can('manage_options') ? 'manage_options' : 'wpam_admin';
        $title = __('Stats Dashboard', 'wpam-aff-stats');

        if (defined('WPAM_VERSION') || class_exists('WPAM_Plugin', false)) {
            add_submenu_page(
                'wpam-affiliates',
                __('Referral stats dashboard', 'wpam-aff-stats'),
                $title,
                $cap,
                self::MENU_SLUG,
                [self::class, 'render_page']
            );

            return;
        }

        add_management_page(
            __('Referral stats dashboard', 'wpam-aff-stats'),
            $title,
            $cap,
            self::MENU_SLUG,
            [self::class, 'render_page']
        );
    }

    public static function enqueue_assets(string $hook): void
    {
        if (!isset($_GET['page']) || sanitize_text_field(wp_unslash((string) $_GET['page'])) !== self::MENU_SLUG) {
            return;
        }

        wp_enqueue_style(
            'tb-aff-stats-dashboard',
            TB_AFF_STATS_PLUGIN_URL . 'assets/admin-dashboard.css',
            [],
            TB_AFF_STATS_VERSION
        );
    }

    public static function render_page(): void
    {
        if (!self::can_manage()) {
            wp_die(esc_html__('You do not have permission to view this page.', 'wpam-aff-stats'));
        }

        $period = self::current_period();
        $payload = self::load_payload($period);
        $stats = $payload['stats'];
        $top = $payload['top'];
        $base = admin_url('admin.php?page=' . self::MENU_SLUG);

        $visits = (int) $stats['visits'];
        $signups = (int) $stats['signups'];
        $paid = (int) $stats['paid'];
        $free = (int) $stats['free'];
        $is_empty = ($visits + $signups + $paid) === 0;

        $earnings_label = function_exists('wpam_format_money')
            ? (string) wpam_format_money((float) $stats['earnings'], false)
            : '$' . number_format((float) $stats['earnings'], 2, '.', '');

        $epc_label = TB_Aff_Stats_Query::format_epc((float) $stats['epc'], $visits);
        if ($epc_label === '—') {
            $epc_label = 'n/a';
        }

        echo '<div class="wrap tb-aff-dash">';

        // Stage: header + funnel as one surface.
        echo '<header class="tb-aff-dash__stage">';
        echo '<div class="tb-aff-dash__stage-top">';
        echo '<div class="tb-aff-dash__hero-copy">';
        echo '<p class="tb-aff-dash__eyebrow">' . esc_html__('Program overview', 'wpam-aff-stats') . '</p>';
        echo '<h1>' . esc_html__('Referral funnel', 'wpam-aff-stats') . '</h1>';
        echo '<p class="tb-aff-dash__lede">' . esc_html__(
            'How traffic from all affiliates becomes signups and paid members.',
            'wpam-aff-stats'
        ) . '</p>';
        echo '</div>';
        echo '<nav class="tb-aff-dash__periods" aria-label="' . esc_attr__('Stats period', 'wpam-aff-stats') . '">';
        foreach (self::period_labels() as $key => $label) {
            $url = $key === 'all' ? $base : add_query_arg('range', $key, $base);
            $class = $period === $key ? ' is-active' : '';
            $aria = $period === $key ? ' aria-current="page"' : '';
            echo '<a class="tb-aff-dash__period' . esc_attr($class) . '" href="' . esc_url($url) . '"' . $aria . '>'
                . esc_html($label) . '</a>';
        }
        echo '</nav>';
        echo '</div>';

        echo '<section class="tb-aff-dash__funnel" aria-label="' . esc_attr__('Referral funnel', 'wpam-aff-stats') . '">';
        if ($is_empty) {
            echo '<p class="tb-aff-dash__funnel-hint">' . esc_html__(
                'No clicks in this period yet. Numbers appear after affiliates share referral links.',
                'wpam-aff-stats'
            ) . '</p>';
        }
        echo '<ol class="tb-aff-dash__funnel-flow">';
        self::render_funnel_node(1, __('Clicks', 'wpam-aff-stats'), $visits, 'clicks');
        self::render_funnel_arrow(
            TB_Aff_Stats_Query::format_percent((float) $stats['click_to_signup']),
            __('to signup', 'wpam-aff-stats')
        );
        self::render_funnel_node(2, __('Signups', 'wpam-aff-stats'), $signups, 'signups');
        self::render_funnel_arrow(
            TB_Aff_Stats_Query::format_percent((float) $stats['signup_to_paid']),
            __('to paid', 'wpam-aff-stats')
        );
        self::render_funnel_node(3, __('Paid', 'wpam-aff-stats'), $paid, 'paid');
        echo '</ol>';
        echo '</section>';
        echo '</header>';

        // KPI strip with short hints.
        $kpis = [
            [
                'label' => __('Unique visitors', 'wpam-aff-stats'),
                'value' => (string) (int) $stats['unique_visits'],
                'hint' => __('Distinct IPs', 'wpam-aff-stats'),
            ],
            [
                'label' => __('Free', 'wpam-aff-stats'),
                'value' => (string) $free,
                'hint' => __('Signups without paid plan', 'wpam-aff-stats'),
            ],
            [
                'label' => __('Advanced', 'wpam-aff-stats'),
                'value' => (string) (int) $stats['advanced'],
                'hint' => __('Active Advanced members', 'wpam-aff-stats'),
            ],
            [
                'label' => __('Pro', 'wpam-aff-stats'),
                'value' => (string) (int) $stats['pro'],
                'hint' => __('Active Pro / Enterprise', 'wpam-aff-stats'),
            ],
            [
                'label' => __('Earnings', 'wpam-aff-stats'),
                'value' => $earnings_label,
                'hint' => __('Affiliate commissions', 'wpam-aff-stats'),
            ],
            [
                'label' => __('EPC', 'wpam-aff-stats'),
                'value' => $epc_label,
                'hint' => __('Earnings per click', 'wpam-aff-stats'),
            ],
        ];

        echo '<section class="tb-aff-dash__kpis" aria-label="' . esc_attr__('Key metrics', 'wpam-aff-stats') . '">';
        foreach ($kpis as $kpi) {
            echo '<article class="tb-aff-dash__kpi">';
            echo '<p class="tb-aff-dash__kpi-label">' . esc_html($kpi['label']) . '</p>';
            echo '<p class="tb-aff-dash__kpi-value">' . esc_html($kpi['value']) . '</p>';
            echo '<p class="tb-aff-dash__kpi-hint">' . esc_html($kpi['hint']) . '</p>';
            echo '</article>';
        }
        echo '</section>';

        // Mix + top in a two-column board on wide screens.
        $mix_total = $free + $paid;
        $has_mix = $mix_total > 0;
        $free_pct = $has_mix ? round(($free / $mix_total) * 100, 1) : 0.0;
        $paid_pct = $has_mix ? round(($paid / $mix_total) * 100, 1) : 0.0;

        echo '<div class="tb-aff-dash__board">';
        echo '<section class="tb-aff-dash__panel tb-aff-dash__mix" aria-label="' . esc_attr__('Membership mix', 'wpam-aff-stats') . '">';
        echo '<div class="tb-aff-dash__panel-head">';
        echo '<h2>' . esc_html__('Signups mix', 'wpam-aff-stats') . '</h2>';
        echo '<p>' . esc_html__('Free vs paid among referred members', 'wpam-aff-stats') . '</p>';
        echo '</div>';
        if (!$has_mix) {
            echo '<div class="tb-aff-dash__empty-card">';
            echo '<strong>' . esc_html__('No referred signups yet', 'wpam-aff-stats') . '</strong>';
            echo '<span>' . esc_html__('The mix fills in when users register via affiliate links.', 'wpam-aff-stats') . '</span>';
            echo '</div>';
        } else {
            echo '<div class="tb-aff-dash__mix-bar" role="img" aria-label="'
                . esc_attr(sprintf(
                    /* translators: 1: free percent, 2: paid percent */
                    __('Free %1$s percent, Paid %2$s percent', 'wpam-aff-stats'),
                    (string) $free_pct,
                    (string) $paid_pct
                ))
                . '">';
            echo '<span class="tb-aff-dash__mix-free" style="width:' . esc_attr((string) $free_pct) . '%"></span>';
            echo '<span class="tb-aff-dash__mix-paid" style="width:' . esc_attr((string) $paid_pct) . '%"></span>';
            echo '</div>';
            echo '<div class="tb-aff-dash__mix-legend">';
            echo '<span><i class="tb-aff-dash__dot tb-aff-dash__dot--free" aria-hidden="true"></i>'
                . esc_html__('Free', 'wpam-aff-stats') . ' <b>' . esc_html((string) $free) . '</b>'
                . ' <em>(' . esc_html((string) $free_pct) . '%)</em></span>';
            echo '<span><i class="tb-aff-dash__dot tb-aff-dash__dot--paid" aria-hidden="true"></i>'
                . esc_html__('Paid', 'wpam-aff-stats') . ' <b>' . esc_html((string) $paid) . '</b>'
                . ' <em>(' . esc_html((string) $paid_pct) . '%)</em></span>';
            echo '</div>';
        }
        echo '</section>';

        echo '<section class="tb-aff-dash__panel tb-aff-dash__top" aria-label="' . esc_attr__('Top affiliates', 'wpam-aff-stats') . '">';
        echo '<div class="tb-aff-dash__panel-head">';
        echo '<h2>' . esc_html__('Top affiliates', 'wpam-aff-stats') . '</h2>';
        echo '<p>' . esc_html__('Ranked by paid referrals, then signups', 'wpam-aff-stats') . '</p>';
        echo '</div>';
        if ($top === []) {
            echo '<div class="tb-aff-dash__empty-card">';
            echo '<strong>' . esc_html__('No affiliates yet', 'wpam-aff-stats') . '</strong>';
            echo '<span>' . esc_html__('Approve partners under My Affiliates to see rankings here.', 'wpam-aff-stats') . '</span>';
            echo '</div>';
        } else {
            echo '<div class="tb-aff-dash__table-wrap">';
            echo '<table class="tb-aff-dash__table">';
            echo '<thead><tr>';
            echo '<th scope="col">' . esc_html__('Rank', 'wpam-aff-stats') . '</th>';
            echo '<th scope="col">' . esc_html__('Affiliate', 'wpam-aff-stats') . '</th>';
            echo '<th scope="col">' . esc_html__('Clicks', 'wpam-aff-stats') . '</th>';
            echo '<th scope="col">' . esc_html__('Signups', 'wpam-aff-stats') . '</th>';
            echo '<th scope="col">' . esc_html__('Paid', 'wpam-aff-stats') . '</th>';
            echo '<th scope="col">' . esc_html__('Conv.', 'wpam-aff-stats') . '</th>';
            echo '<th scope="col">' . esc_html__('EPC', 'wpam-aff-stats') . '</th>';
            echo '</tr></thead><tbody>';
            $rank = 0;
            foreach ($top as $row) {
                $rank++;
                $aid = (int) $row['affiliate_id'];
                $detail = admin_url('admin.php?page=wpam-affiliates&viewDetail=' . $aid);
                $name = (string) $row['name'];
                $row_epc = TB_Aff_Stats_Query::format_epc((float) $row['epc'], (int) $row['visits']);
                if ($row_epc === '—') {
                    $row_epc = 'n/a';
                }
                echo '<tr>';
                echo '<td><span class="tb-aff-dash__rank">' . esc_html((string) $rank) . '</span></td>';
                echo '<td><a class="tb-aff-dash__name" href="' . esc_url($detail) . '">' . esc_html($name) . '</a>';
                echo '<div class="tb-aff-dash__sub">#' . esc_html((string) $aid);
                if ($row['email'] !== '') {
                    echo ' · ' . esc_html((string) $row['email']);
                }
                echo '</div></td>';
                echo '<td>' . esc_html((string) (int) $row['visits']) . '</td>';
                echo '<td>' . esc_html((string) (int) $row['signups']) . '</td>';
                echo '<td><strong>' . esc_html((string) (int) $row['paid']) . '</strong></td>';
                echo '<td>' . esc_html(TB_Aff_Stats_Query::format_percent((float) $row['signup_to_paid'])) . '</td>';
                echo '<td>' . esc_html($row_epc) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
        }
        echo '</section>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * @return array{stats: array<string, mixed>, top: list<array<string, mixed>>}
     */
    private static function load_payload(string $period): array
    {
        $cache_key = 'wpam_aff_stats_dash_' . $period;
        $cached = get_transient($cache_key);
        if (is_array($cached) && isset($cached['stats'], $cached['top'])) {
            return [
                'stats' => $cached['stats'],
                'top' => is_array($cached['top']) ? $cached['top'] : [],
            ];
        }

        $range = $period === 'all' ? null : TB_Aff_Stats_Query::range_for($period);
        $payload = [
            'stats' => TB_Aff_Stats_Query::get_program_stats($range),
            'top' => TB_Aff_Stats_Query::get_top_affiliates(5, $range),
        ];
        set_transient($cache_key, $payload, self::CACHE_TTL);

        return $payload;
    }

    private static function render_funnel_node(int $step, string $label, int $value, string $mod): void
    {
        echo '<li class="tb-aff-dash__funnel-node tb-aff-dash__funnel-node--' . esc_attr($mod) . '">';
        echo '<span class="tb-aff-dash__funnel-stepnum">' . esc_html((string) $step) . '</span>';
        echo '<span class="tb-aff-dash__funnel-value">' . esc_html((string) $value) . '</span>';
        echo '<span class="tb-aff-dash__funnel-label">' . esc_html($label) . '</span>';
        echo '</li>';
    }

    private static function render_funnel_arrow(string $rate, string $caption): void
    {
        echo '<li class="tb-aff-dash__funnel-edge" aria-hidden="true">';
        echo '<span class="tb-aff-dash__funnel-rate">' . esc_html($rate) . '</span>';
        echo '<span class="tb-aff-dash__funnel-edge-label">' . esc_html($caption) . '</span>';
        echo '</li>';
    }

    private static function can_manage(): bool
    {
        return current_user_can('manage_options') || current_user_can('wpam_admin');
    }

    private static function current_period(): string
    {
        $raw = isset($_GET['range']) ? sanitize_text_field(wp_unslash((string) $_GET['range'])) : 'all';
        $raw = strtolower($raw);
        if (in_array($raw, ['today', 'month', 'all'], true)) {
            return $raw;
        }

        return 'all';
    }

    /**
     * @return array<string, string>
     */
    private static function period_labels(): array
    {
        return [
            'all' => __('All time', 'wpam-aff-stats'),
            'today' => __('Today', 'wpam-aff-stats'),
            'month' => __('This month', 'wpam-aff-stats'),
        ];
    }
}
