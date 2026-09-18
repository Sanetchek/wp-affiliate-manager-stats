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
        $max_funnel = max(1, $visits, $signups, $paid);

        echo '<div class="wrap tb-aff-dash">';
        echo '<div class="tb-aff-dash__hero">';
        echo '<div class="tb-aff-dash__hero-copy">';
        echo '<p class="tb-aff-dash__eyebrow">' . esc_html__('WP Affiliate Manager Stats', 'wpam-aff-stats') . '</p>';
        echo '<h1>' . esc_html__('Referral program', 'wpam-aff-stats') . '</h1>';
        echo '<p class="tb-aff-dash__lede">' . esc_html__(
            'Site-wide Click to Signup to Paid funnel across all affiliates.',
            'wpam-aff-stats'
        ) . '</p>';
        echo '</div>';
        echo '<nav class="tb-aff-dash__periods" aria-label="' . esc_attr__('Stats period', 'wpam-aff-stats') . '">';
        foreach (self::period_labels() as $key => $label) {
            $url = $key === 'all' ? $base : add_query_arg('range', $key, $base);
            $class = $period === $key ? ' is-active' : '';
            echo '<a class="tb-aff-dash__period' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';
        echo '</div>';

        // Hero funnel.
        echo '<section class="tb-aff-dash__funnel" aria-label="' . esc_attr__('Referral funnel', 'wpam-aff-stats') . '">';
        echo '<div class="tb-aff-dash__funnel-track">';
        self::render_funnel_step(__('Clicks', 'wpam-aff-stats'), $visits, $max_funnel, 'clicks');
        echo '<div class="tb-aff-dash__funnel-edge">';
        echo '<span>' . esc_html(TB_Aff_Stats_Query::format_percent((float) $stats['click_to_signup'])) . '</span>';
        echo '<span class="tb-aff-dash__funnel-edge-label">' . esc_html__('Click → Signup', 'wpam-aff-stats') . '</span>';
        echo '</div>';
        self::render_funnel_step(__('Signups', 'wpam-aff-stats'), $signups, $max_funnel, 'signups');
        echo '<div class="tb-aff-dash__funnel-edge">';
        echo '<span>' . esc_html(TB_Aff_Stats_Query::format_percent((float) $stats['signup_to_paid'])) . '</span>';
        echo '<span class="tb-aff-dash__funnel-edge-label">' . esc_html__('Signup → Paid', 'wpam-aff-stats') . '</span>';
        echo '</div>';
        self::render_funnel_step(__('Paid', 'wpam-aff-stats'), $paid, $max_funnel, 'paid');
        echo '</div>';
        echo '</section>';

        // KPI strip.
        $earnings_label = function_exists('wpam_format_money')
            ? (string) wpam_format_money((float) $stats['earnings'], false)
            : '$' . number_format((float) $stats['earnings'], 2, '.', '');

        $kpis = [
            ['label' => __('Unique', 'wpam-aff-stats'), 'value' => (string) (int) $stats['unique_visits']],
            ['label' => __('Free', 'wpam-aff-stats'), 'value' => (string) $free],
            ['label' => __('Advanced', 'wpam-aff-stats'), 'value' => (string) (int) $stats['advanced']],
            ['label' => __('Pro', 'wpam-aff-stats'), 'value' => (string) (int) $stats['pro']],
            ['label' => __('Earnings', 'wpam-aff-stats'), 'value' => $earnings_label],
            [
                'label' => __('EPC', 'wpam-aff-stats'),
                'value' => TB_Aff_Stats_Query::format_epc((float) $stats['epc'], $visits),
            ],
        ];

        echo '<section class="tb-aff-dash__kpis" aria-label="' . esc_attr__('Key metrics', 'wpam-aff-stats') . '">';
        foreach ($kpis as $kpi) {
            echo '<div class="tb-aff-dash__kpi">';
            echo '<div class="tb-aff-dash__kpi-value">' . esc_html($kpi['value']) . '</div>';
            echo '<div class="tb-aff-dash__kpi-label">' . esc_html($kpi['label']) . '</div>';
            echo '</div>';
        }
        echo '</section>';

        // Free vs Paid split.
        $mix_total = max(1, $free + $paid);
        $free_pct = round(($free / $mix_total) * 100, 1);
        $paid_pct = round(($paid / $mix_total) * 100, 1);

        echo '<section class="tb-aff-dash__mix" aria-label="' . esc_attr__('Membership mix', 'wpam-aff-stats') . '">';
        echo '<h2>' . esc_html__('Signups mix', 'wpam-aff-stats') . '</h2>';
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
        echo '<span><i class="tb-aff-dash__dot tb-aff-dash__dot--free"></i>'
            . esc_html__('Free', 'wpam-aff-stats') . ' '
            . esc_html((string) $free) . ' (' . esc_html((string) $free_pct) . '%)</span>';
        echo '<span><i class="tb-aff-dash__dot tb-aff-dash__dot--paid"></i>'
            . esc_html__('Paid', 'wpam-aff-stats') . ' '
            . esc_html((string) $paid) . ' (' . esc_html((string) $paid_pct) . '%)</span>';
        echo '</div>';
        echo '</section>';

        // Top affiliates.
        echo '<section class="tb-aff-dash__top" aria-label="' . esc_attr__('Top affiliates', 'wpam-aff-stats') . '">';
        echo '<h2>' . esc_html__('Top affiliates', 'wpam-aff-stats') . '</h2>';
        if ($top === []) {
            echo '<p class="tb-aff-dash__empty">' . esc_html__('No affiliate data yet.', 'wpam-aff-stats') . '</p>';
        } else {
            echo '<table class="tb-aff-dash__table widefat striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Affiliate', 'wpam-aff-stats') . '</th>';
            echo '<th>' . esc_html__('Clicks', 'wpam-aff-stats') . '</th>';
            echo '<th>' . esc_html__('Signups', 'wpam-aff-stats') . '</th>';
            echo '<th>' . esc_html__('Paid', 'wpam-aff-stats') . '</th>';
            echo '<th>' . esc_html__('Signup → Paid', 'wpam-aff-stats') . '</th>';
            echo '<th>' . esc_html__('EPC', 'wpam-aff-stats') . '</th>';
            echo '</tr></thead><tbody>';
            foreach ($top as $row) {
                $aid = (int) $row['affiliate_id'];
                $detail = admin_url('admin.php?page=wpam-affiliates&viewDetail=' . $aid);
                $name = (string) $row['name'];
                echo '<tr>';
                echo '<td><a href="' . esc_url($detail) . '"><strong>' . esc_html($name) . '</strong></a>';
                echo '<div class="tb-aff-dash__sub">#' . esc_html((string) $aid);
                if ($row['email'] !== '') {
                    echo ' · ' . esc_html((string) $row['email']);
                }
                echo '</div></td>';
                echo '<td>' . esc_html((string) (int) $row['visits']) . '</td>';
                echo '<td>' . esc_html((string) (int) $row['signups']) . '</td>';
                echo '<td>' . esc_html((string) (int) $row['paid']) . '</td>';
                echo '<td>' . esc_html(TB_Aff_Stats_Query::format_percent((float) $row['signup_to_paid'])) . '</td>';
                echo '<td>' . esc_html(TB_Aff_Stats_Query::format_epc((float) $row['epc'], (int) $row['visits'])) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</section>';

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

    private static function render_funnel_step(string $label, int $value, int $max, string $mod): void
    {
        $pct = max(12, (int) round(($value / max(1, $max)) * 100));
        echo '<div class="tb-aff-dash__funnel-step tb-aff-dash__funnel-step--' . esc_attr($mod) . '">';
        echo '<div class="tb-aff-dash__funnel-bar" style="--tb-funnel-h:' . esc_attr((string) $pct) . '%">';
        echo '<span class="tb-aff-dash__funnel-value">' . esc_html((string) $value) . '</span>';
        echo '</div>';
        echo '<div class="tb-aff-dash__funnel-label">' . esc_html($label) . '</div>';
        echo '</div>';
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
