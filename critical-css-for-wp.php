<?php
/*
Plugin Name: Critical CSS For WP
Description: Critical css helps the browser to paint fast and render the above fold content of each web page, before downloading the complete css files.
Version: 1.0.1
Author: Magazine3
Author URI: https://magazine3.company/
Donate link: https://www.paypal.me/Kaludi/25
Text Domain:  criticalcssforwp
Domain Path: /languages
License: GPL2
*/
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

define('CRITICAL_CSS_FOR_WP_VERSION','1.0.1');
define('CRITICAL_CSS_FOR_WP_PLUGIN_URI', plugin_dir_url(__FILE__));
define('CRITICAL_CSS_FOR_WP_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * Critical css cache path
 **/
define('CRITICAL_CSS_FOR_WP_CSS_DIR', WP_CONTENT_DIR . "/cache/critical-css-for-wp/css/");

define('CCWP_CACHE_NAME', 'ccwp_cleared_timestamp');

define('CCWP_JS_EXCLUDE_CACHE_DIR', WP_CONTENT_DIR . "/cache/critical-css-for-wp/excluded-js/");
define('CCWP_JS_EXCLUDE_CACHE_URL', site_url("/wp-content/cache/critical-css-for-wp/excluded-js/"));

require_once CRITICAL_CSS_FOR_WP_PLUGIN_DIR . "includes/common.php";
require_once CRITICAL_CSS_FOR_WP_PLUGIN_DIR . "admin/settings.php";
require_once CRITICAL_CSS_FOR_WP_PLUGIN_DIR . "includes/class-critical-css.php";


add_action('admin_enqueue_scripts','ccfwp_admin_enqueue');
function ccfwp_admin_enqueue($check) {
    if ( !is_admin() ) {
        return;
    }
    if($check != 'toplevel_page_critical-css-for-wp'){
        return; 
    }
    wp_enqueue_script('ccfwp-datatable-script', CRITICAL_CSS_FOR_WP_PLUGIN_URI . '/admin/js/jquery.dataTables.min.js', ['jquery']);
    wp_enqueue_style( 'ccfwp-datatable-style', CRITICAL_CSS_FOR_WP_PLUGIN_URI . '/admin/js/jquery.dataTables.min.css' );

    $data = array(
        'ccfwp_security_nonce'         => wp_create_nonce('ccfwp_ajax_check_nonce')  
    );
    wp_register_script( 'ccfwp-admin-js', CRITICAL_CSS_FOR_WP_PLUGIN_URI . '/admin/js/script.js', array('ccfwp-datatable-script'), CRITICAL_CSS_FOR_WP_VERSION , true );
    wp_localize_script( 'ccfwp-admin-js', 'ccfwp_localize_data', $data );
    wp_enqueue_script( 'ccfwp-admin-js' );
}

add_action( 'init', 'load_js_data' );
function load_js_data(){
    require_once CRITICAL_CSS_FOR_WP_PLUGIN_DIR."includes/javascript/delay-js.php";
}

register_activation_hook( __FILE__, 'ccfwp_on_install' );

function ccfwp_on_install(){

    global $wpdb;
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $charset_collate = $engine = '';    
    
    if(!empty($wpdb->charset)) {
        $charset_collate .= " DEFAULT CHARACTER SET {$wpdb->charset}";
    } 
    if($wpdb->has_cap('collation') AND !empty($wpdb->collate)) {
        $charset_collate .= " COLLATE {$wpdb->collate}";
    }

    $found_engine = $wpdb->get_var("SELECT ENGINE FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = '".DB_NAME."' AND `TABLE_NAME` = '{$wpdb->prefix}posts';");
        
    if(strtolower($found_engine) == 'innodb') {
        $engine = ' ENGINE=InnoDB';
    }

    $found_tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}critical_css%';");    
        
    if(!in_array("{$wpdb->prefix}critical_css_for_wp_urls", $found_tables)) {
            
        dbDelta("CREATE TABLE `{$wpdb->prefix}critical_css_for_wp_urls` (
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
        ) ".$charset_collate.$engine.";");                
    }   

}