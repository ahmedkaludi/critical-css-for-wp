<?php
/**
 * Plugin Name: Reduce Unused CSS Solution with Critical CSS For WP
 * Description: Critical CSS For WP intends to provide great experience to the web page visitors by improving the performance of the web page. Here we'd remove the unused CSS which helps to paint fast and render the above fold content, before downloading the complete css files.
 * Version: 1.0.8
 * Author: Magazine3
 * Author URI: https://magazine3.company/
 * Donate link: https://www.paypal.me/Kaludi/25
 * Text Domain:  criticalcssforwp
 * Domain Path: /languages
 * License: GPL2
 */
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRITICAL_CSS_FOR_WP_VERSION', '1.0.8' );
define( 'CRITICAL_CSS_FOR_WP_PLUGIN_URI', plugin_dir_url( __FILE__ ) );
define( 'CRITICAL_CSS_FOR_WP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Critical css cache path
 */

define( 'CCWP_CACHE_NAME', 'ccwp_cleared_timestamp' );
define( 'CRITICAL_CSS_FOR_WP_CSS_DIR', WP_CONTENT_DIR . '/cache/critical-css-for-wp/css/' );
define( 'CCWP_JS_EXCLUDE_CACHE_DIR', WP_CONTENT_DIR . '/cache/critical-css-for-wp/excluded-js/' );
define( 'CCWP_JS_EXCLUDE_CACHE_URL', site_url( '/wp-content/cache/critical-css-for-wp/excluded-js/' ) );
define( 'CRITICAL_CSS_FOR_WP_CSS_DIR_ALT', WP_CONTENT_DIR . '/ccwp-cache/css/' );

require_once CRITICAL_CSS_FOR_WP_PLUGIN_DIR . 'includes/common.php';
require_once CRITICAL_CSS_FOR_WP_PLUGIN_DIR . 'admin/settings.php';
require_once CRITICAL_CSS_FOR_WP_PLUGIN_DIR . 'includes/class-critical-css.php';


add_action( 'admin_enqueue_scripts', 'ccfwp_admin_enqueue' );
function ccfwp_admin_enqueue( $check ) {
	if ( ! is_admin() ) {
		return;
	}
	if ( $check !== 'toplevel_page_critical-css-for-wp' ) {
		return;
	}
	wp_enqueue_script( 'ccfwp-datatable-script', CRITICAL_CSS_FOR_WP_PLUGIN_URI . '/admin/js/jquery.dataTables.min.js', array( 'jquery' ), CRITICAL_CSS_FOR_WP_VERSION, true );
	wp_enqueue_style( 'ccfwp-datatable-style', CRITICAL_CSS_FOR_WP_PLUGIN_URI . '/admin/js/jquery.dataTables.min.css', CRITICAL_CSS_FOR_WP_VERSION );

	$data = array(
		'ccfwp_security_nonce' => wp_create_nonce( 'ccfwp_ajax_check_nonce' ),
	);
	wp_register_script( 'ccfwp-admin-js', CRITICAL_CSS_FOR_WP_PLUGIN_URI . '/admin/js/script.js', array( 'ccfwp-datatable-script' ), CRITICAL_CSS_FOR_WP_VERSION, true );
	wp_localize_script( 'ccfwp-admin-js', 'ccfwp_localize_data', $data );
	wp_enqueue_script( 'ccfwp-admin-js' );
}

add_action( 'init', 'load_js_data' );
function load_js_data() {
	require_once CRITICAL_CSS_FOR_WP_PLUGIN_DIR . 'includes/javascript/delay-js.php';
}

register_activation_hook( __FILE__, 'ccfwp_on_activate' );

function ccfwp_on_activate( $network_wide ) {
	global $wpdb;

	if ( is_multisite() && $network_wide ) {
		$blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( $blog_id );
			ccfwp_on_install();
			restore_current_blog();
		}
	} else {
		ccfwp_on_install();
	}
}

function ccfwp_on_install() {
	$charset_collate = $engine = '';
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	if ( ! empty( $wpdb->charset ) ) {
		$charset_collate .= " DEFAULT CHARACTER SET {$wpdb->charset}";
	}
	if ( $wpdb->has_cap( 'collation' ) && ! empty( $wpdb->collate ) ) {
		$charset_collate .= " COLLATE {$wpdb->collate}";
	}

	$found_engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = %s AND `TABLE_NAME` = %s;', array( DB_NAME, $wpdb->prefix . 'posts' ) ) );

	if ( strtolower( $found_engine ) == 'innodb' ) {
		$engine = ' ENGINE=InnoDB';
	}

	$found_tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}critical_css%';" );

	if ( ! in_array( "{$wpdb->prefix}critical_css_for_wp_urls", $found_tables ) ) {

		dbDelta(
			"CREATE TABLE `{$wpdb->prefix}critical_css_for_wp_urls` (
            `id` bigint( 20 ) unsigned NOT NULL AUTO_INCREMENT,
            `url_id` bigint( 20 ) unsigned NOT NULL,            
            `type` varchar(20),
            `type_name` varchar(50),
            `url` varchar(300) NOT NULL,                 
            `status` varchar(20) NOT NULL default 'queue',                                          
            `cached_name` varchar(100),
            `created_at` datetime NOT NULL,
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            `failed_error` text  NOT NULL Default '',
             UNIQUE KEY `url` ( `url` ),               
             PRIMARY KEY (`id`)
        ) " . $charset_collate . $engine . ';'
		);
	}

}

function ccfwp_plugin_settings_links( $links ) {
	$custom_urls   = array();
	$custom_urls[] = '<a href="' . admin_url( 'admin.php?page=critical-css-for-wp' ) . '">' . __( 'Dashboard' ) . '</a>';
	$custom_urls[] = '<a href="' . admin_url( 'admin.php?page=critical-css-for-wp&tab=advance' ) . '">' . __( 'Settings' ) . '</a>';
	$custom_urls[] = '<a href="' . admin_url( 'admin.php?page=critical-css-for-wp&tab=support' ) . '">' . __( 'Support' ) . '</a>';
	return array_merge( $custom_urls, $links );
}
  $ccfwp_plugin = plugin_basename( __FILE__ );
  add_filter( "plugin_action_links_$ccfwp_plugin", 'ccfwp_plugin_settings_links' );