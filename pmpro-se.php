<?php
/*
Plugin Name: Paid Memberships Pro - SpacesEngine Integration
Plugin URI: 
Description: Integration to monetize SpacesEngine using Paid Memberships Pro.
Version: 0.3.04
Author: Brandon Meyer
Author URI: indigetal.com
*/

// Ensure PMPro is installed
if (!defined('PMPRO_DIR')) {
    return;
}

// Include the settings page
require_once plugin_dir_path(__FILE__) . 'includes/integration-settings.php';

// Retrieve level IDs for SpacesEngine group
function retrieve_se_levels() {
    global $spacesengine_levels;

    $options = get_option('pmpro_se_settings');
    $group_id = isset($options['group_id']) ? intval($options['group_id']) : null;

    if (!function_exists('pmpro_get_level_ids_for_group') || !$group_id) {
        return;
    }

    // Retrieve level IDs for the SpacesEngine group
    $spacesengine_levels = pmpro_get_level_ids_for_group($group_id);
}
add_action('init', 'retrieve_se_levels', 1);

// Ensure levels are initialized
function ensure_levels_initialized() {
    global $spacesengine_levels;
    if (!isset($spacesengine_levels) || !is_array($spacesengine_levels)) {
        retrieve_se_levels();
    }
}

// Restrict access to certain pages based on level group
function se_member_redirects() {
    global $spacesengine_levels;

    $options = get_option('pmpro_se_settings');
    $non_admin_user_id = isset($options['non_admin_user_id']) ? intval($options['non_admin_user_id']) : null;
    $create_space_page = isset($options['create_space_page']) ? $options['create_space_page'] : null;
    $redirect_url = isset($options['redirect_url']) ? $options['redirect_url'] : null;

    // Check if PMPro is active
    if (!function_exists('pmpro_hasMembershipLevel')) {
        return;
    }

    // Only proceed if $create_space_page and $redirect_url are set
    if ($create_space_page && $redirect_url && strpos($_SERVER['REQUEST_URI'], '/' . trim($create_space_page, '/') . '/') !== false) {
        // Allow administrators to access restricted pages
        if (current_user_can('administrator')) {
            return;
        }

        // Allow specific non-admin user to access the create-organization page
        if ($non_admin_user_id && get_current_user_id() == $non_admin_user_id) {
            return;
        }

        // If a visitor trying to create a new Space doesn't have a SpacesEngine membership...
        if (!pmpro_hasMembershipLevel($spacesengine_levels)) {
            // Correct the redirect URL to ensure it is absolute
            $redirect_url = home_url($redirect_url);
            // Redirect them to the SpacesEngine plans page
            wp_safe_redirect($redirect_url);
            exit;
        }
    }
}
add_action('template_redirect', 'se_member_redirects', 10); // Ensure this runs before the Snippet Manager redirects

// Adjust the cost of a SpacesEngine-based level at checkout
function pmpro_adjustable_level_cost($level) {
    global $spacesengine_levels;
    $options = get_option('pmpro_se_settings');

    $promoted_monthly = isset($options['promoted_monthly']) ? floatval($options['promoted_monthly']) : null;
    $promoted_annual = isset($options['promoted_annual']) ? floatval($options['promoted_annual']) : null;
    $featured_monthly = isset($options['featured_monthly']) ? floatval($options['featured_monthly']) : null;
    $featured_annual = isset($options['featured_annual']) ? floatval($options['featured_annual']) : null;

    // Return the unmodified level if not in SpacesEngine group
    if (isset($level->id) && !in_array($level->id, $spacesengine_levels)) {
        return $level;
    }
    $field_name = 'upgrade_listing';
    
    // Flag to track if adjustments have been made
    static $adjustments_made = false;
    // Check if adjustments have already been made, and proceed if not
    if (!$adjustments_made && !empty($_REQUEST[$field_name])) {
        $options = array(
            'promoted' => array('monthly_fee' => $promoted_monthly, 'annual_fee' => $promoted_annual),
            'featured' => array('monthly_fee' => $featured_monthly, 'annual_fee' => $featured_annual)
        );
        
        if (isset($options[$_REQUEST[$field_name]])) {
            $option_values = $options[$_REQUEST[$field_name]];
            
            // Initialize extra fee
            $extra_fee = 0;
            // Determine the additional fee based on the selected option and cycle period
            if ($level->cycle_period === 'Month') {
                $extra_fee = $option_values['monthly_fee'];
            } elseif ($level->cycle_period === 'Year') {
                $extra_fee = $option_values['annual_fee'];
            }
            // Check if there is an extra fee
            if ($extra_fee > 0) {
                // Add the additional fee to the level's initial payment
                $level->initial_payment += $extra_fee;
                
                // Check if the level has a recurring subscription
                if (pmpro_isLevelRecurring($level)) {
                    // Configure recurring payments
                    $level->billing_amount += $extra_fee;
                }
            }
        }
        // Set the flag to true to indicate adjustments have been made
        $adjustments_made = true;
    }
    return $level;
}
add_filter("pmpro_checkout_level", "pmpro_adjustable_level_cost", 10, 1);

