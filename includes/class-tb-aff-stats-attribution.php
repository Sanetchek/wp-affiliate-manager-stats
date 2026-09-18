<?php
/**
 * Persist first-touch WPAM referrer on registration.
 *
 * @package WPAM_Affiliate_Stats
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class TB_Aff_Stats_Attribution
{
    public static function init(): void
    {
        add_action('user_register', [self::class, 'on_user_register'], 20);
    }

    /**
     * Capture cookie → user meta on signup (does not overwrite; no Free commission).
     */
    public static function on_user_register(int $user_id): void
    {
        if ($user_id <= 0) {
            return;
        }

        // Prefer theme helper when available (same first-touch rules).
        if (function_exists('tb_wpam_persist_referrer_for_user')) {
            tb_wpam_persist_referrer_for_user($user_id);

            return;
        }

        self::persist_referrer($user_id);
    }

    /**
     * Standalone persist when theme bridge is not loaded.
     *
     * @return int Stored affiliate ID (0 if none).
     */
    public static function persist_referrer(int $user_id, int $aff_id = 0): int
    {
        if ($user_id <= 0) {
            return 0;
        }

        $meta_key = defined('TB_WPAM_REFERRER_META_KEY')
            ? TB_WPAM_REFERRER_META_KEY
            : 'tb_wpam_referrer_id';

        $existing = (int) get_user_meta($user_id, $meta_key, true);
        if ($existing > 0) {
            return $existing;
        }

        if ($aff_id <= 0) {
            $aff_id = self::cookie_affiliate_id();
        }

        if ($aff_id <= 0) {
            return 0;
        }

        update_user_meta($user_id, $meta_key, $aff_id);

        return $aff_id;
    }

    private static function cookie_affiliate_id(): int
    {
        if (function_exists('tb_wpam_get_cookie_affiliate_id')) {
            return tb_wpam_get_cookie_affiliate_id();
        }

        if (!isset($_COOKIE['wpam_id'])) {
            return 0;
        }

        $raw = wp_unslash((string) $_COOKIE['wpam_id']);
        $aff_id = (int) sanitize_text_field($raw);

        return $aff_id > 0 ? $aff_id : 0;
    }
}
