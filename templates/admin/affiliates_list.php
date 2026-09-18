<?php
/**
 * Standalone WPAM My Affiliates list (used when theme has no override).
 * Injects Funnel-capable list table via tb_wpam_affiliates_list_table_class.
 *
 * @package WPAM_Affiliate_Stats
 */

defined('ABSPATH') || exit;

$wpam_plugin_tabs = [
    'wpam-affiliates' => __('Affiliate Details', 'affiliates-manager'),
    'wpam-affiliates&tab=export_data' => __('Export Data', 'affiliates-manager'),
];

echo '<div class="wrap"><h1>' . esc_html__('My Affiliates', 'affiliates-manager') . '</h1>';

$current = isset($_GET['page']) ? sanitize_text_field(wp_unslash((string) $_GET['page'])) : '';
if (isset($_GET['tab'])) {
    $current .= '&tab=' . sanitize_text_field(wp_unslash((string) $_GET['tab']));
}

$content = '<h2 class="nav-tab-wrapper">';
foreach ($wpam_plugin_tabs as $location => $tabname) {
    $class = ($current === $location) ? ' nav-tab-active' : '';
    $content .= '<a class="nav-tab' . esc_attr($class) . '" href="?page=' . esc_attr($location) . '">' . esc_html($tabname) . '</a>';
}
$content .= '</h2>';
echo $content;
echo '<div id="poststuff"><div id="post-body">';

if (isset($_GET['tab']) && sanitize_text_field(wp_unslash((string) $_GET['tab'])) === 'export_data') {
    ?>
    <div class="postbox">
        <h3 class="hndle"><label for="title"><?php esc_html_e('Export Affiliates Record', 'affiliates-manager'); ?></label></h3>
        <div class="inside">
            <form method="POST">
                <?php wp_nonce_field('wpam-export-affiliates-to-csv-nonce'); ?>
                <p>
                    <input type="submit" name="wpam-export-affiliates-to-csv" value="<?php esc_attr_e('Export to CSV', 'affiliates-manager'); ?>" class="button-primary"/>
                </p>
            </form>
        </div>
    </div>
    <?php
} else {
    ?>
    <div class="postbox">
        <h3 class="hndle"><label for="title"><?php esc_html_e('Affiliate Search', 'affiliates-manager'); ?></label></h3>
        <div class="inside">
            <p><?php esc_html_e('Search for an affiliate by entering the affiliate ID, first name, last name or email address', 'affiliates-manager'); ?></p>
            <form method="post" action="">
                <input name="wpam_affiliate_search" type="text" size="35" value=""/>
                <input type="submit" name="submit" class="button" value="<?php esc_attr_e('Search', 'affiliates-manager'); ?>" />
            </form>
        </div>
    </div>
    <?php
    $status_array = [
        'all_active' => __('All Active', 'affiliates-manager'),
        'all' => __('All (Including Closed)', 'affiliates-manager'),
        'active' => __('Active', 'affiliates-manager'),
        'applied' => __('Applied', 'affiliates-manager'),
        'approved' => __('Approved', 'affiliates-manager'),
        'confirmed' => __('Confirmed', 'affiliates-manager'),
        'declined' => __('Declined', 'affiliates-manager'),
        'blocked' => __('Blocked', 'affiliates-manager'),
        'inactive' => __('Inactive', 'affiliates-manager'),
    ];
    $current_class = '';
    if (isset($_REQUEST['statusFilter'])) {
        $current_class = sanitize_text_field(wp_unslash((string) $_REQUEST['statusFilter']));
    }
    echo '<ul class="subsubsub">';
    $count = 1;
    foreach ($status_array as $key => $status) {
        $is_current = ($current_class === $key) ? ' class="current"' : '';
        echo '<li><a href="' . esc_url(admin_url('admin.php?page=wpam-affiliates&statusFilter=' . rawurlencode($key))) . '"' . $is_current . '>' . esc_html($status) . '</a>' . ($count === 9 ? '' : ' | ') . '</li>';
        $count++;
    }
    echo '</ul>';

    include_once WPAM_BASE_DIRECTORY . '/classes/ListAffiliatesTable.php';
    if (class_exists('TB_Aff_Stats_Admin_List', false)) {
        TB_Aff_Stats_Admin_List::define_list_table_if_needed();
    }
    $table_class = class_exists('TB_Aff_Stats_List_Table', false)
        ? 'TB_Aff_Stats_List_Table'
        : (class_exists('TB_Wpam_List_Affiliates_Table', false)
            ? 'TB_Wpam_List_Affiliates_Table'
            : 'WPAM_List_Affiliates_Table');
    $table_class = (string) apply_filters('tb_wpam_affiliates_list_table_class', $table_class);
    $affiliates_list_table = new $table_class();
    $affiliates_list_table->prepare_items();
    ?>
    <div class="wpam-click-throughs">
        <form id="wpam-click-throughs-filter" method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr(isset($_REQUEST['page']) ? (string) $_REQUEST['page'] : 'wpam-affiliates'); ?>" />
            <?php $affiliates_list_table->display(); ?>
        </form>
    </div>
    <?php
}

echo '</div></div></div>';
