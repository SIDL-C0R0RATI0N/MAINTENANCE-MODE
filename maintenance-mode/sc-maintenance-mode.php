<?php
/*  +---------------------------------------------------------------------+
    Plugin Name: Système de Maintenance
    Plugin URI: https://github.com/SIDL-C0R0RATI0N/MAINTENANCE-MODE
    Version: 1.0.1
    Author: SIDL CORPORATION
    Author URI: https://sidl-corporation.fr/
    Description: Vous avez besoins d'une page de maintenance ? Nous avons mise en place notre premier plugins pour vous permettre de mettre votre site en maintenance le temps d'une mise à jour.
    Text Domain: sc-maintenance-mode
    Domain Path: /languages/
    Requires at least: 4.0
    Tested up to: 6.0.3
    Requires PHP: 5.3
    Stable tag: 1.0.1
    License: GPLv2 or later
    License URI: http://www.gnu.org/licenses/gpl-2.0.html

    +---------------------------------------------------------------------+
    Copyright 2020-2022 SIDL CORPORATION

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
    +---------------------------------------------------------------------+

   * @package sc-maintenance-mode
   * @author SIDL CORPORATION
   * @version 1.0.1
*/
// define stuff
define('SCMM_VERSION', '1.0.1');
define('SCMM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SCMM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SCMM_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('SCMM_PLUGIN_DOMAIN', 'sc-maintenance-mode');
define('SCMM_VIEW_SITE_CAP', 'scmm_view_site');
define('SCMM_PLUGIN_CAP', 'scmm_control');
define('SCMM_SUPPORT_LINK', 'https://forum.sidl-corporation.fr/topic/48-wordpress-plugins-maintenance-mode-additional-plugin-for-wordpress/');
define('SCMM_RELEASES_LINK', 'https://github.com/SIDL-C0R0RATI0N/MAINTENANCE-MODE/releases');

/**
 * Installation
 *
 * @since 1.0
 */
function scmm_install()
{
    // remove old settings. This has been deprecated in 1.2
    delete_option('scmm-content-default');

    // set default content
    scmm_set_content();
}
add_action('activate_' . SCMM_PLUGIN_BASENAME, 'scmm_install');

/**
 * Default hardcoded settings
 *
 * @since 1.4
 */
function scmm_get_defaults($type)
{
    switch ($type) {
        case 'maintenance_message':
            $default = __("<h1>Site en maintenance</h1><p>Notre site Web fait actuellement l'objet d'une maintenance planifiée. Veuillez revenir bientôt.</p>", SCMM_PLUGIN_DOMAIN);
            break;
        case 'warning_wp_super_cache':
            $default = __("Important : n'oubliez pas de vider votre cache à l'aide de WP Super Cache lors de l'activation ou de la désactivation du mode maintenance.", SCMM_PLUGIN_DOMAIN);
            break;
        case 'warning_w3_total_cache':
            $default = __("Important : n'oubliez pas de vider votre cache à l'aide de W3 Total Cache lors de l'activation ou de la désactivation du mode maintenance.", SCMM_PLUGIN_DOMAIN);
            break;
        case 'warning_comet_cache':
            $default = __("Important : n'oubliez pas de vider votre cache à l'aide de Comet Cache lors de l'activation ou de la désactivation du mode maintenance.", SCMM_PLUGIN_DOMAIN);
            break;
        case 'scmm_enabled':
            $default = __("Le mode maintenance est actuellement actif. Pour vous assurer que cela fonctionne, ouvrez votre page Web en mode privé / incognito, un autre navigateur ou déconnectez-vous simplement. Les utilisateurs connectés ne sont pas affectés par le mode de maintenance.", SCMM_PLUGIN_DOMAIN);
            break;
        case 'scmm_add_widget_areas':
            $default = __('Vous pouvez ajouter des widgets dans <strong>Apparence -> Widgets</strong>.', SCMM_PLUGIN_DOMAIN);
            break;
        default:
            $default = false;
            break;
    }

    return $default;
}

/**
 * Set the default content
 * Avoid duplicate function.
 *
 * @since 1.0
 */
function scmm_set_content()
{
    // If content is not set, set the default content.
    $content = get_option('scmm-content');

    if (empty($content)) {
        $content = scmm_get_defaults('maintenance_message');
        update_option('scmm-content', stripslashes($content));
    }

    // If content is not set, set the default content.
    $mode = get_option('scmm-mode');
    if (empty($mode)) {
        update_option('scmm-mode', 'default');
    }
}

/**
 * Load plugin textdomain.
 *
 * @since 1.3.1
*/
function scmm_load_textdomain()
{
    load_plugin_textdomain(SCMM_PLUGIN_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'scmm_load_textdomain');

/**
 * Main class
 *
 * @since 1.0
*/
class scMaintenanceMode
{
    /**
     * Constructor
     *
     * @since 1.0
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'ui'));
        add_action('admin_head', array($this, 'style'));
        add_action('admin_init', array($this, 'settings'));
        add_action('admin_init', array($this, 'manage_capabilities'));

        // remove old settings. This has been deprecated in 1.2
        delete_option('scmm-content-default');

        // maintenance mode
        add_action('get_header', array($this, 'maintenance'));

        add_action('admin_bar_menu', array($this, 'indicator'), 100);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'action_links'));

        add_action('scmm_before_mm', array($this, 'before_maintenance_mode'));

        // add shortcode support
        add_filter('scmm_content', 'do_shortcode', 11);

        // add widget areas if enabled
        if (get_option('scmm_add_widget_areas')) {
            $this->register_widget_sidebars();
        }
    }

    /**
     * Settings page
     *
     * @since 1.0
    */
    public function ui()
    {
        add_submenu_page('options-general.php', __('Maintenance', SCMM_PLUGIN_DOMAIN), __('Maintenance', SCMM_PLUGIN_DOMAIN), $this->get_relevant_cap(), 'sc-maintenance-mode', array($this, 'settingsPage'));
    }

    /**
     * Inject styling for admin bar indicator
     *
     * @since 1.1
    */
    public function style()
    {
        echo 
        '<style type="text/css">
        #wp-admin-bar-scmm-indicator.scmm-indicator--enabled {
            background: rgba(159, 0, 0, 1)
        }
        
        .card-sc {
            position: relative;
            padding: 20px;
            flex-direction: column;
            min-width: 0;
            word-wrap: break-word;
            background-color: #b6b6b64c;
            background-clip: border-box;
            border: 0 solid rgba(0, 0, 0, .125);
            border-radius: 10px;
            box-shadow: 0 3px 10px rgb(0 0 0 / 55%);
            background-color: #00000008;
        }
        
        .hr-sc {
            background-image: linear-gradient(90deg, hsla(0, 0%, 100%, 0), #fff, hsla(0, 0%, 100%, 0));
            background-color: transparent;
            margin: 1rem 0;
            color: inherit;
            border: 0;
            opacity: .25;
        }
        
        
        /* ======= AlertBox ======== */
        
        .scAlerts {
            padding: 15px 15px 15px 45px;
            border-radius: 2px;
            position: relative;
            margin-bottom: 10px;
            color: #fff;
        }
        
        .scAlerts_error {
            background: #d70014;
            color: white;
            border-radius: 9px;
        }
        
        .scAlerts_warning {
            background: #ff7600;
            color: #ffffff;
            border-radius: 9px;
        }
        
        .scAlerts_success {
            background: #0e8506;
            color: #ffffff;
            border-radius: 9px;
        }
        
        .scAlerts_info,
        .scAlerts_information {
            background: #00a1ff;
            color: white;
            border-radius: 9px;
        }
        
        .toggle {
            cursor: pointer;
            display: inline-block;
        }
        
        .toggle-switch {
            display: inline-block;
            background: rgb(160, 160, 160);
            border-radius: 16px;
            width: 58px;
            height: 32px;
            position: relative;
            vertical-align: middle;
            transition: background 0.25s;
        }
        
        .toggle-switch:before,
        .toggle-switch:after {
            content: "";
        }
        
        .toggle-switch:before {
            display: block;
            background: linear-gradient(to bottom, #fff 0%, #eee 100%);
            border-radius: 50%;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.25);
            width: 24px;
            height: 24px;
            position: absolute;
            top: 4px;
            left: 4px;
            transition: left 0.25s;
        }
        
        .toggle:hover .toggle-switch:before {
            background: linear-gradient(to bottom, #fff 0%, #fff 100%);
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.5);
        }
        
        .toggle-checkbox:checked+.toggle-switch {
            background: #056b2e;
        }
        
        .toggle-checkbox:checked+.toggle-switch:before {
            left: 30px;
        }
        
        .toggle-checkbox {
            position: absolute;
            visibility: hidden;
        }
        
        .toggle-label {
            margin-left: 5px;
            position: relative;
            top: 2px;
        }
        </style>';
    }
    /**
     * Settings
     *
     * @since 1.0
    */
    public function settings()
    {
        register_setting('scmm', 'scmm-enabled');
        register_setting('scmm', 'scmm-content');
        register_setting('scmm', 'scmm_add_widget_areas');
        register_setting('scmm', 'scmm_analytify');
        register_setting('scmm', 'scmm_code_snippet');
        register_setting('scmm', 'scmm-site-title');
        register_setting('scmm', 'scmm-roles');
        register_setting('scmm', 'scmm-mode');

        // set the content
        scmm_set_content();
    }
    /**
     * Settings page
     *
     * @since 1.0
    */
    public function settingsPage()
    {
        ?>
 
        <div class="wrap">
            <h2><b><?php _e('Maintenance du site.', SCMM_PLUGIN_DOMAIN); ?></b></h2>
            <p><?php _e('Notre plugin à était éditer pour une amélioration de la page de maintenance, dont avec un style plus adapter, qui inclus le mode sombre ou claire. Notre plugin et totalement français.', SCMM_PLUGIN_DOMAIN); ?></p>
            <hr class="hr-sc"/><br/>
            <div class="card-sc">
                <form method="post" action="options.php">
                    <?php settings_fields('scmm'); ?>
                    <?php do_settings_sections('scmm'); ?>

                    <?php $this->notify(); ?>
                    
                    <table class="form-table">
                        
                        <tr valign="top">
                            <th scope="row">
                                <label for="scmm_enabled"><?php _e('Activation / Désactivation :', SCMM_PLUGIN_DOMAIN); ?></label>
                            </th>
                            <td>
                                <?php $scmm_enabled = esc_attr(get_option('scmm-enabled')); ?>
                                <label class="toggle">
                                    <input class="toggle-checkbox" type="checkbox" id="scmm_enabled" name="scmm-enabled" value="1" <?php checked($scmm_enabled, 1); ?>>
                                    <div class="toggle-switch"></div>
                                </label>
                                <!--
                                <input type="checkbox" id="scmm_enabled" name="scmm-enabled" value="1" <?php checked($scmm_enabled, 1); ?>>-->
                                <?php if ($scmm_enabled) : ?>
                                    <p class="description"><?php echo scmm_get_defaults('scmm_enabled'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        

                        <tr>
                            <th scope="row"><?php _e('Type de la maintenance :', SCMM_PLUGIN_DOMAIN); ?></th>
                            <td>
                                <?php $scmm_mode = esc_attr(get_option('scmm-mode')); ?>
                                <?php $mode_default = $scmm_mode == 'default' ? true : false; ?>
                                <?php $mode_cs = $scmm_mode == 'cs' ? true : false; ?>
                                <label>
                                    <input name="scmm-mode" type="radio" value="default" <?php checked($mode_default, 1); ?>>
                                    <?php _e('Maintenance du site ', SCMM_PLUGIN_DOMAIN); ?> <i>(<?php _e('Default', SCMM_PLUGIN_DOMAIN); ?>)</i>
                                </label>&nbsp;&nbsp;
                                <label>
                                    <input name="scmm-mode" type="radio" value="cs" <?php checked($mode_cs, 1); ?>>
                                    <?php _e('Site internet bientôt disponible', SCMM_PLUGIN_DOMAIN); ?>
                                </label>
                                <p class="description">
                                    <?php _e('<br/><p class="scAlerts scAlerts_info" style="line-height:18px;">Si vous mettez votre site en <b>maintenance</b> pendant une période plus longue, vous devez le définir sur <b>"Site internet bientôt disponible"</b>.<br/>Sinon, utilisez le mode <b>"Maintenance en cours"</b>.</p>', SCMM_PLUGIN_DOMAIN); ?><br/>
                                    <?php _e('<i>La valeur par défaut définit HTTP sur 503, prochainement définira HTTP sur 200.</i>', SCMM_PLUGIN_DOMAIN); ?> <a href="https://en.wikipedia.org/wiki/List_of_HTTP_status_codes" target="blank"><?php _e('En savoir plus.', SCMM_PLUGIN_DOMAIN); ?></a>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <?php _e('Autres :', SCMM_PLUGIN_DOMAIN); ?>
                            </th>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg('scmm', 'preview', bloginfo('url'))); ?>" target="_blank" class="button button-primary"><?php _e('Voir l\'aperçu', SCMM_PLUGIN_DOMAIN); ?></a>
                                <a class="button button-warning support" href="<?php echo SCMM_SUPPORT_LINK ?>" target="_blank"><?php _e('Support technique', SCMM_PLUGIN_DOMAIN); ?></a>
                                <a class="button button-secondary" href="<?php echo SCMM_RELEASES_LINK ?>" target="_blank"><?php _e('Releases', SCMM_PLUGIN_DOMAIN); ?></a>
                            </td>
                        </tr>
                        
                        <tr>
                            <th colspan="2">
                                <?php $this->editor_content(); ?>
                            </th>
                        </tr>
                        
                    </table>

                    <div class="card-sc">                
                        <a href="#" class="scmm-advanced-settings">
                            <span class="scmm-advanced-settings__label-advanced">
                                <?php _e('Réglages avancés', SCMM_PLUGIN_DOMAIN); ?>
                            </span>
                            <span class="scmm-advanced-settings__label-hide-advanced" style="display: none;">
                                <?php _e('Masquer les paramètres avancés', SCMM_PLUGIN_DOMAIN); ?>
                            </span>
                        </a>
                        

                        <table class="form-table form--scmm-advanced-settings" style="display: none">
                        
                            <tr valign="top">
                                <th scope="row">
                                    <label for="scmm_add_widget_areas"><?php _e('Ajouter des zones de widget au-dessus et en dessous du contenu', SCMM_PLUGIN_DOMAIN); ?></label>
                                </th>
                                <td>
                                    <?php $scmm_add_widget_areas = esc_attr(get_option('scmm_add_widget_areas')); ?>
                                    <label class="toggle">
                                        <input class="toggle-checkbox" type="checkbox" id="scmm_add_widget_areas" name="scmm_add_widget_areas" value="1" <?php checked($scmm_add_widget_areas, 1); ?>>
                                        <div class="toggle-switch"></div>
                                    </label>
                                    <!--<input type="checkbox" id="scmm_add_widget_areas" name="scmm_add_widget_areas" value="1" <?php checked($scmm_add_widget_areas, 1); ?>>-->
                                    <?php if ($scmm_add_widget_areas) : ?>
                                        <p class="description"><?php echo scmm_get_defaults('scmm_add_widget_areas'); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <tr valign="middle">
                                <th scope="row"><?php _e('Titre de la page :', SCMM_PLUGIN_DOMAIN); ?></th>
                                <td>
                                    <?php $scmm_site_title = esc_attr(get_option('scmm-site-title')); ?>
                                    <input name="scmm-site-title" type="text" id="scmm-site-title" placeholder="<?php echo $this->site_title(); ?>" value="<?php echo $scmm_site_title; ?>" class="regular-text">
                                    <p class="description"><?php _e('Remplace le titre méta du site par défaut.', SCMM_PLUGIN_DOMAIN); ?></p>
                                </td>
                            </tr>

                            <?php $options = get_option('scmm-roles'); ?>
                            <?php $wp_roles = get_editable_roles(); ?>
                            <?php if ($wp_roles && is_array($wp_roles)) : ?>
                                <tr valign="top">
                                    <th scope="row"><?php _e('Rôles d\'utilisateur', SCMM_PLUGIN_DOMAIN); ?>
                                        <p class="description"><?php _e('Cochez ceux qui peuvent accéder au front-end de votre site Web si le mode maintenance est activé', SCMM_PLUGIN_DOMAIN); ?>.</p>
                                        <p class="description"><?php _e('Veuillez noter que cela ne s\'applique PAS à la zone d\'administration', SCMM_PLUGIN_DOMAIN); ?>.</p>
                                        <p><a href="#" class="scmm-toggle-all"><?php _e('Tout cocher', SCMM_PLUGIN_DOMAIN); ?></a></p>
                                    </th>
                                    <td>
                                        <?php foreach ($wp_roles as $role => $role_details) :  ?>
                                            <?php if ($role !== 'administrator') : ?>
                                            <fieldset>
                                                <legend class="screen-reader-text">
                                                    <span><?php echo (isset($options[$role])) ? $options[$role] : ''; ?></span>
                                                </legend>
                                                <label>
                                                    <input type="checkbox" class="scmm-roles" name="scmm-roles[<?php echo $role; ?>]" value="1" <?php checked(isset($options[$role]), 1); ?> /> <?php echo $role_details['name']; ?>
                                                </label>
                                            </fieldset>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <tr valign="top">
                                    <th scope="row" colspan="2">
                                        <p class="description"><?php _e('Le contrôle des rôles d\'utilisateur n\'est actuellement pas disponible sur votre site Web. Pardon!', SCMM_PLUGIN_DOMAIN); ?></p>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php
                                // do we have Analytify installed and linked to google?
                                wp_cache_delete('analytify_ua_code', 'options'); // see https://wordpress.stackexchange.com/questions/100040/can-i-force-get-option-to-go-back-to-the-db-instead-of-cache
                                $ua_code = get_option('analytify_ua_code'); ?>
                                <?php if ($ua_code) {
                                    $scmm_analytify = esc_attr(get_option('scmm_analytify')); ?>
                                    <tr valign="top">
                                        <th scope="row">
                                            <label for="scmm_analytify"><?php echo sprintf(__('Add Google Analytics code', SCMM_PLUGIN_DOMAIN)); ?></label>
                                        </th>
                                        <td>
                                            <input type="checkbox" id="scmm_analytify" name="scmm_analytify" value="1" <?php checked($scmm_analytify, 1); ?>>
                                            <?php echo sprintf(__('for Analytics profile <b>%s</b> (<a href="/wp-admin/admin.php?page=analytify-settings">configured in Analytify</a>)', SCMM_PLUGIN_DOMAIN), $ua_code); ?>
                                            <p class="description">
<?php _e('Since you have the Analytify plugin installed, this will add Google Analytics tracking code to the maintenance page.', SCMM_PLUGIN_DOMAIN); ?>
                                            </p>
                                        </td>
                                    </tr>
                                <?php
                                } ?>
                            <!--
                            <tr valign="middle">
                                <th id="custom_css" scope="row"><?php _e('Custom Stylesheet', SCMM_PLUGIN_DOMAIN); ?></th>
                                <td>
                                    <?php $scmm_site_title = esc_attr(get_option('scmm-site-title')); ?>
                                    <?php $scmm_stlylesheet_filename = $this->get_css_filename(); ?>
                                    <?php $scmm_has_custom_stylsheet = (bool) $this->get_custom_stylesheet_url(); ?>
                                    <?php if ($scmm_has_custom_stylsheet) : ?>
                                        <p>
                                            <span style="line-height: 1.3; font-weight: 600; color: green;">You are currently using custom stylesheet.</span>
                                            <span class="description">(<?php _e("'$scmm_stlylesheet_filename' file in your theme folder", SCMM_PLUGIN_DOMAIN); ?>)</span>
                                        </p>
                                    <?php else : ?>
                                        
                                        <p class="description"><?php _e("For custom stylesheet, add '$scmm_stlylesheet_filename' file to your theme folder. If your custom stylesheet file is picked up by the Maintenance Mode, it will be indicated here.", SCMM_PLUGIN_DOMAIN); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>-->

                            <tr valign="top">
                                <th scope="row">
                                    <label for="scmm_code_snippet"><?php _e('Injecter un extrait de code', SCMM_PLUGIN_DOMAIN); ?></label>
                                </th>
                                <td>
                                    <textarea id="scmm_code_snippet" name="scmm_code_snippet" style="width:100%;height:150px"><?php echo esc_attr(get_option('scmm_code_snippet')); ?></textarea>
                                    <p class="description">
                                        <?php _e('Ceci est utile pour ajouter un extrait Javascript à la page de maintenance.', SCMM_PLUGIN_DOMAIN); ?>
                                        <?php
                                        if ($ua_code) {
                                            _e("REMARQUE : si vous utilisez l'option ci-dessus pour ajouter le code Google Analytics, ne collez PAS le code de suivi GA ici.", SCMM_PLUGIN_DOMAIN);
                                        } ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <?php submit_button(); ?>
                </form>
                <?php echo 'Version : '.SCMM_VERSION;?>
            </div>
        </div>
        <script>
          jQuery(document).ready(function() {
            jQuery('.scmm-advanced-settings').on('click', function(event) {
              event.preventDefault();
              jQuery('.form--scmm-advanced-settings').toggle();
              if (jQuery('.form--scmm-advanced-settings').is(':visible')) {
                jQuery(this).find('.scmm-advanced-settings__label-advanced').hide();
                jQuery(this).find('.scmm-advanced-settings__label-hide-advanced').show();
              } else {
                jQuery(this).find('.scmm-advanced-settings__label-advanced').show();
                jQuery(this).find('.scmm-advanced-settings__label-hide-advanced').hide();
              }
            });
            jQuery('.scmm-toggle-all').on('click', function(event) {
              event.preventDefault();
              var checkBoxes = jQuery("input.scmm-roles");
              checkBoxes.prop("checked", !checkBoxes.prop("checked"));
            });
          });
        </script>
    <?php
    }
    /**
     * Admin bar indicator
     *
     * @since 1.1
    */
    public function indicator($wp_admin_bar)
    {
        $enabled = apply_filters('scmm_admin_bar_indicator_enabled', $enabled = true);

        if (!current_user_can($this->get_relevant_cap())) {
            return false;
        }

        if (!$enabled) {
            return false;
        }

        $is_enabled = get_option('scmm-enabled');
        $status = _x('Maintenance : désactivé', 'Admin bar indicator', SCMM_PLUGIN_DOMAIN);

        if ($is_enabled) {
            $status = _x('Maintenance : activé', 'Admin bar indicator', SCMM_PLUGIN_DOMAIN);
        }

        $indicatorClasses = $is_enabled ? 'scmm-indicator scmm-indicator--enabled' : 'scmm-indicator';

        $indicator = [
            'id' => 'scmm-indicator',
            'title' => '<span class="ab-icon dashicon-before dashicons-hammer"></span> ' . $status,
            'parent' => false,
            'href' => get_admin_url(null, 'options-general.php?page=sc-maintenance-mode'),
            'meta' => [
                'title' => _x('Maintenance du site', 'Admin bar indicator', SCMM_PLUGIN_DOMAIN),
                'class' => $indicatorClasses,
            ]
        ];

        $wp_admin_bar->add_node($indicator);
    }

    /**
     * Plugin action links
     *
     * @since 1.1
     * @return mixed
    */
    public function action_links($links)
    {
        $links[] = '<a href="' . get_admin_url(null, 'options-general.php?page=sc-maintenance-mode') . '">' . _x('Settings', 'Plugin Settings link', SCMM_PLUGIN_DOMAIN) . '</a>';
        $links[] = '<a target="_blank" href="' . SCMM_SUPPORT_LINK . '">' . _x('Support', 'Plugin Support link', SCMM_PLUGIN_DOMAIN) . '</a>';

        return $links;
    }

    /**
     * Default site title for maintenance mode
     *
     * @since 2.0
     * @return string
     */
    public function site_title()
    {
        return apply_filters('scmm_site_title', get_bloginfo('name') . ' - ' . __('Site en maintenance', SCMM_PLUGIN_DOMAIN));
    }

    /**
     * Manage capabilities
     *
     * @since 2.0
     */
    public function manage_capabilities()
    {
        $wp_roles = get_editable_roles();
        $all_roles = get_option('scmm-roles');

        // extra checks
        if ($wp_roles && is_array($wp_roles)) {
            foreach ($wp_roles as $role => $role_details) {
                $get_role = get_role($role);

                if (is_array($all_roles) && array_key_exists($role, $all_roles)) {
                    $get_role->add_cap(SCMM_VIEW_SITE_CAP);
                } else {
                    $get_role->remove_cap(SCMM_VIEW_SITE_CAP);
                }
            }
        }

        // administrator by default
        $admin_role = get_role('administrator');
        $admin_role->add_cap(SCMM_VIEW_SITE_CAP);
        $admin_role->add_cap(SCMM_PLUGIN_CAP);
    }

    /**
     * Get mode
     *
     * @since 2.2
     * @return int
     */
    public function get_mode()
    {
        $mode = get_option('scmm-mode');
        if ($mode == 'cs') {
            // coming soon page
            return 200;
        }

        // maintenance mode
        return 503;
    }

    /**
     * Get content
     *
     * @since 2.3
     * @return mixed
     */
    public function get_content()
    {
        $get_content = get_option('scmm-content');
        $content = (!empty($get_content)) ? $get_content : scmm_get_defaults('maintenance_message');
        $content = apply_filters('wptexturize', $content);
        $content = apply_filters('wpautop', $content);
        $content = apply_filters('shortcode_unautop', $content);
        $content = apply_filters('prepend_attachment', $content);
        $content = apply_filters('wp_make_content_images_responsive', $content);
        $content = apply_filters('convert_smilies', $content);
        $content = apply_filters('scmm_content', $content);

        // analytify support
        $analytify = $this->analytify_support();

        // custom code snippets
        $code = get_option('scmm_code_snippet');

        // add widgets
        $widget_before = $this->widget_before();
        $widget_after = $this->widget_after();

        // add custom stylesheet
        $stylesheet = $this->custom_stylesheet();

        return $analytify . $code . $stylesheet . $widget_before . $content . $widget_after;
    }

    /**
     * Get title
     *
     * @since 2.3
     * @return string
     */
    public function get_title()
    {
        $site_title = get_option('scmm-site-title');
        return $site_title ? $site_title : $this->site_title();
    }

    /**
     * Get CSS file name
     *
     * @since 2.4
     * @return string
     */
    
    public function get_css_filename()
    {
        return apply_filters('scmm_css_filename', 'maintenance.min.css');
    }

    /**
     * Custom stylsheet
     *
     * @since 2.4
     * @return void
     */
    public function custom_stylesheet()
    {
        /*
        $stylesheet = '';
        $url = $this->get_custom_stylesheet_url();
        if ($url) {
            $stylesheet = '<style type="text/css">' . file_get_contents($url) . '</style>';
            //'<link rel="stylesheet" href="'.$css_url.'?ver=6.0.3" type="text/css"/>';
        }
        return $stylesheet;
        */
        
        $stylesheet = '';
        $url = 'https://dl.sidl-corporation.fr/dl/css/maintenance.min.css';
        if ($url) {
            $stylesheet = //'<style type="text/css">' . file_get_contents($url) . '</style>';
            '<link rel="stylesheet" href="'.$url.'?ver=0.0.1" type="text/css"/>';
        }
        return $stylesheet;
    }

    /**
     * Check for custom stylesheet
     *
     * @since 2.4
     * @return boolean
     */
    
    public function get_custom_stylesheet_url()
    {
        /*
        $stylesheet_url = false;
        $url_filename = $this->get_css_filename();
        if (!validate_file($url_filename)) {
            $url = apply_filters('scmm_css_url', get_stylesheet_directory().'/'.$url_filename.'?ver=0.0.1');

            if (file_exists($url)) {
                $stylesheet_url = $url;
            }
        }
        return $stylesheet_url;
        */
        $stylesheet_url = false;
        $url_filename = 'https://dl.sidl-corporation.fr/dl/css/maintenance.min.css';
        if (!validate_file($url_filename)) {
            $url = apply_filters('scmm_css_url', $url_filename.'?ver=0.0.1');

            if (file_exists($url)) {
                $stylesheet_url = $url;
            }
        }
        return $stylesheet_url;
    }

    /**
     * Editor content
     *
     * @since 2.4
     * @return void
     */
    public function editor_content()
    {
        $content = get_option('scmm-content');
        $editor_id = 'scmm-content';
        wp_editor($content, $editor_id);
    }

    /**
     * Before maintenance mode
     */
    public function before_maintenance_mode()
    {
        // remove jetpack sharing
        remove_filter('the_content', 'sharing_display', 19);
    }

    /**
     * Is maintenance enabled?
     *
     * @since 2.3
     * @return boolean
     */
    public function enabled()
    {
        // enabled
        if (get_option('scmm-enabled') || isset($_GET['scmm']) && $_GET['scmm'] == 'preview') {
            return true;
        }

        // disabled
        return false;
    }

    /**
     * Maintenance Mode
     *
     * @since 1.0
    */
    public function maintenance()
    {
        if (!$this->enabled()) {
            return false;
        }

        do_action('scmm_before_mm');

        // TML Compatibility
        if (class_exists('Theme_My_Login')) {
            if (Theme_My_Login::is_tml_page()) {
                return;
            }
        }

        if (!(current_user_can(SCMM_VIEW_SITE_CAP) || current_user_can('super admin')) || (isset($_GET['scmm']) && $_GET['scmm'] == 'preview')) {
            wp_die($this->get_content(), $this->get_title(), ['response' => $this->get_mode()]);
        }
    }

    /**
     * Get releavant cap
     *
     * This has been implementend due to lack of compatiblity with user role
     * and capabilities plugins that caused some users problems viewing the settings
     * page. So if user is a super admin, plugin will use 'delete_plugins' cap, otherwise
     * plugins' cap 'SCmm_control'
     *
     * @return void
     * @since 2.4.2
     */
    public function get_relevant_cap()
    {
        return is_super_admin() ? 'delete_plugins' : SCMM_PLUGIN_CAP;
    }

    /**
     * Notify if cache plugin detected
     *
     * @since 1.2
    */
    public function notify()
    {
        $cache_plugin_enabled = $this->cache_plugin();
        if (!empty($cache_plugin_enabled)) {
            $class = 'error';
            $message = $this->cache_plugin();
            if (isset($_GET['settings-updated'])) {
                echo '<div class="' . $class . '"><p>' . $message . '</p></div>';
            }
        }
    }

    /**
     * Register widget sidebars
     *
     * @return void
     */
    public function register_widget_sidebars()
    {
        if (function_exists('register_sidebar')) {
            register_sidebar([
                'id' => 'scmm-before',
                'name' => __('Mode maintenance - avant le contenu', SCMM_PLUGIN_DOMAIN),
                'description' => __('', SCMM_PLUGIN_DOMAIN),
                'before_widget' => "\n" . '<div id="%1$s" class="widget %2$s">',
                'after_widget' => '</div>' . "\n",
            ]);

            register_sidebar([
                'id' => 'scmm-after',
                'name' => __('Mode maintenance - après le contenu', SCMM_PLUGIN_DOMAIN),
                'description' => __('', SCMM_PLUGIN_DOMAIN),
                'before_widget' => "\n" . '<div id="%1$s" class="widget %2$s">',
                'after_widget' => '</div>' . "\n",
            ]);
        }
    }

    /**
     * Widget
     *
     * @param string $id
     * @return void
     */
    public function widget($id)
    {
        $widget = '';

        if (get_option('scmm_add_widget_areas')) {
            ob_start();
            dynamic_sidebar(sprintf('scmm-%s', $id));
            $widget = ob_get_clean();
        }

        return $widget;
    }

    /**
     * Widget before
     *
     * @return void
     */
    public function widget_before()
    {
        return $this->widget('before');
    }

    /**
     * Widget after
     *
     * @return void
     */
    public function widget_after()
    {
        return $this->widget('after');
    }

    /**
     * Analytify plugin support
     *
     * @since 2.4
     * @return void
     */
    public function analytify_support()
    {
        // Do we have a UA code from Analytify plugin?
        $analytify = '';
        if (get_option('scmm_analytify') && $ua_code = get_option('analytify_ua_code')) {
            // Yes, so we can generate the code to inject
            $analytify = <<<EOD
                <script>
                  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
                  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
                  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
                  })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

                  ga('create', '{$ua_code}', 'auto');
                  ga('send', 'pageview');

                </script>
EOD;
        }

        return $analytify;
    }

    /**
     * Detect cache plugins
     *
     * @since 1.2
     * @return string
    */
    public function cache_plugin()
    {
        $message = '';
        // add wp super cache support
        if (in_array('wp-super-cache/wp-cache.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            $message = SCmm_get_defaults('warning_wp_super_cache');
        }

        // add w3 total cache support
        if (in_array('w3-total-cache/w3-total-cache.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            $message = SCmm_get_defaults('warning_w3_total_cache');
        }

        // add comet cache support
        if (in_array('comet-cache/comet-cache.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            $message = SCmm_get_defaults('warning_comet_cache');
        }

        return $message;
    }
}
// initialise plugin.
$scMaintenanceMode = new scMaintenanceMode();