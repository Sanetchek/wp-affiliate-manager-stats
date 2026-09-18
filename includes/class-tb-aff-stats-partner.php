<?php
/**
 * Partner dashboard funnel cards (All time / Today / This month).
 *
 * @package WPAM_Affiliate_Stats
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class TB_Aff_Stats_Partner
{
    public static function init(): void
    {
        add_action('tb_affiliate_stats_overview', [self::class, 'render_overview']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_partner_assets'], 110);
    }

    public static function enqueue_partner_assets(): void
    {
        if (!function_exists('tb_is_wpam_page') || !tb_is_wpam_page()) {
            // Fallback: enqueue when AffiliatesHome shortcode may render.
            if (!is_singular()) {
                return;
            }
            $post = get_post();
            if (!$post instanceof WP_Post) {
                return;
            }
            $content = (string) $post->post_content;
            if (!has_shortcode($content, 'AffiliatesHome')) {
                return;
            }
        }

        $deps = [];
        if (wp_style_is('tb-frontend', 'registered') || wp_style_is('tb-frontend', 'enqueued')) {
            $deps[] = 'tb-frontend';
        }

        wp_enqueue_style(
            'tb-aff-stats-partner',
            TB_AFF_STATS_PLUGIN_URL . 'assets/partner-affiliate-stats.css',
            $deps,
            TB_AFF_STATS_VERSION
        );
    }

    public static function render_overview(): void
    {
        $user_id = get_current_user_id();
        $aff_id = TB_Aff_Stats_Query::affiliate_id_for_user($user_id);
        if ($aff_id <= 0) {
            return;
        }

        $periods = [
            'all' => [
                'title' => __('All time', 'wpam-aff-stats'),
                'range' => null,
                'emphasis' => true,
            ],
            'today' => [
                'title' => __('Today', 'wpam-aff-stats'),
                'range' => TB_Aff_Stats_Query::range_for('today'),
                'emphasis' => false,
            ],
            'month' => [
                'title' => __('This month', 'wpam-aff-stats'),
                'range' => TB_Aff_Stats_Query::range_for('month'),
                'emphasis' => false,
            ],
        ];

        echo '<div class="tb-aff-stats-partner">';
        foreach ($periods as $period) {
            $stats = TB_Aff_Stats_Query::get_stats($aff_id, $period['range']);
            $class = $period['emphasis'] ? ' tb-aff-stats-partner__section--primary' : '';
            echo '<section class="tb-aff-stats-partner__section' . esc_attr($class) . '">';
            echo '<h3 class="tb-aff-stats-partner__title">' . esc_html((string) $period['title']) . '</h3>';
            echo '<div class="tb-aff-stats-cards">';
            self::render_card(__('Clicks', 'wpam-aff-stats'), (string) (int) $stats['visits']);
            self::render_card(__('Signups', 'wpam-aff-stats'), (string) (int) $stats['signups']);
            self::render_card(__('Free', 'wpam-aff-stats'), (string) (int) $stats['free']);
            self::render_card(__('Paid', 'wpam-aff-stats'), (string) (int) $stats['paid']);
            self::render_card(
                __('Signup → Paid', 'wpam-aff-stats'),
                TB_Aff_Stats_Query::format_percent((float) $stats['signup_to_paid'])
            );
            self::render_card(
                __('EPC', 'wpam-aff-stats'),
                TB_Aff_Stats_Query::format_epc((float) $stats['epc'], (int) $stats['visits'])
            );
            echo '</div></section>';
        }
        echo '</div>';
    }

    private static function render_card(string $label, string $value): void
    {
        echo '<div class="tb-aff-stats-card">';
        echo '<div class="tb-aff-stats-card__value">' . esc_html($value) . '</div>';
        echo '<div class="tb-aff-stats-card__label">' . esc_html($label) . '</div>';
        echo '</div>';
    }
}
