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

Commissions for Free signups are **not** awarded by this plugin. Wire paid checkout commissions yourself (or use a theme purchase bridge) via `do_action('wpam_process_affiliate_commission', $args)`.

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

## License

GPL-2.0-or-later (WordPress plugin compatible)
