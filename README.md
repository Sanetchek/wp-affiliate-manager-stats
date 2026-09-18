# WP Affiliate Manager Stats

WordPress companion for **[WP Affiliate Manager](https://wordpress.org/plugins/affiliates-manager/)** + **[Paid Memberships Pro](https://www.paidmembershipspro.com/)**.

Compact Click → Signup → Paid funnel, EPC, and readable admin/partner cards — without paid WPAM/PMPro affiliate add-ons.

Built for membership sites with a Free → paid ladder. **Works on any WordPress install** that runs WPAM + PMPro.

## Requirements

- WordPress 6.0+
- [Affiliates Manager](https://wordpress.org/plugins/affiliates-manager/)
- [Paid Memberships Pro](https://www.paidmembershipspro.com/)

No custom theme required. The plugin ships fallback WPAM templates; if your theme already overrides `affiliates-manager/*.php`, those still win.

## Install

1. Copy folder to `wp-content/plugins/wp-affiliate-manager-stats/`
2. Activate **WP Affiliate Manager Stats**
3. Open Affiliates → My Affiliates — Funnel column + detail cards
4. Affiliate Overview shows All time / Today / This month cards

## Metrics

| Metric | Source |
|---|---|
| Visits / Unique | WPAM `wpam_tracking_tokens` |
| Signups | User meta `tb_wpam_referrer_id` (first-touch on `user_register`) |
| Free / Paid | Active PMPro level (paid = Advanced / Pro / Enterprise by name, or theme tier helpers if present) |
| EPC | WPAM credit commissions ÷ visits |

Free signups never get a commission. Native PMPro paid checkouts are handled by this plugin (see below). Off-site gateways (Whop, crypto) still need a fulfillment bridge.

## Commissions (native PMPro)

On sites **without** a custom purchase bridge, the plugin awards WPAM commissions on successful native PMPro checkout (`pmpro_after_checkout`):

- Only **paid** levels (price &gt; 0 / paid level map)
- Uses first-touch `tb_wpam_referrer_id` (or `wpam_id` cookie)
- WPAM dedupes by `txn_id`

Disable if you handle commissions yourself:

```php
add_filter('tb_aff_stats_enable_pmpro_commission', '__return_false');
```

On TipsBattle the theme purchase bridge is detected automatically — the plugin does **not** double-award.

Whop / crypto / off-site gateways still need a fulfillment hook that calls `do_action('wpam_process_affiliate_commission', $args)` (or the theme bridge).

## Optional theme hooks

If you customize WPAM templates in your theme, keep these bridges:

```php
// After choosing the list-table class:
$table_class = apply_filters('tb_wpam_affiliates_list_table_class', $table_class);

// After Account Summary on partner Overview:
do_action('tb_affiliate_stats_overview');
```

## Portability notes

- Works out of the box on stock WPAM + PMPro
- Paid level detection: uses `tb_get_pmpro_level_id_by_tier()` when available, otherwise matches PMPro level names containing `advanced` / `pro` / `enterprise`
- Referrer meta key: `tb_wpam_referrer_id` (override by defining `TB_WPAM_REFERRER_META_KEY` before load)

## Smoke

```bash
php tests/smoke/TbAffiliateStatsTest.php
```

## Developer documentation

In wp-admin: **Plugins → WP Affiliate Manager Stats → Documentation**  
(or **Affiliates → WPAM Stats Docs** after activate).

Source file: [`docs/developers.md`](docs/developers.md)

## License

GPL-2.0-or-later (WordPress plugin compatible)
