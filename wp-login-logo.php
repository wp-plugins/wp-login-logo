<?php

/**
 * WP Login Logo
 *
 * @link http://cris9400.nerdnet.it/work/2015/05/21/wp-login-logo/
 *
 * @author Cris9400
 * @version 1.0.0
 * @package WP_Login_Logo
 */

 /*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/
 
/*
Plugin Name: WP Login Logo
Plugin URI: 
Description: Change login logo (wp-login.php).
Version: 1.0.0
Author: Cris9400
Author URI: http://cris9400.nerdnet.it/
Text Domain: wp-login-logo
Domain Path: /languages/
License: GPLv2 or later
*/

//Enable the plugin for the init hook, but only if WP is loaded. Calling this php file directly will do nothing.
if(defined('ABSPATH') && defined('WPINC')) {
    add_action("wp_loaded",array("WPLoginLogo","init"));
}

/**
 * Main class for WP Login Logo, does it all.
 *
 * @package WP_Login_Logo
 * @todo Uninstall plugin hook
 * @todo I18n Support
 */

class WPLoginLogo
{
    /**
     * @const VERSION The current plugin version
     */
    const VERSION = '1.0.0';

    /**
     * @const WPURL Link to author site
     */
    const WPURL = 'http://cris9400.nerdnet.it/work/2015/05/21/wp-login-logo/';