// Update billing_amount and initial_payment columns in pmpro_memberships_users table after checkout
function db_adjusted_cost_sync($user_id, $morder) {
    global $wpdb;
    
    // Get the specific id of the membership entry
    $membership_entry_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM $wpdb->pmpro_memberships_users WHERE user_id = %d AND membership_id = %d AND status = 'active'", 
            $user_id, 
            $morder->membership_id
            )
        );
        
    if ($membership_entry_id) {
        // Update the specific row with the adjusted prices
        $wpdb->update(
            $wpdb->pmpro_memberships_users, 
            array(
                'initial_payment' => $morder->subtotal, 
                'billing_amount' => $morder->subtotal
            ), 
            array('id' => $membership_entry_id), 
            array(
                '%s', 
                '%s'
            ), 
            array('%d')
        );
    }
}
add_action('pmpro_after_checkout', 'db_adjusted_cost_sync', 10, 2);

// Modify the output of pmpro_getMembershipLevelsForUser(), ensuring that the adjusted price is reflected correctly on the membership account page.
function display_membership_adjusted_price($levels, $user_id) {
    global $spacesengine_levels;

    // Ensure levels are initialized
    ensure_levels_initialized();

    // Check if spacesengine_levels is set and is an array
    if (!isset($spacesengine_levels) || !is_array($spacesengine_levels)) {
        return $levels;
    }

    foreach ($levels as $key => $level) {
        // Check if the level belongs to the SpacesEngine group
        if (in_array($level->id, $spacesengine_levels)) {
            // Get the selected option for the user
            $upgrade_listing = get_user_meta($user_id, 'upgrade_listing', true);

            // Check if the user selected either 'featured' or 'promoted'
            if ($upgrade_listing === 'featured' || $upgrade_listing === 'promoted') {
                // Adjust the price of each SpacesEngine level
                $adjusted_cost = pmpro_adjustable_level_cost($levels[$key]);
                $levels[$key]->initial_payment = $adjusted_cost->initial_payment;
                $levels[$key]->billing_amount = $adjusted_cost->billing_amount;
            }
        }
    }
    return $levels;
}
add_filter('pmpro_get_membership_levels_for_user', 'display_membership_adjusted_price', 10, 2);

// Set listing as promoted or featured if the user purchases that option
function set_featured_space_meta($post_id) {
    if (get_post_type($post_id) === 'wpe_wpspace') {
        $upgrade_listing = get_user_meta(get_current_user_id(), 'upgrade_listing', true);
        if ($upgrade_listing === 'promoted') {
            update_post_meta($post_id, 'featured_space', 1);
        } elseif ($upgrade_listing === 'featured') {
            update_post_meta($post_id, 'featured_space', 2);
        }
    }
}
add_action('save_post', 'set_featured_space_meta');

// Set a Member's Space to Draft when their SpacesEngine-based plan is removed (https://www.paidmembershipspro.com/set-member-authors-posts-draft-membership-cancelled/)
function pmpro_space_level_removed_actions($level_id, $user_id) {
    global $spacesengine_levels;
    $user_roles = get_userdata($user_id)->roles;
    
    if (array_intersect(array('subscriber', 'editor', 'contributor', 'author'), $user_roles) && !in_array($level_id, $spacesengine_levels)) {
        update_user_posts_to_draft($user_id);
        update_user_upgrade_listing($user_id, 'none');
    }
}

function update_user_posts_to_draft($user_id) {
    // Get the user's posts
    $args = array('author' => $user_id, 'post_type' => 'wpe_wpspace');
    $user_posts = get_posts($args);
    foreach ($user_posts as $user_post) {
        wp_update_post(array('ID' => $user_post->ID, 'post_status' => 'draft'));
    }
}

// Update upgrade_listing user meta
function update_user_upgrade_listing($user_id, $value) {
    update_user_meta($user_id, 'upgrade_listing', $value);
}
add_action('pmpro_after_change_membership_level', 'pmpro_space_level_removed_actions', 10, 2);

