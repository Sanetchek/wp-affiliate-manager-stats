# Developer documentation

WP Affiliate Manager Stats — companion for **Affiliates Manager (WPAM)** + **Paid Memberships Pro (PMPro)**.

This page is for developers integrating the plugin into a theme or custom checkout.

## What this plugin does

| Feature | Automatic? |
|---|---|
| Persist first-touch referrer on `user_register` | Yes |
| Funnel column on My Affiliates | Yes |
| Metric cards on affiliate detail | Yes |
| Partner Overview cards (All time / Today / Month) | Yes |
| CSV export with funnel columns | Yes |
| Commission on **native** PMPro checkout | Yes (optional; auto-skips if theme bridge exists) |
| Commission on Whop / crypto / webhooks | No — call WPAM from your fulfillment code |

## Requirements

- WordPress 6.0+
- [Affiliates Manager](https://wordpress.org/plugins/affiliates-manager/)
- [Paid Memberships Pro](https://www.paidmembershipspro.com/)

Install path: `wp-content/plugins/wp-affiliate-manager-stats/`

## Attribution (referrals)

Meta key (first-touch, never overwritten):

```text
tb_wpam_referrer_id
```

Override before the plugin loads:

```php
define('TB_WPAM_REFERRER_META_KEY', 'my_custom_referrer_meta');
```

Flow:

1. Visitor opens `?wpam_id={affiliateId}` → WPAM sets cookie `wpam_id`
2. On `user_register` the plugin stores that ID on the new user
3. Later stats count Signups / Free / Paid from this meta + active PMPro membership

## Metrics

| Metric | Source |
|---|---|
| Visits / Unique | WPAM `wpam_tracking_tokens` |
| Signups | Users with referrer meta |
| Paid | Active PMPro membership on a paid level |
| Free | Signups − Paid |
| EPC | Sum of WPAM credit commissions ÷ Visits |

Paid level detection:

1. Theme helpers `tb_get_pmpro_level_id_by_tier()` when present
2. Else PMPro level names containing `advanced` / `pro` / `enterprise`
3. Commission bridge also treats any level with price &gt; 0 as paid

## Native PMPro commissions

When no theme purchase bridge is detected, the plugin hooks `pmpro_after_checkout` and fires:

```php
do_action('wpam_process_affiliate_commission', [
    'txn_id'      => $order_code, // or pmpro_{id}
    'amount'      => $order_total,
    'aff_id'      => $referrer_id,
    'email'       => $buyer_email,
    'integration' => 'wpam-aff-stats',
]);
```

Disable:

```php
add_filter('tb_aff_stats_enable_pmpro_commission', '__return_false');
```

Force enable even if a theme helper exists:

```php
add_filter('tb_aff_stats_enable_pmpro_commission', '__return_true');
```

Filter award args:

```php
add_filter('tb_aff_stats_commission_args', function (array $args, int $user_id): array {
    // $args['amount'] = …;
    return $args;
}, 10, 2);
```

## Off-site / custom checkout (Whop, crypto, etc.)

`pmpro_after_checkout` does **not** run on webhook fulfillment. After a successful paid membership, call WPAM yourself:

```php
do_action('wpam_process_affiliate_commission', [
    'txn_id' => 'unique-transaction-id',
    'amount' => 49.00,
    'aff_id' => (int) get_user_meta($buyer_user_id, 'tb_wpam_referrer_id', true),
    'email'  => $buyer_email,
]);
```

Persist the referrer **before** redirecting off-site (cookie is missing on webhooks):

```php
// If your theme already has this helper:
tb_wpam_persist_referrer_for_user($user_id);

// Or:
$aff_id = isset($_COOKIE['wpam_id']) ? (int) $_COOKIE['wpam_id'] : 0;
if ($aff_id > 0 && !get_user_meta($user_id, 'tb_wpam_referrer_id', true)) {
    update_user_meta($user_id, 'tb_wpam_referrer_id', $aff_id);
}
```

## Theme template hooks

If your theme overrides WPAM templates, keep these bridges:

```php
// affiliates_list.php — after resolving the list-table class:
$table_class = apply_filters('tb_wpam_affiliates_list_table_class', $table_class);

// affiliate_cp_home.php — after Account Summary:
do_action('tb_affiliate_stats_overview');
```

Without theme overrides, the plugin injects its own templates via `wpam_load_template_files` (theme paths still win).

## Public PHP API (selected)

```php
TB_Aff_Stats_Query::get_stats(int $aff_id, ?array $range = null): array
TB_Aff_Stats_Query::get_stats_for_affiliates(array $aff_ids, ?array $range = null): array
TB_Aff_Stats_Query::query_referred_users(int $aff_id, int $page = 1, int $per_page = 50): array
TB_Aff_Stats_Query::format_percent(float $ratio): string
TB_Aff_Stats_Query::format_epc(float $epc, int $visits): string
TB_Aff_Stats_Query::range_for('today'|'month'|'all'): ?array

TB_Aff_Stats_Commission::award_commission(int $user_id, string $txn_id, float $amount = 0.0, string $buyer_email = ''): bool
TB_Aff_Stats_Attribution::persist_referrer(int $user_id, int $aff_id = 0): int
```

`$range` example: `['start' => '2026-01-01 00:00:00', 'end' => '2026-02-01 00:00:00']`.

## Filters & actions summary

| Hook | Type | Purpose |
|---|---|---|
| `tb_aff_stats_enable_pmpro_commission` | filter bool | Toggle native PMPro commission bridge |
| `tb_aff_stats_commission_args` | filter array | Mutate commission payload |
| `tb_wpam_affiliates_list_table_class` | filter string | Swap My Affiliates list-table class |
| `tb_affiliate_stats_overview` | action | Render partner funnel cards |
| `wpam_load_template_files` | filter (WPAM) | Plugin inserts fallback templates |

## File map

```text
wp-affiliate-manager-stats.php          Bootstrap
includes/class-tb-aff-stats-query.php   Funnel SQL / formatters
includes/class-tb-aff-stats-attribution.php  user_register persist
includes/class-tb-aff-stats-commission.php   pmpro_after_checkout bridge
includes/class-tb-aff-stats-admin-*.php Admin UI
includes/class-tb-aff-stats-partner.php Partner cards
includes/class-tb-aff-stats-export.php  CSV
includes/class-tb-aff-stats-docs.php    This documentation screen
templates/                              Fallback WPAM views
docs/developers.md                      Source of this page
tests/smoke/TbAffiliateStatsTest.php    Smoke tests
```

## Smoke tests

```bash
php tests/smoke/TbAffiliateStatsTest.php
```

## Repository

https://github.com/Sanetchek/wp-affiliate-manager-stats

## License

GPL-2.0-or-later
