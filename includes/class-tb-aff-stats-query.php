<?php
/**
 * Referral funnel query helpers (Click → Signup → Paid, EPC).
 *
 * @package WPAM_Affiliate_Stats
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class TB_Aff_Stats_Query
{
    /**
     * Empty stats payload.
     *
     * @return array{
     *   visits:int,unique_visits:int,signups:int,free:int,paid:int,
     *   advanced:int,pro:int,earnings:float,
     *   click_to_signup:float,signup_to_paid:float,click_to_paid:float,epc:float
     * }
     */
    public static function empty_stats(): array
    {
        return [
            'visits' => 0,
            'unique_visits' => 0,
            'signups' => 0,
            'free' => 0,
            'paid' => 0,
            'advanced' => 0,
            'pro' => 0,
            'earnings' => 0.0,
            'click_to_signup' => 0.0,
            'signup_to_paid' => 0.0,
            'click_to_paid' => 0.0,
            'epc' => 0.0,
        ];
    }

    /**
     * PMPro level IDs treated as paid (Advanced / Pro / Enterprise, month+year).
     *
     * @return array{all: list<int>, advanced: list<int>, pro: list<int>}
     */
    public static function paid_level_id_map(): array
    {
        $advanced = [];
        $pro = [];

        if (function_exists('tb_get_pmpro_level_id_by_tier')) {
            foreach (['month', 'year'] as $cycle) {
                $adv = (int) tb_get_pmpro_level_id_by_tier('advanced', $cycle);
                if ($adv > 0) {
                    $advanced[] = $adv;
                }
                $p = (int) tb_get_pmpro_level_id_by_tier('pro', $cycle);
                if ($p > 0) {
                    $pro[] = $p;
                }
                $ent = (int) tb_get_pmpro_level_id_by_tier('enterprise', $cycle);
                if ($ent > 0) {
                    $pro[] = $ent;
                }
            }
        } elseif (function_exists('pmpro_getAllLevels')) {
            $levels = pmpro_getAllLevels(true, true);
            if (is_array($levels)) {
                foreach ($levels as $level) {
                    if (!is_object($level) || empty($level->id)) {
                        continue;
                    }
                    $name = strtolower(trim((string) ($level->name ?? '')));
                    $id = (int) $level->id;
                    if ($id <= 0) {
                        continue;
                    }
                    if (str_contains($name, 'advanced')) {
                        $advanced[] = $id;
                    } elseif (str_contains($name, 'pro') || str_contains($name, 'enterprise')) {
                        $pro[] = $id;
                    }
                }
            }
        }

        $advanced = array_values(array_unique(array_filter($advanced)));
        $pro = array_values(array_unique(array_filter($pro)));

        return [
            'all' => array_values(array_unique(array_merge($advanced, $pro))),
            'advanced' => $advanced,
            'pro' => $pro,
        ];
    }

    /**
     * @return list<int>
     */
    public static function paid_pmpro_level_ids(): array
    {
        return self::paid_level_id_map()['all'];
    }

    /**
     * Whether a user currently holds a paid membership.
     */
    public static function user_is_paid(int $user_id): bool
    {
        if ($user_id <= 0 || !function_exists('pmpro_getMembershipLevelForUser')) {
            return false;
        }

        $level = pmpro_getMembershipLevelForUser($user_id);
        if (!$level || empty($level->id)) {
            return false;
        }

        $paid = self::paid_pmpro_level_ids();
        if ($paid === []) {
            return false;
        }

        return in_array((int) $level->id, $paid, true);
    }

    /**
     * Stats for one affiliate. Optional range: ['start' => Y-m-d H:i:s, 'end' => Y-m-d H:i:s].
     *
     * @param array{start?: string, end?: string}|null $range
     * @return array{
     *   visits:int,unique_visits:int,signups:int,free:int,paid:int,
     *   advanced:int,pro:int,earnings:float,
     *   click_to_signup:float,signup_to_paid:float,click_to_paid:float,epc:float
     * }
     */
    public static function get_stats(int $aff_id, ?array $range = null): array
    {
        if ($aff_id <= 0) {
            return self::empty_stats();
        }

        $batch = self::get_stats_for_affiliates([$aff_id], $range);

        return $batch[$aff_id] ?? self::empty_stats();
    }

    /**
     * Batched stats for list tables (no N+1).
     *
     * @param list<int> $aff_ids
     * @param array{start?: string, end?: string}|null $range
     * @return array<int, array{
     *   visits:int,unique_visits:int,signups:int,free:int,paid:int,
     *   advanced:int,pro:int,earnings:float,
     *   click_to_signup:float,signup_to_paid:float,click_to_paid:float,epc:float
     * }>
     */
    public static function get_stats_for_affiliates(array $aff_ids, ?array $range = null): array
    {
        global $wpdb;

        $aff_ids = array_values(array_unique(array_filter(array_map('intval', $aff_ids))));
        $out = [];
        foreach ($aff_ids as $id) {
            $out[$id] = self::empty_stats();
        }
        if ($aff_ids === []) {
            return $out;
        }

        $start = isset($range['start']) ? (string) $range['start'] : '';
        $end = isset($range['end']) ? (string) $range['end'] : '';
        $has_range = $start !== '' && $end !== '';

        $tokens_table = defined('WPAM_TRACKING_TOKENS_TBL')
            ? WPAM_TRACKING_TOKENS_TBL
            : $wpdb->prefix . 'wpam_tracking_tokens';
        $txn_table = defined('WPAM_TRANSACTIONS_TBL')
            ? WPAM_TRANSACTIONS_TBL
            : $wpdb->prefix . 'wpam_transactions';
        $meta_key = defined('TB_WPAM_REFERRER_META_KEY')
            ? TB_WPAM_REFERRER_META_KEY
            : 'tb_wpam_referrer_id';

        $placeholders = implode(',', array_fill(0, count($aff_ids), '%d'));

        // Visits + unique visits.
        if ($has_range) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $click_sql = self::prepare(
                "SELECT sourceAffiliateId AS aff_id,
                        COUNT(*) AS visits,
                        COUNT(DISTINCT ipAddress) AS unique_visits
                 FROM {$tokens_table}
                 WHERE sourceAffiliateId IN ({$placeholders})
                   AND dateCreated >= %s AND dateCreated < %s
                 GROUP BY sourceAffiliateId",
                array_merge($aff_ids, [$start, $end])
            );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $click_sql = self::prepare(
                "SELECT sourceAffiliateId AS aff_id,
                        COUNT(*) AS visits,
                        COUNT(DISTINCT ipAddress) AS unique_visits
                 FROM {$tokens_table}
                 WHERE sourceAffiliateId IN ({$placeholders})
                 GROUP BY sourceAffiliateId",
                $aff_ids
            );
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $click_rows = $wpdb->get_results($click_sql, ARRAY_A);
        if (is_array($click_rows)) {
            foreach ($click_rows as $row) {
                $aid = (int) ($row['aff_id'] ?? 0);
                if (!isset($out[$aid])) {
                    continue;
                }
                $out[$aid]['visits'] = (int) ($row['visits'] ?? 0);
                $out[$aid]['unique_visits'] = (int) ($row['unique_visits'] ?? 0);
            }
        }

        // Earnings (credit commissions).
        if ($has_range) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $earn_sql = self::prepare(
                "SELECT affiliateId AS aff_id,
                        COALESCE(SUM(IF(type = 'credit', amount, 0)), 0) AS earnings
                 FROM {$txn_table}
                 WHERE affiliateId IN ({$placeholders})
                   AND dateCreated >= %s AND dateCreated < %s
                 GROUP BY affiliateId",
                array_merge($aff_ids, [$start, $end])
            );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $earn_sql = self::prepare(
                "SELECT affiliateId AS aff_id,
                        COALESCE(SUM(IF(type = 'credit', amount, 0)), 0) AS earnings
                 FROM {$txn_table}
                 WHERE affiliateId IN ({$placeholders})
                 GROUP BY affiliateId",
                $aff_ids
            );
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $earn_rows = $wpdb->get_results($earn_sql, ARRAY_A);
        if (is_array($earn_rows)) {
            foreach ($earn_rows as $row) {
                $aid = (int) ($row['aff_id'] ?? 0);
                if (!isset($out[$aid])) {
                    continue;
                }
                $out[$aid]['earnings'] = (float) ($row['earnings'] ?? 0);
            }
        }

        // Signups from referrer meta (+ optional user_registered window).
        $users = $wpdb->users;
        $usermeta = $wpdb->usermeta;
        if ($has_range) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $signup_sql = self::prepare(
                "SELECT um.meta_value AS aff_id, COUNT(DISTINCT um.user_id) AS signups
                 FROM {$usermeta} um
                 INNER JOIN {$users} u ON u.ID = um.user_id
                 WHERE um.meta_key = %s
                   AND um.meta_value IN ({$placeholders})
                   AND u.user_registered >= %s AND u.user_registered < %s
                 GROUP BY um.meta_value",
                array_merge([$meta_key], $aff_ids, [$start, $end])
            );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $signup_sql = self::prepare(
                "SELECT um.meta_value AS aff_id, COUNT(DISTINCT um.user_id) AS signups
                 FROM {$usermeta} um
                 WHERE um.meta_key = %s
                   AND um.meta_value IN ({$placeholders})
                 GROUP BY um.meta_value",
                array_merge([$meta_key], $aff_ids)
            );
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $signup_rows = $wpdb->get_results($signup_sql, ARRAY_A);
        if (is_array($signup_rows)) {
            foreach ($signup_rows as $row) {
                $aid = (int) ($row['aff_id'] ?? 0);
                if (!isset($out[$aid])) {
                    continue;
                }
                $out[$aid]['signups'] = (int) ($row['signups'] ?? 0);
            }
        }

        $level_map = self::paid_level_id_map();
        $paid_ids = $level_map['all'];
        $adv_ids = $level_map['advanced'];
        $pro_ids = $level_map['pro'];
        $mu_table = $wpdb->prefix . 'pmpro_memberships_users';

        if ($paid_ids !== [] && self::pmpro_memberships_table_exists($mu_table)) {
            $paid_ph = implode(',', array_fill(0, count($paid_ids), '%d'));

            if ($has_range) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $paid_sql = self::prepare(
                    "SELECT um.meta_value AS aff_id, COUNT(DISTINCT um.user_id) AS paid
                     FROM {$usermeta} um
                     INNER JOIN {$users} u ON u.ID = um.user_id
                     INNER JOIN {$mu_table} mu
                        ON mu.user_id = um.user_id AND mu.status = 'active'
                     WHERE um.meta_key = %s
                       AND um.meta_value IN ({$placeholders})
                       AND mu.membership_id IN ({$paid_ph})
                       AND u.user_registered >= %s AND u.user_registered < %s
                     GROUP BY um.meta_value",
                    array_merge([$meta_key], $aff_ids, $paid_ids, [$start, $end])
                );
            } else {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $paid_sql = self::prepare(
                    "SELECT um.meta_value AS aff_id, COUNT(DISTINCT um.user_id) AS paid
                     FROM {$usermeta} um
                     INNER JOIN {$mu_table} mu
                        ON mu.user_id = um.user_id AND mu.status = 'active'
                     WHERE um.meta_key = %s
                       AND um.meta_value IN ({$placeholders})
                       AND mu.membership_id IN ({$paid_ph})
                     GROUP BY um.meta_value",
                    array_merge([$meta_key], $aff_ids, $paid_ids)
                );
            }
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $paid_rows = $wpdb->get_results($paid_sql, ARRAY_A);
            if (is_array($paid_rows)) {
                foreach ($paid_rows as $row) {
                    $aid = (int) ($row['aff_id'] ?? 0);
                    if (!isset($out[$aid])) {
                        continue;
                    }
                    $out[$aid]['paid'] = (int) ($row['paid'] ?? 0);
                }
            }

            if ($adv_ids !== []) {
                $out = self::merge_tier_counts($out, $aff_ids, $adv_ids, 'advanced', $meta_key, $mu_table, $range);
            }
            if ($pro_ids !== []) {
                $out = self::merge_tier_counts($out, $aff_ids, $pro_ids, 'pro', $meta_key, $mu_table, $range);
            }
        }

        foreach ($out as $aid => $stats) {
            $signups = (int) $stats['signups'];
            $paid = (int) $stats['paid'];
            $visits = (int) $stats['visits'];
            $earnings = (float) $stats['earnings'];

            $out[$aid]['free'] = max(0, $signups - $paid);
            $out[$aid]['click_to_signup'] = $visits > 0 ? ($signups / $visits) : 0.0;
            $out[$aid]['signup_to_paid'] = $signups > 0 ? ($paid / $signups) : 0.0;
            $out[$aid]['click_to_paid'] = $visits > 0 ? ($paid / $visits) : 0.0;
            $out[$aid]['epc'] = $visits > 0 ? ($earnings / $visits) : 0.0;
        }

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $out
     * @param list<int> $aff_ids
     * @param list<int> $tier_ids
     * @param array{start?: string, end?: string}|null $range
     * @return array<int, array<string, mixed>>
     */
    private static function merge_tier_counts(
        array $out,
        array $aff_ids,
        array $tier_ids,
        string $key,
        string $meta_key,
        string $mu_table,
        ?array $range
    ): array {
        global $wpdb;

        $placeholders = implode(',', array_fill(0, count($aff_ids), '%d'));
        $tier_ph = implode(',', array_fill(0, count($tier_ids), '%d'));
        $users = $wpdb->users;
        $usermeta = $wpdb->usermeta;
        $start = isset($range['start']) ? (string) $range['start'] : '';
        $end = isset($range['end']) ? (string) $range['end'] : '';
        $has_range = $start !== '' && $end !== '';

        if ($has_range) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $sql = self::prepare(
                "SELECT um.meta_value AS aff_id, COUNT(DISTINCT um.user_id) AS cnt
                 FROM {$usermeta} um
                 INNER JOIN {$users} u ON u.ID = um.user_id
                 INNER JOIN {$mu_table} mu
                    ON mu.user_id = um.user_id AND mu.status = 'active'
                 WHERE um.meta_key = %s
                   AND um.meta_value IN ({$placeholders})
                   AND mu.membership_id IN ({$tier_ph})
                   AND u.user_registered >= %s AND u.user_registered < %s
                 GROUP BY um.meta_value",
                array_merge([$meta_key], $aff_ids, $tier_ids, [$start, $end])
            );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $sql = self::prepare(
                "SELECT um.meta_value AS aff_id, COUNT(DISTINCT um.user_id) AS cnt
                 FROM {$usermeta} um
                 INNER JOIN {$mu_table} mu
                    ON mu.user_id = um.user_id AND mu.status = 'active'
                 WHERE um.meta_key = %s
                   AND um.meta_value IN ({$placeholders})
                   AND mu.membership_id IN ({$tier_ph})
                 GROUP BY um.meta_value",
                array_merge([$meta_key], $aff_ids, $tier_ids)
            );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return $out;
        }

        foreach ($rows as $row) {
            $aid = (int) ($row['aff_id'] ?? 0);
            if (!isset($out[$aid])) {
                continue;
            }
            $out[$aid][$key] = (int) ($row['cnt'] ?? 0);
        }

        return $out;
    }

    /**
     * Safe $wpdb->prepare that unpacks argument lists (IN (...) clauses).
     *
     * @param string       $sql  Query with placeholders.
     * @param list<mixed>  $args Values.
     */
    private static function prepare(string $sql, array $args): string
    {
        global $wpdb;

        if ($args === []) {
            return $sql;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (string) $wpdb->prepare($sql, ...$args);
    }

    private static function pmpro_memberships_table_exists(string $table): bool
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        return is_string($found) && $found === $table;
    }

    /**
     * Paginated referred users for admin drill-down.
     *
     * @return array{items: list<array{user_id:int,login:string,email:string,tier:string,registered:string}>, total: int}
     */
    public static function query_referred_users(int $aff_id, int $page = 1, int $per_page = 50): array
    {
        global $wpdb;

        $empty = ['items' => [], 'total' => 0];
        if ($aff_id <= 0) {
            return $empty;
        }

        $page = max(1, $page);
        $per_page = max(1, min(100, $per_page));
        $offset = ($page - 1) * $per_page;
        $meta_key = defined('TB_WPAM_REFERRER_META_KEY')
            ? TB_WPAM_REFERRER_META_KEY
            : 'tb_wpam_referrer_id';

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT um.user_id)
             FROM {$wpdb->usermeta} um
             WHERE um.meta_key = %s AND um.meta_value = %s",
            $meta_key,
            (string) $aff_id
        ));

        if ($total <= 0) {
            return $empty;
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID AS user_id, u.user_login AS login, u.user_email AS email, u.user_registered AS registered
             FROM {$wpdb->usermeta} um
             INNER JOIN {$wpdb->users} u ON u.ID = um.user_id
             WHERE um.meta_key = %s AND um.meta_value = %s
             ORDER BY u.user_registered DESC
             LIMIT %d OFFSET %d",
            $meta_key,
            (string) $aff_id,
            $per_page,
            $offset
        ), ARRAY_A);

        $items = [];
        if (is_array($rows)) {
            $level_map = self::paid_level_id_map();
            foreach ($rows as $row) {
                $uid = (int) ($row['user_id'] ?? 0);
                $items[] = [
                    'user_id' => $uid,
                    'login' => (string) ($row['login'] ?? ''),
                    'email' => (string) ($row['email'] ?? ''),
                    'tier' => self::resolve_user_tier_label($uid, $level_map),
                    'registered' => (string) ($row['registered'] ?? ''),
                ];
            }
        }

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @param array{all: list<int>, advanced: list<int>, pro: list<int>} $level_map
     */
    private static function resolve_user_tier_label(int $user_id, array $level_map): string
    {
        if ($user_id <= 0 || !function_exists('pmpro_getMembershipLevelForUser')) {
            return __('Free', 'wpam-aff-stats');
        }

        $level = pmpro_getMembershipLevelForUser($user_id);
        if (!$level || empty($level->id)) {
            return __('Free', 'wpam-aff-stats');
        }

        $id = (int) $level->id;
        if (in_array($id, $level_map['pro'], true)) {
            $name = strtolower((string) ($level->name ?? ''));
            if (str_contains($name, 'enterprise')) {
                return __('Enterprise', 'wpam-aff-stats');
            }

            return __('Pro', 'wpam-aff-stats');
        }
        if (in_array($id, $level_map['advanced'], true)) {
            return __('Advanced', 'wpam-aff-stats');
        }

        if (function_exists('tb_resolve_membership_tier') && function_exists('tb_get_membership_tier_label')) {
            $tier = tb_resolve_membership_tier($level);

            return (string) tb_get_membership_tier_label($tier);
        }

        return __('Free', 'wpam-aff-stats');
    }

    public static function format_percent(float $ratio): string
    {
        if ($ratio <= 0) {
            return '0%';
        }

        return number_format_i18n(round($ratio * 100, 1), 1) . '%';
    }

    public static function format_epc(float $epc, int $visits): string
    {
        if ($visits <= 0) {
            return '—';
        }

        if (function_exists('wpam_format_money')) {
            return (string) wpam_format_money($epc, false);
        }

        return '$' . number_format($epc, 2, '.', '');
    }

    /**
     * Today / this-month / all-time range helpers (site timezone).
     *
     * @return array{start: string, end: string}|null null = all time
     */
    public static function range_for(string $period): ?array
    {
        $period = strtolower(trim($period));
        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $now = new DateTimeImmutable('now', $tz);

        if ($period === 'today') {
            $start = $now->setTime(0, 0, 0);
            $end = $start->modify('+1 day');

            return [
                'start' => $start->format('Y-m-d H:i:s'),
                'end' => $end->format('Y-m-d H:i:s'),
            ];
        }

        if ($period === 'month' || $period === 'this_month') {
            $start = $now->modify('first day of this month')->setTime(0, 0, 0);
            $end = $start->modify('+1 month');

            return [
                'start' => $start->format('Y-m-d H:i:s'),
                'end' => $end->format('Y-m-d H:i:s'),
            ];
        }

        return null;
    }

    /**
     * Resolve WPAM affiliate ID for a WordPress user.
     */
    public static function affiliate_id_for_user(int $user_id): int
    {
        if ($user_id <= 0) {
            return 0;
        }

        global $wpdb;
        $table = defined('WPAM_AFFILIATES_TBL')
            ? WPAM_AFFILIATES_TBL
            : $wpdb->prefix . 'wpam_affiliates';

        $id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT affiliateId FROM {$table} WHERE userId = %d LIMIT 1",
            $user_id
        ));

        return $id > 0 ? $id : 0;
    }
}
