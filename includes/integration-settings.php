<?php
// Add the settings page to the Paid Memberships Pro menu
function pmpro_se_add_settings_page() {
    // Add to Paid Memberships Pro menu
    add_submenu_page(
        'pmpro-membershiplevels',
        'SpacesEngine Addon',
        'SpacesEngine Addon',
        'manage_options',
        'pmpro-se-integration',
        'pmpro_se_settings_page_callback'
    );
}
add_action('admin_menu', 'pmpro_se_add_settings_page');

// Callback function to render the settings page
function pmpro_se_settings_page_callback() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('SpacesEngine Addon Settings', 'textdomain'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('pmpro_se_settings_group');
            do_settings_sections('pmpro-se-integration');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings
function pmpro_se_register_settings() {
    register_setting('pmpro_se_settings_group', 'pmpro_se_settings');

    add_settings_section(
        'pmpro_se_main_section',
        __('Main Settings', 'textdomain'),
        'pmpro_se_section_callback',
        'pmpro-se-integration'
    );

    add_settings_field(
        'pmpro_se_group_id',
        __('Group ID', 'textdomain'),
        'pmpro_se_group_id_callback',
        'pmpro-se-integration',
        'pmpro_se_main_section',
        array('label_for' => 'pmpro_se_group_id')
    );

    add_settings_field(
        'pmpro_se_non_admin_user_id',
        __('Non-Admin User ID', 'textdomain'),
        'pmpro_se_non_admin_user_id_callback',
        'pmpro-se-integration',
        'pmpro_se_main_section',
        array('label_for' => 'pmpro_se_non_admin_user_id')
    );

    add_settings_field(
        'pmpro_se_create_space_page',
        __('Create New Space Page', 'textdomain'),
        'pmpro_se_create_space_page_callback',
        'pmpro-se-integration',
        'pmpro_se_main_section',
        array('label_for' => 'pmpro_se_create_space_page')
    );

    add_settings_field(
        'pmpro_se_redirect_url',
        __('Redirect URL', 'textdomain'),
        'pmpro_se_redirect_url_callback',
        'pmpro-se-integration',
        'pmpro_se_main_section',
        array('label_for' => 'pmpro_se_redirect_url')
    );

    add_settings_section(
        'pmpro_se_upgrade_costs_section',
        __('Upgrade Costs', 'textdomain'),
        'pmpro_se_upgrade_costs_section_callback',
        'pmpro-se-integration'
    );

    add_settings_field(
        'pmpro_se_promoted_monthly',
        __('Promoted Monthly Cost', 'textdomain'),
        'pmpro_se_promoted_monthly_callback',
        'pmpro-se-integration',
        'pmpro_se_upgrade_costs_section',
        array('label_for' => 'pmpro_se_promoted_monthly')
    );

    add_settings_field(
        'pmpro_se_promoted_annual',
        __('Promoted Annual Cost', 'textdomain'),
        'pmpro_se_promoted_annual_callback',
        'pmpro-se-integration',
        'pmpro_se_upgrade_costs_section',
        array('label_for' => 'pmpro_se_promoted_annual')
    );

    add_settings_field(
        'pmpro_se_featured_monthly',
        __('Featured Monthly Cost', 'textdomain'),
        'pmpro_se_featured_monthly_callback',
        'pmpro-se-integration',
        'pmpro_se_upgrade_costs_section',
        array('label_for' => 'pmpro_se_featured_monthly')
    );

    add_settings_field(
        'pmpro_se_featured_annual',
        __('Featured Annual Cost', 'textdomain'),
        'pmpro_se_featured_annual_callback',
        'pmpro-se-integration',
        'pmpro_se_upgrade_costs_section',
        array('label_for' => 'pmpro_se_featured_annual')
    );
}
add_action('admin_init', 'pmpro_se_register_settings');

function pmpro_se_section_callback() {
    echo '<p>' . __('Main settings for PMPro Integration with SpacesEngine.', 'textdomain') . '</p>';
}

function pmpro_se_group_id_callback() {
    $options = get_option('pmpro_se_settings');
    ?>
    <input type="text" name="pmpro_se_settings[group_id]" id="pmpro_se_group_id" value="<?php echo isset($options['group_id']) ? esc_attr($options['group_id']) : ''; ?>">
    <p class="description"><?php _e('The Group ID of the SpacesEngine Levels', 'textdomain'); ?></p>
    <?php
}

function pmpro_se_non_admin_user_id_callback() {
    $options = get_option('pmpro_se_settings');
    ?>
    <input type="text" name="pmpro_se_settings[non_admin_user_id]" id="pmpro_se_non_admin_user_id" value="<?php echo isset($options['non_admin_user_id']) ? esc_attr($options['non_admin_user_id']) : ''; ?>">
    <p class="description"><?php _e('Allow user with ID to pre-populate directory with default Spaces (must be a non-admin user for the filters to apply).', 'textdomain'); ?></p>
    <?php
}

function pmpro_se_create_space_page_callback() {
    $options = get_option('pmpro_se_settings');
    ?>
    <input type="text" name="pmpro_se_settings[create_space_page]" id="pmpro_se_create_space_page" value="<?php echo isset($options['create_space_page']) ? esc_attr($options['create_space_page']) : ''; ?>">
    <p class="description"><?php _e('The relative path of the page to create a new space.', 'textdomain'); ?></p>
    <?php
}

function pmpro_se_redirect_url_callback() {
    $options = get_option('pmpro_se_settings');
    ?>
    <input type="text" name="pmpro_se_settings[redirect_url]" id="pmpro_se_redirect_url" value="<?php echo isset($options['redirect_url']) ? esc_attr($options['redirect_url']) : ''; ?>">
    <p class="description"><?php _e('The PMPro Levels page for SpacesEngine plans.', 'textdomain'); ?></p>
    <?php
}

function pmpro_se_upgrade_costs_section_callback() {
    echo '<p>' . __('Define the costs for promoted and featured upgrades.', 'textdomain') . '</p>';
}

function pmpro_se_promoted_monthly_callback() {
    $options = get_option('pmpro_se_settings');
    ?>
    <input type="number" name="pmpro_se_settings[promoted_monthly]" id="pmpro_se_promoted_monthly" value="<?php echo isset($options['promoted_monthly']) ? esc_attr($options['promoted_monthly']) : ''; ?>">
    <p class="description"><?php _e('The monthly cost for promoted upgrades.', 'textdomain'); ?></p>
    <?php
}

function pmpro_se_promoted_annual_callback() {
    $options = get_option('pmpro_se_settings');
    ?>
    <input type="number" name="pmpro_se_settings[promoted_annual]" id="pmpro_se_promoted_annual" value="<?php echo isset($options['promoted_annual']) ? esc_attr($options['promoted_annual']) : ''; ?>">
    <p class="description"><?php _e('The annual cost for promoted upgrades.', 'textdomain'); ?></p>
    <?php
}

function pmpro_se_featured_monthly_callback() {
    $options = get_option('pmpro_se_settings');
    ?>
    <input type="number" name="pmpro_se_settings[featured_monthly]" id="pmpro_se_featured_monthly" value="<?php echo isset($options['featured_monthly']) ? esc_attr($options['featured_monthly']) : ''; ?>">
    <p class="description"><?php _e('The monthly cost for featured upgrades.', 'textdomain'); ?></p>
    <?php
}

function pmpro_se_featured_annual_callback() {
    $options = get_option('pmpro_se_settings');
    ?>
    <input type="number" name="pmpro_se_settings[featured_annual]" id="pmpro_se_featured_annual" value="<?php echo isset($options['featured_annual']) ? esc_attr($options['featured_annual']) : ''; ?>">
    <p class="description"><?php _e('The annual cost for featured upgrades.', 'textdomain'); ?></p>
    <?php
}
?>
