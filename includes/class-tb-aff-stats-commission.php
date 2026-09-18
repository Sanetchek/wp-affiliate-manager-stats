<?php
/**
 * Optional native PMPro checkout → WPAM commission bridge.
 *
 * Enabled by default when the theme does not already hook pmpro_after_checkout
 * for WPAM (e.g. TipsBattle purchase bridge). Disable via filter:
 * add_filter('tb_aff_stats_enable_pmpro_commission', '__return_false');
 *
 * @package WPAM_Affiliate_Stats
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class TB_Aff_Stats_Commission
{
    public static function init(): void
    {
        // Theme loads after plugins_loaded — decide enablement on init.
        add_action('init', [self::class, 'maybe_register'], 5);
    }

    /**
     * Register pmpro_after_checkout when no theme bridge is present.
     */
    public static function maybe_register(): void
    {
        if (!self::is_enabled()) {
            return;
        }

        if (has_action('pmpro_after_checkout', [self::class, 'on_pmpro_after_checkout'])) {
            return;
        }

        add_action('pmpro_after_checkout', [self::class, 'on_pmpro_after_checkout'], 20, 2);
    }

    /**
     * Whether this plugin should award commissions on native PMPro checkout.
     */
    public static function is_enabled(): bool
    {
        // TipsBattle (or any theme) already bridges PMPro → WPAM — avoid double payout.
        if (has_action('pmpro_after_checkout', 'tb_wpam_award_on_pmpro_after_checkout')) {
            $default = false;
        } elseif (function_exists('tb_wpam_award_membership_commission')) {
            // Theme helpers exist; prefer theme bridge even if hook not yet registered.
            $default = false;
        } else {
            $default = true;
        }

        /**
         * Filter: enable optional PMPro → WPAM commission on pmpro_after_checkout.
         *
         * @param bool $enabled Default true unless a theme bridge is detected.
         */
        return (bool) apply_filters('tb_aff_stats_enable_pmpro_commission', $default);
    }

    /**
     * Award WPAM commission after successful native PMPro checkout.
     *
     * @param int              $user_id Buyer user ID.
     * @param object|null      $order   MemberOrder when provided.
     */
    public static function on_pmpro_after_checkout(int $user_id, $order = null): void
    {
        if ($user_id <= 0) {
            return;
        }

        // Prefer theme award helper when present (single path, no double fire).
        if (function_exists('tb_wpam_award_on_pmpro_after_checkout')) {
            return;
        }

        $txn_id = '';
        $amount = 0.0;
        $level_id = 0;

        if (is_object($order)) {
            if (!empty($order->code)) {
                $txn_id = (string) $order->code;
            } elseif (!empty($order->id)) {
                $txn_id = 'pmpro_' . (int) $order->id;
            }
            if (isset($order->total) && is_numeric($order->total)) {
                $amount = (float) $order->total;
            }
            if (!empty($order->membership_id)) {
                $level_id = (int) $order->membership_id;
            }
            $status = isset($order->status) ? (string) $order->status : '';
            if ($status !== '' && $status !== 'success') {
                return;
            }
        }

        if ($txn_id === '' || $level_id <= 0) {
            return;
        }

        // Paid levels only (PMPro price > 0). Free checkout must not create commission.
        if (!self::level_is_paid($level_id)) {
            return;
        }

        if ($amount <= 0) {
            $amount = self::level_sale_amount($level_id);
        }

        self::award_commission($user_id, $txn_id, $amount);
    }

    /**
     * Whether a PMPro level should generate affiliate commission.
     */
    public static function level_is_paid(int $level_id): bool
    {
        if ($level_id <= 0) {
            return false;
        }

        $paid_ids = TB_Aff_Stats_Query::paid_pmpro_level_ids();
        if ($paid_ids !== []) {
            return in_array($level_id, $paid_ids, true);
        }

        // Fallback: any level with a positive price.
        return self::level_sale_amount($level_id) > 0;
    }

    /**
     * Sale amount for commission base (order total preferred by caller).
     */
    public static function level_sale_amount(int $level_id): float
    {
        if ($level_id <= 0 || !function_exists('pmpro_getLevel')) {
            return 0.0;
        }

        $level = pmpro_getLevel($level_id);
        if (!$level) {
            return 0.0;
        }

        if (function_exists('tb_resolve_pmpro_subscription_amount')) {
            return (float) tb_resolve_pmpro_subscription_amount($level);
        }

        $initial = (float) ($level->initial_payment ?? 0);
        if ($initial > 0) {
            return $initial;
        }

        return (float) ($level->billing_amount ?? 0);
    }

    /**
     * Resolve affiliate ID (stored first-touch meta, then cookie).
     */
    public static function resolve_referrer_id(int $user_id): int
    {
        if (function_exists('tb_wpam_resolve_referrer_id')) {
            return tb_wpam_resolve_referrer_id($user_id);
        }

        $meta_key = defined('TB_WPAM_REFERRER_META_KEY')
            ? TB_WPAM_REFERRER_META_KEY
            : 'tb_wpam_referrer_id';

        if ($user_id > 0) {
            $stored = (int) get_user_meta($user_id, $meta_key, true);
            if ($stored > 0) {
                return $stored;
            }
        }

        if (isset($_COOKIE['wpam_id'])) {
            $aff_id = (int) sanitize_text_field(wp_unslash((string) $_COOKIE['wpam_id']));
            if ($aff_id > 0) {
                return $aff_id;
            }
        }

        return 0;
    }

    /**
     * Fire WPAM commission action (WPAM handles dedupe / own-referral).
     *
     * @return bool True when the award action was fired.
     */
    public static function award_commission(
        int $user_id,
        string $txn_id,
        float $amount = 0.0,
        string $buyer_email = ''
    ): bool {
        $txn_id = sanitize_text_field($txn_id);
        if ($user_id <= 0 || $txn_id === '') {
            return false;
        }

        if (!has_action('wpam_process_affiliate_commission') && !class_exists('WPAM_Commission_Tracking')) {
            return false;
        }

        $aff_id = self::resolve_referrer_id($user_id);
        if ($aff_id <= 0) {
            return false;
        }

        if ($buyer_email === '') {
            $user = get_userdata($user_id);
            if ($user && is_string($user->user_email)) {
                $buyer_email = $user->user_email;
            }
        }
        $buyer_email = sanitize_email($buyer_email);

        $args = [
            'txn_id' => $txn_id,
            'amount' => max(0.0, $amount),
            'aff_id' => $aff_id,
            'integration' => 'wpam-aff-stats',
        ];
        if ($buyer_email !== '') {
            $args['email'] = $buyer_email;
        }

        /**
         * Filter commission args before WPAM award.
         *
         * @param array<string, mixed> $args
         * @param int                  $user_id
         */
        $args = apply_filters('tb_aff_stats_commission_args', $args, $user_id);
        if (!is_array($args) || empty($args['txn_id']) || empty($args['aff_id'])) {
            return false;
        }

        if (has_action('wpam_process_affiliate_commission')) {
            do_action('wpam_process_affiliate_commission', $args);
        } elseif (class_exists('WPAM_Commission_Tracking')) {
            WPAM_Commission_Tracking::award_commission($args);
        }

        return true;
    }
}
