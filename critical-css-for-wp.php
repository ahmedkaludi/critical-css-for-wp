<?php
/**
 * Plugin Name: Reduce Unused CSS Solution with Critical CSS For WP
 * Description: Critical CSS For WP intends to provide great experience to the web page visitors by improving the performance of the web page. Here we'd remove the unused CSS which helps to paint fast and render the above fold content, before downloading the complete css files.
 * Version: 1.0.17
 * Author: Magazine3
 * Author URI: https://magazine3.company/
 * Donate link: https://www.paypal.me/Kaludi/25
 * Text Domain:  critical-css-for-wp
 * Domain Path: /languages
 * License: GPL2
 *
 * @package critical-css-for-wp
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRITICAL_CSS_FOR_WP_VERSION', '1.0.17' );
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
require_once CRITICAL_CSS_FOR_WP_PLUGIN_DIR . 'includes/class-critical-css-for-wp.php';


add_action( 'admin_enqueue_scripts', 'ccfwp_admin_enqueue' );

/**
 * Enqueue admin scripts
 *
 * @param string $check The page hook.
 */
function ccfwp_admin_enqueue( $check ) {
	if ( ! is_admin() ) {
		return;
	}
	if ( 'toplevel_page_critical-css-for-wp' !== $check ) {
		return;
	}
	wp_enqueue_script( 'ccfwp-datatable-script', CRITICAL_CSS_FOR_WP_PLUGIN_URI . '/admin/js/jquery.dataTables.min.js', array( 'jquery' ), CRITICAL_CSS_FOR_WP_VERSION, true );
	wp_enqueue_style( 'ccfwp-datatable-style', CRITICAL_CSS_FOR_WP_PLUGIN_URI . '/admin/css/jquery.dataTables.min.css', array(), CRITICAL_CSS_FOR_WP_VERSION );

	$data = array(
		'ccfwp_security_nonce' => wp_create_nonce( 'ccfwp_ajax_check_nonce' ),
	);
	$min  = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	wp_register_script( 'ccfwp-admin-js', CRITICAL_CSS_FOR_WP_PLUGIN_URI . "/admin/js/script{$min}.js", array( 'ccfwp-datatable-script' ), CRITICAL_CSS_FOR_WP_VERSION, true );
	wp_localize_script( 'ccfwp-admin-js', 'ccfwp_localize_data', $data );
	wp_enqueue_script( 'ccfwp-admin-js' );
}

add_action( 'init', 'ccfwp_load_js_data' );

/**
 * Load js data
 */
function ccfwp_load_js_data() {
	require_once CRITICAL_CSS_FOR_WP_PLUGIN_DIR . 'includes/javascript/delay-js.php';
}

register_activation_hook( __FILE__, 'ccfwp_on_activate' );
/**
 * On plugin activation
 *
 * @param bool $network_wide Network wide activation.
 */
function ccfwp_on_activate( $network_wide ) {
	global $wpdb;

	if ( is_multisite() && $network_wide ) {
		$blog_ids = get_sites();
		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( $blog_id );
			ccfwp_on_install();
			restore_current_blog();
		}
	} else {
		ccfwp_on_install();
	}
}

/**
 * On plugin install add tables
 */
function ccfwp_on_install() {
	$charset_collate = '';
	$engine          = '';

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	if ( ! empty( $wpdb->charset ) ) {
		$charset_collate .= " DEFAULT CHARACTER SET {$wpdb->charset}";
	}
	if ( $wpdb->has_cap( 'collation' ) && ! empty( $wpdb->collate ) ) {
		$charset_collate .= " COLLATE {$wpdb->collate}";
	}
	//phpcs:ignore -- Reason: No need to escape query
	$found_engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = %s AND `TABLE_NAME` = %s;', array( DB_NAME, $wpdb->prefix . 'posts' ) ) );

	if ( strtolower( $found_engine ) === 'innodb' ) {
		$engine = ' ENGINE=InnoDB';
	}
	//phpcs:ignore -- Reason:  No need to escape query
	$found_tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}critical_css%';" );

	if ( ! in_array( "{$wpdb->prefix}critical_css_for_wp_urls", $found_tables ) ) {

		dbDelta(
			"CREATE TABLE `{$wpdb->prefix}critical_css_for_wp_urls` (
            `id` bigint( 20 ) unsigned NOT NULL AUTO_INCREMENT,
            `url_id` bigint( 20 ) unsigned NOT NULL,            
            `type` varchar(20),
            `type_name` varchar(50),
            `url` varchar(250) NOT NULL,                 
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
/**
 * Add settings link
 *
 * @param array $links The plugin links.
 */
function ccfwp_plugin_settings_links( $links ) {
	$custom_urls   = array();
	$custom_urls[] = '<a href="' . esc_url(admin_url( 'admin.php?page=critical-css-for-wp' )) . '">' . esc_html__( 'Dashboard' ,'critical-css-for-wp') . '</a>';
	$custom_urls[] = '<a href="' .  esc_url(admin_url( 'admin.php?page=critical-css-for-wp&tab=advance' )) . '">' . esc_html__( 'Settings' ,'critical-css-for-wp') . '</a>';
	$custom_urls[] = '<a href="' .  esc_url(admin_url( 'admin.php?page=critical-css-for-wp&tab=support' )) . '">' . esc_html__( 'Support' , 'critical-css-for-wp') . '</a>';
	return array_merge( $custom_urls, $links );
}
	$ccfwp_plugin = plugin_basename( __FILE__ );
	add_filter( "plugin_action_links_$ccfwp_plugin", 'ccfwp_plugin_settings_links' );