    /**
     * Fire up the plugin and register them hooks
     */
    public static function init()
    {
        load_plugin_textdomain('wp-login-logo', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        add_action('admin_menu', array('WPLoginLogo', 'registerAdminMenu'));
        add_filter('plugin_action_links', array('WPLoginLogo', 'registerPluginSettingsLink'),10,2);
        add_action('wp_ajax_getImageData', array('WPLoginLogo', 'getImageData'));
        add_action('wp_ajax_displayPreviewImg', array('WPLoginLogo', 'displayPreviewImg'));
        add_action('login_head', array('WPLoginLogo', 'replaceLoginLogo'));
        add_filter('login_headerurl', array('WPLoginLogo', 'replaceLoginUrl'));
        add_filter("login_headertitle", array('WPLoginLogo', 'replaceLoginTitle'));
        register_uninstall_hook(self::getBaseName(), array('WPLoginLogo', 'uninstall'));

        //Load only on plugin admin page
        if (isset($_GET['page']) && $_GET['page'] == self::getBaseName()) {
            add_action('admin_enqueue_scripts', array('WPLoginLogo', 'myAdminScriptsAndStyles'));
        }
    }
    /**
     * Load scripts and styles for plugin admin page
     */
    public static function myAdminScriptsAndStyles()
    {
        wp_register_style('wp-login-logo', self::getPluginDir() . '/wp-login-logo-min.css', array(), self::VERSION);
        wp_register_script('wp-login-logo', self::getPluginDir() . '/wp-login-logo-min.js', array('jquery','media-upload','thickbox','underscore'), self::VERSION);

        wp_enqueue_media();
        wp_enqueue_style('wp-login-logo');
        wp_enqueue_script('wp-login-logo');
    }

    /**
     * Setup admin menu and add options page
     */
    public static function registerAdminMenu()
    {
        if (function_exists('add_options_page')) {
            $page_title = __('WP Login Logo Settings', 'wp-login-logo');
            $menu_title = 'WP Login Logo';
            $capability = 'manage_options';
            $menu_slug = self::getBaseName();
            $function = array('WPLoginLogo','showOptionsPage');

            add_options_page($page_title, $menu_title, $capability, $menu_slug, $function);
        }
    }

    /**
     * Add settings link to plugin page
     *
     * @param array $links Array of plugin option links
     * @param string $file Handle to plugin filename
     * @return array Modified list of plugin option links
     */
    public static function registerPluginSettingsLink($links, $file)
    {
        $this_plugin = self::getBaseName();

        if ($file == $this_plugin) {
            $settings_link = '<a href="' . admin_url() . 'options-general.php?page=' . $this_plugin . '">' . __('Settings', 'wp-login-logo') . '</a>';
            array_unshift($links, $settings_link);
        }

        return $links;
    }

    /**
     * Generate the HTML to display the plugin settings page
     *
     * @TODO seperate presentation logic
     */
    public static function showOptionsPage()
    {
        ?>

        <div class="wrap wp-login-logo">
            <?php screen_icon('edit-pages'); ?>
            <h2>WP Login Logo</h2>

            <div class="updated fade update-status">
                <p><strong><?php _e('Settings Saved', 'wp-login-logo'); ?></strong></p>
            </div>

            <p><?php printf(__('by %1$s from %2$s', 'wp-login-logo'), '<strong>Cris9400</strong>', '<strong><a href="http://cris9400.nerdnet.it" title="nn developer">Cris9400.NerdNET.it</a></strong>'); ?></p>

            <h3><?php _e('How it Works', 'wp-login-logo'); ?></h3>
            <ol>
                <li><?php _e('Use the WordPress media uploader to upload an image, or select one from the media library.', 'wp-login-logo'); ?></li>
                <li><?php _e('It is highly recommended that you select an image with a width less than 320px.', 'wp-login-logo'); ?></li>
                <li><?php _e('Select your desired image size and click "insert into post".', 'wp-login-logo'); ?></li>
            </ol>
            <form class="inputfields">
                <input id="upload-input" type="text" size="36" name="upload image" class="upload-image" value="" />
                <input id="upload-button" type="button" value="<?php _e('Upload Image', 'wp-login-logo'); ?>" class="upload-image" />
                <?php wp_nonce_field('wp_login_logo_action','wp_login_logo_nonce'); ?>
            </form>
            <div class="img-holder">
                <p><?php _e('Here is a preview of your selected image at actual size', 'wp-login-logo'); ?></p>
                <div class="img-preview"></div>
            </div>
        </div>

        <?php
    }

    /**
     * Replace the login logo on wp-admin
     */
    public static function replaceLoginLogo()
    {
        $img_data = get_option('wp_login_logo');

        // use https for background-image if on ssl
        if (is_ssl()) {
            $img_data['src'] = preg_replace( "/^http:/i", "https:", $img_data['src'] );
        }

        if ($img_data) {
            $style = '<style type="text/css">';
            $style .= sprintf('.login h1 a { background: transparent url("%s") no-repeat center top; background-size:%spx %spx; height: %spx; width:auto; }', $img_data['src'], $img_data['width'], $img_data['height'], $img_data['height']);
			$style .= '</style>';
            $style .= "\r\n" . '<!-- WP Login Logo ' . self::VERSION . ' ' . self::WPURL . ' -->' . "\r\n";
            echo $style;
        }
    }

    /**
     * Retrieve the img data via AJAX and save as wordpress option
     */
    public static function getImageData()
    {
        if (!empty($_POST) && check_admin_referer('wp_login_logo_action','wp_login_logo_nonce')) {
            if (current_user_can('manage_options')) {
                // sanitize inputs
                $img_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
                $img_size = filter_input(INPUT_POST, 'size', FILTER_SANITIZE_STRING);

                // get the img at the correct size
                $img = wp_get_attachment_image_src($img_id, $img_size);

                // save src + attribs in the DB
                $img_data['id'] = $img_id;
                $img_data['src'] = $img[0];
                $img_data['width'] = $img[1];
                $img_data['height'] = $img[2];

                update_option('wp_login_logo', $img_data);

                $returnval = json_encode(array('src' => $img_data['src'], 'id' => $img_data['id']));
                wp_die($returnval);
            }
        }
    }

    /**
     * Display the currently set login logo img
     */
    public static function displayPreviewImg()
    {
        if (!empty($_POST) && check_admin_referer('wp_login_logo_action','wp_login_logo_nonce')) {
            if (current_user_can('manage_options')) {
                $img_data = get_option('wp_login_logo');
                if ($img_data) {
                    $returnval = json_encode(array('src' => $img_data['src'], 'id' => $img_data['id']));
                }
                else {
                    $returnval = false;
                }
                wp_die($returnval);
            }
        }
    }

    /**
     * Remove saved options on uninstall
     */
    public static function uninstall()
    {
        if (!current_user_can('activate_plugins')) {
            wp_die("I\'m afraid I can\' do that.");
        }

        check_admin_referer('bulk-plugins');

        delete_option('wp_login_logo');
    }

    /**
     * Retrieve the Home URL
     *
     * @return string Home URL
     */
    public static function replaceLoginUrl()
    {
        return home_url();
    }

    /**
     * Retrieve the Site Description
     *
     * @return string Site Description
     */
    public static function replaceLoginTitle()
    {
        return get_bloginfo('description');
    }

    /**
     * Retrieve the unique plugin basename
     *
     * @return string Plugin basename
     */
    public static function getBaseName()
    {
        return plugin_basename(__FILE__);
    }

    /**
     * Retrieve the URL to the plugin basename
     *
     * @return string Plugin basename URL
     */
    public static function getPluginDir()
    {
        return WP_PLUGIN_URL . '/' . dirname(plugin_basename(__FILE__));
    }
}

?>