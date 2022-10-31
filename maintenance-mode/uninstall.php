<?php
// Make sure uninstallation is triggered
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

/**
 * Remove capabilities
 *
 * @since 2.1
 */
function scmm_remove_capabilities()
{
    global $wpdb;
    $wp_roles = get_option($wpdb->prefix . 'user_roles');

    if ($wp_roles && is_array($wp_roles)) {
        foreach ($wp_roles as $role => $role_details) {
            $get_role = get_role($role);
            $get_role->remove_cap('scmm_view_site');
            $get_role->remove_cap('scmm_control');
        }
    }
}

/**
 * Uninstall - clean up database removing plugin options
 *
 * @since 1.0
*/
function scmm_delete_plugin()
{
    delete_option('scmm-content-default');
    delete_option('scmm-content');
    delete_option('scmm-enabled');
    delete_option('scmm-site-title');
    delete_option('scmm-roles');
    delete_option('scmm-mode');
    delete_option('scmm_add_widget_areas');
    delete_option('scmm_analytify');
    delete_option('scmm_code_snippet');

    // remove capabilities
    scmm_remove_capabilities();
}

scmm_delete_plugin();