// Define the global options_and_filters array if not already defined
global $options_and_filters;
if (!isset($options_and_filters) || !is_array($options_and_filters)) {
    $options_and_filters = array(
        'enable_space_creation' => 'wpe_wps_is_space_creation_enabled',
        'space_creation_limit' => 'wpe_wps_get_space_creation_limit',
        'verify_spaces' => 'wpe_wps_is_verification_enabled',
        //'feature_spaces' => 'wpe_wps_is_featured_enabled',
        //'promote_spaces' => 'wpe_wps_is_promoted_enabled',
        'enable_space_admins' => 'wpe_wps_get_admins_enabled',
        'enable_space_editors' => 'wpe_wps_get_editors_enabled',
        'enable_profile_picture' => 'wpe_wps_can_profile_picture_upload',
        'enable_cover_image' => 'wpe_wps_can_cover_image_upload',
        'enable_short_description' => 'wpe_wps_is_short_description_enabled',
        'display_long_description' => 'wpe_wps_can_display_long_description',
        'display_website' => 'wpe_wps_can_display_website',
        'display_email' => 'wpe_wps_can_display_email',
        'display_address' => 'wpe_wps_can_display_address',
        'display_phone_number' => 'wpe_wps_can_display_phone',
        'enable_work_hours' => 'wpe_wps_is_work_hours_enabled',
        'display_social_icons' => 'wpe_wps_can_display_social_icons',
        'enable_categories' => 'wpe_wps_is_categories_enabled',
        'enable_messaging' => 'wpe_wps_is_messaging_enabled',
        'enable_engagement' => 'wpe_wps_is_engagement_enabled',
        'enable_contact_form' => 'wpe_wps_is_contact_form_enabled',
        'display_whatsapp_number' => 'wpe_wps_can_display_whatsapp',
        'enable_activity_feed' => 'wpe_wps_is_activity_feed_enabled',
        'enable_action_buttons' => 'wpe_wps_is_action_buttons_enabled',
        'enable_linked_groups' => 'wpe_wps_is_groups_enabled',
        'enable_services' => 'wpe_wps_is_services_enabled',
        'enable_header_video' => 'wpe_wps_is_video_enabled',
        'enable_reviews' => 'wpe_wps_is_reviews_enabled',
        'enable_jobs' => 'wpe_wps_is_jobs_enabled',
        'enable_events' => 'wpe_wps_is_events_enabled',
        'display_custom_fields' => 'wpe_wps_can_display_custom_fields'
    );
}

/* Configure default listing for pre-populating directory (must use non-admin user) 
It's recommended that you create an account with a username like "unverified," hide it from the BB members directory using a profile type, and create a redirect on the user profile to point to a "Claim a Space" 
page with instructions. Edit the user ID of the account you created and add the filters to the array
*/
function filters_for_specific_user($result, $space) {
    // Check if the post is of type 'wpe_wpspace'
    if (isset($space->post_type) && 'wpe_wpspace' === $space->post_type) {
        global $wpdb;
        $post_author = $wpdb->get_var($wpdb->prepare("SELECT post_author FROM $wpdb->posts WHERE ID = %d", $space->ID));
        // Check if the author's user ID matches the non-admin user ID from settings
        $options = get_option('pmpro_se_settings');
        $non_admin_user_id = isset($options['non_admin_user_id']) ? intval($options['non_admin_user_id']) : null;
        if ($non_admin_user_id && $non_admin_user_id === (int) $post_author) {
            // Apply the filters based on settings
            global $options_and_filters;
            foreach ($options_and_filters as $option_name => $filter_hook) {
                if (isset($options[$option_name]) && $options[$option_name] === 'disable') {
                    add_filter($filter_hook, '__return_false', 10, 2);
                }
            }
            return false;
        }
    }
    // Return the initial passed result for other spaces
    return $result;
}

// Apply the same callback function to all the filters defined in options_and_filters
global $options_and_filters;
$options = get_option('pmpro_se_settings');
foreach ($options_and_filters as $option_name => $filter_hook) {
    if (isset($options[$option_name]) && $options[$option_name] === 'disable') {
        add_filter($filter_hook, 'filters_for_specific_user', 10, 2);
    }
}

// Add SpacesEngine filters to PMPro level settings page:
add_action('pmpro_membership_level_after_other_settings', 'enable_disable_se_features', 1, 2);

