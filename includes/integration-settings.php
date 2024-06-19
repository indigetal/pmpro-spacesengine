<?php
// Add the settings page to the Memberships menu under PMPro
function pmpro_se_add_settings_page() {
    add_submenu_page(
        'pmpro-dashboard', // Parent menu slug
        'SpacesEngine Addon', // Page title
        'SpacesEngine Addon', // Menu title
        'manage_options', // Capability
        'pmpro-se-integration', // Menu slug
        'pmpro_se_settings_page_callback' // Callback function
    );
}
add_action('admin_menu', 'pmpro_se_add_settings_page');

// Callback function to render the settings page
function pmpro_se_settings_page_callback() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('SpacesEngine Integration Settings', 'textdomain'); ?></h1>
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

    add_settings_section(
        'pmpro_se_default_features_section',
        __('Default Listing', 'textdomain'),
        'pmpro_se_default_features_section_callback',
        'pmpro-se-integration'
    );

    add_settings_field(
        'pmpro_se_non_admin_user_id',
        __('Non-Admin User ID', 'textdomain'),
        'pmpro_se_non_admin_user_id_callback',
        'pmpro-se-integration',
        'pmpro_se_default_features_section',
        array('label_for' => 'pmpro_se_non_admin_user_id')
    );

    global $options_and_filters;

    foreach ($options_and_filters as $option_name => $filter_hook) {
        add_settings_field(
            $option_name,
            __(ucwords(str_replace('_', ' ', $option_name)), 'textdomain'),
            'pmpro_se_default_feature_callback',
            'pmpro-se-integration',
            'pmpro_se_default_features_section',
            array('label_for' => $option_name)
        );
    }
}
add_action('admin_init', 'pmpro_se_register_settings');

function pmpro_se_section_callback() {
    echo '<p>' . __('Main settings for PMPro Integration with SpacesEngine.', 'textdomain') . '</p>';
}

function pmpro_se_group_id_callback() {
    $options = get_option('pmpro_se_settings');
    ?>
    <input type="text" name="pmpro_se_settings[group_id]" id="se_pmpro_group_id" value="<?php echo isset($options['group_id']) ? esc_attr($options['group_id']) : ''; ?>">
    <p class="description"><?php _e('The Group ID of the SpacesEngine Levels', 'textdomain'); ?></p>
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
    echo '<p>' . __('Define the costs for promoted and featured upgrades. This requires that you create a custom user field in Memberships > Settings > User Fields named "upgrade_listing" with the options "none", "promoted", and "featured". Another housekeeping item to ensure you are displaying accurate information to your users: in Memberships > Settings > Email Templates, select the email template “Checkout - Paid,” and “Checkout - Paid (admin)” and remove the following line: <p>Membership Fee: !!membership_cost!!</p> That line displays the initial cost of the membership plan and not the updated membership plan with the options.', 'textdomain') . '</p>';
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

function pmpro_se_default_features_section_callback() {
    echo '<p>' . __('Configure default listing features, great for pre-populating the directory with basic Spaces without requiring a plan or product purchase. You must assign a non-admin user for the filters to apply to the listings. It is recommended that you create a dedicated account with a username like "unverified-listing," hide it from the BB members directory using a profile type, and create a redirect on the user profile to point to a "Claim a Space" page with instructions as an extra precaution.', 'textdomain') . '</p>';
}

function pmpro_se_non_admin_user_id_callback() {
    $options = get_option('pmpro_se_settings');
    ?>
    <input type="text" name="pmpro_se_settings[non_admin_user_id]" id="pmpro_se_non_admin_user_id" value="<?php echo isset($options['non_admin_user_id']) ? esc_attr($options['non_admin_user_id']) : ''; ?>">
    <p class="description"><?php _e('The user ID of the account to be used for pre-populating the directory (must be a non-admin user for the filters to apply).', 'textdomain'); ?></p>
    <?php
}

function pmpro_se_default_feature_callback($args) {
    $options = get_option('pmpro_se_settings');
    $option_name = $args['label_for'];
    $value = isset($options[$option_name]) ? $options[$option_name] : 'enable';

    // Render input based on option type
    if ($option_name === 'space_creation_limit') {
        ?>
        <input type="number" name="pmpro_se_settings[<?php echo esc_attr($option_name); ?>]" id="<?php echo esc_attr($option_name); ?>" value="<?php echo esc_attr(absint($value)); ?>" />
        <p class="description"><?php _e('Maximum number of spaces that can be created.', 'textdomain'); ?></p>
        <?php
    } else {
        ?>
        <select name="pmpro_se_settings[<?php echo esc_attr($option_name); ?>]" id="<?php echo esc_attr($option_name); ?>">
            <option value="enable" <?php selected($value, 'enable'); ?>><?php _e('Enable', 'textdomain'); ?></option>
            <option value="disable" <?php selected($value, 'disable'); ?>><?php _e('Disable', 'textdomain'); ?></option>
        </select>
        <?php
    }
}
?>