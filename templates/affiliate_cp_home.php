<?php
/**
 * Standalone partner Overview (used when theme has no override).
 *
 * @package WPAM_Affiliate_Stats
 */

defined('ABSPATH') || exit;
?>
<div class="aff-wrap">
    <?php include WPAM_BASE_DIRECTORY . '/html/affiliate_cp_nav.php'; ?>
    <div class="wrap">
        <table class="pure-table">
            <thead>
                <tr>
                    <th colspan="2"><?php esc_html_e('Account Summary', 'affiliates-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php esc_html_e('Balance', 'affiliates-manager'); ?></td>
                    <td><?php echo wpam_format_money($this->viewData['accountStanding']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Commission Rate', 'affiliates-manager'); ?></td>
                    <td><?php echo esc_html($this->viewData['commissionRateString']); ?></td>
                </tr>
            </tbody>
        </table>

        <table class="pure-table">
            <thead>
                <tr>
                    <th colspan="2"><?php esc_html_e('Today', 'affiliates-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="2">
                        <div class="summaryPanel">
                            <?php if (get_option(WPAM_PluginConfig::$AffEnableImpressions)) { ?>
                                <div class="summaryPanelLine">
                                    <div class="summaryPanelLineValue"><?php echo esc_html($this->viewData['todayImpressions']); ?></div>
                                    <div class="summaryPanelLineLabel"><?php esc_html_e('Impressions', 'affiliates-manager'); ?></div>
                                </div>
                            <?php } ?>
                            <div class="summaryPanelLine">
                                <div class="summaryPanelLineValue"><?php echo esc_html($this->viewData['todayVisitors']); ?></div>
                                <div class="summaryPanelLineLabel"><?php esc_html_e('Visitors', 'affiliates-manager'); ?></div>
                            </div>
                            <div class="summaryPanelLine">
                                <div class="summaryPanelLineValue"><?php echo esc_html($this->viewData['todayClosedTransactions']); ?></div>
                                <div class="summaryPanelLineLabel"><?php esc_html_e('Closed Transactions', 'affiliates-manager'); ?></div>
                            </div>
                            <div class="summaryPanelLine">
                                <div class="summaryPanelLineValue"><?php echo esc_html($this->viewData['todayRevenue']); ?></div>
                                <div class="summaryPanelLineLabel"><?php esc_html_e('Revenue', 'affiliates-manager'); ?></div>
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <table class="pure-table">
            <thead>
                <tr>
                    <th colspan="2"><?php esc_html_e('This Month', 'affiliates-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="2">
                        <div class="summaryPanel">
                            <?php if (get_option(WPAM_PluginConfig::$AffEnableImpressions)) { ?>
                                <div class="summaryPanelLine">
                                    <div class="summaryPanelLineValue"><?php echo esc_html($this->viewData['monthImpressions']); ?></div>
                                    <div class="summaryPanelLineLabel"><?php esc_html_e('Impressions', 'affiliates-manager'); ?></div>
                                </div>
                            <?php } ?>
                            <div class="summaryPanelLine">
                                <div class="summaryPanelLineValue"><?php echo esc_html($this->viewData['monthVisitors']); ?></div>
                                <div class="summaryPanelLineLabel"><?php esc_html_e('Visitors', 'affiliates-manager'); ?></div>
                            </div>
                            <div class="summaryPanelLine">
                                <div class="summaryPanelLineValue"><?php echo esc_html($this->viewData['monthClosedTransactions']); ?></div>
                                <div class="summaryPanelLineLabel"><?php esc_html_e('Closed Transactions', 'affiliates-manager'); ?></div>
                            </div>
                            <div class="summaryPanelLine">
                                <div class="summaryPanelLineValue"><?php echo esc_html($this->viewData['monthRevenue']); ?></div>
                                <div class="summaryPanelLineLabel"><?php esc_html_e('Revenue', 'affiliates-manager'); ?></div>
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php do_action('tb_affiliate_stats_overview'); ?>
    </div>
</div>