function enable_disable_se_features($level) {
    global $spacesengine_levels, $options_and_filters;

    // Retrieve the ID from the level object
    $id = $level->id;

    // Check if the current level ID is in the organization levels array
    if (in_array($id, $spacesengine_levels)) {
        ?>
        <h3><?php esc_html_e('SpacesEngine Features', 'pmpro'); ?></h3>
        <table class="form-table">
            <tbody>
                <?php foreach ($options_and_filters as $option_name => $filter_hook) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html(ucwords(str_replace('_', ' ', str_replace('enable_', '', $option_name)))); ?></th>
                        <td>
                            <?php
                            // Retrieve the option value for the current membership level
                            $option_value = get_option('wpe_wps_' . $option_name . '_level_' . $id, 'enable');

                            // Render input based on option type
                            if ($option_name === 'space_creation_limit') {
                                ?>
                                <input type="number" name="<?php echo esc_attr($option_name); ?>" value="<?php echo esc_attr(absint($option_value)); ?>" />
                                <?php
                            } else {
                                ?>
                                <select name="<?php echo esc_attr($option_name); ?>" id="<?php echo esc_attr($option_name); ?>">
                                    <option value="enable" <?php selected($option_value, 'enable'); ?>><?php _e('Enable', 'textdomain'); ?></option>
                                    <option value="disable" <?php selected($option_value, 'disable'); ?>><?php _e('Disable', 'textdomain'); ?></option>
                                </select>
                                <?php
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}

// Define the update_wpe_wps_filters function
function update_wpe_wps_filters($filter_hook, $value, $level_id) {
    global $options_and_filters;

    // Extract option name from filter hook
    $option_name = array_search($filter_hook, $options_and_filters);
    
    // Update options specific to the membership level
    update_option('wpe_wps_' . $option_name . '_level_' . $level_id, $value);
    do_action($filter_hook, $value, $level_id);
}

// Hook the update function to the pmpro_save_membership_level action
add_action('pmpro_save_membership_level', 'update_wpe_wps_filters_for_membership_level', 10, 1);

function update_wpe_wps_filters_for_membership_level($level_id) {
    global $options_and_filters;
    // Initialize an array to store disabled filters
    $disabled_filters = array();

    // Iterate over each option and filter
    foreach ($options_and_filters as $option_name => $filter_hook) {
        $value = isset($_REQUEST[$option_name]) ? $_REQUEST[$option_name] : 'enable';

        // If the value is 'disable', it means the filter is disabled
        if ($value === 'disable') {
            $disabled_filters[] = $filter_hook;
        }

        // Call the update function
        update_wpe_wps_filters($filter_hook, $value, $level_id);
    }

    // Save the disabled filters for the membership level
    update_option('wpe_wps_disabled_filters_level_' . $level_id, $disabled_filters);
    error_log('Disabled filters for level ' . $level_id . ': ' . print_r($disabled_filters, true)); // Log the disabled filters for the level
}

// Function to disable filters based on the space author's membership level
function se_filters_for_individual_spaces($result, $space) {
    // Check if the post type is `wpe_wpspace`
    if (!isset($space->post_type) || $space->post_type !== 'wpe_wpspace') {
        return $result;
    }

    // Get the author ID and membership levels
    $author_id = $space->post_author;
    $membership_levels = pmpro_getMembershipLevelsForUser($author_id);

    if (!$membership_levels) {
        return $result;
    }

    error_log('Author ID: ' . $author_id);
    error_log('Author Membership Levels: ' . print_r($membership_levels, true));

    $all_disabled_filters = array();

    foreach ($membership_levels as $level) {
        $membership_level_id = $level->ID;
        $disabled_filters = get_option('wpe_wps_disabled_filters_level_' . $membership_level_id, array());

        error_log('Disabled Filters for level ' . $membership_level_id . ': ' . print_r($disabled_filters, true));

        if (is_array($disabled_filters)) {
            $all_disabled_filters = array_merge($all_disabled_filters, $disabled_filters);
        }
    }

    error_log('All Disabled Filters: ' . print_r($all_disabled_filters, true));

    if (empty($all_disabled_filters)) {
        return $result;
    }

    // Disable each filter by adding a callback that does nothing
    foreach ($all_disabled_filters as $filter_hook) {
        error_log('Disabling filter: ' . $filter_hook);
        add_filter($filter_hook, 'disable_filter_callback', 20, 2); // Use a higher priority to ensure it overrides other filters
    }

    return $result;
}

// Callback function to disable the filter
function disable_filter_callback($value) {
    return false;
}

// Apply the filters for individual spaces based on membership level
$options = get_option('pmpro_se_settings');
foreach ($options_and_filters as $option_name => $filter_hook) {
    if (isset($options[$option_name]) && $options[$option_name] === 'disable') {
        add_filter($filter_hook, 'se_filters_for_individual_spaces', 10, 2);
    }
}

?>