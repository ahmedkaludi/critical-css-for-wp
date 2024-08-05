<?php
/**
 * Admin setup for the plugin
 *
 * @since 1.0
 * @package settings
 * @function    superpwa_add_menu_links()           Add admin menu pages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add admin menu pages
 *
 * @since   1.0
 * @refer   https://developer.wordpress.org/plugins/administration-menus/
 */
function ccfwp_add_menu_links() {
	// Main menu page.
	add_menu_page( __( 'Critical CSS For WP', 'critical-css-for-wp' ), __( 'Critical CSS For WP', 'critical-css-for-wp' ), 'manage_options', 'critical-css-for-wp', 'ccfwp_admin_interface_render', 'dashicons-performance', 100 );
}

add_action( 'admin_menu', 'ccfwp_add_menu_links' );

/**
 * Admin interface renderer
 *
 * @since 1.0
 */
function ccfwp_admin_interface_render() {
	// Authentication.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$tab = ccfwp_get_tab( 'generatecss', array( 'generatecss', 'advance', 'support' ) );
	?>
	<div class="ccfwp-container" id="ccfwp-wrap">

		<h1><?php echo esc_html__( 'Critical CSS For WP', 'critical-css-for-wp' ); ?></h1>
		<div class="ccfwp-comp-cont">
	<h2 class="nav-tab-wrapper">
		<?php
			echo '<a href="' . esc_url( ccfwp_admin_link( 'generatecss' ) ) . '" class="nav-tab ' . esc_attr( 'generatecss' == $tab ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Generate CSS' , 'critical-css-for-wp') . '</a>';

			echo '<a href="' . esc_url( ccfwp_admin_link( 'advance' ) ) . '" class="nav-tab ' . esc_attr( 'advance' == $tab ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Advance' , 'critical-css-for-wp') . '</a>';

			echo '<a href="' . esc_url( ccfwp_admin_link( 'support' ) ) . '" class="nav-tab ' . esc_attr( 'support' == $tab ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Support' , 'critical-css-for-wp') . '</a>';
		?>
		  </h2>
		  <form action="options.php" method="post" enctype="multipart/form-data" class="ccfwp-settings-form">
		  <?php
			// Output nonce, action, and option_page fields for a settings page.
				settings_fields( 'ccfwp_settings_group' );
			 echo "<div class='ccfwp-section-tab ccfwp-generatecss' " . ( $tab != 'generatecss' ? 'style="display:none;"' : '' ) . '>';
				   ccfwp_urlslist_callback();
			  echo '</div>';

			  echo "<div class='ccfwp-section-tab ccfwp-advance' " . ( $tab != 'advance' ? 'style="display:none;"' : '' ) . '>';
				   ccfwp_advance_settings_callback();
			  echo '</div>';

			  echo "<div class='ccfwp-section-tab ccfwp-support' " . ( $tab != 'support' ? 'style="display:none;"' : '' ) . '>';
				   ccfwp_support_settings_callback();
			  echo '</div>';
			?>
					
		  <?php submit_button( esc_html__( 'Save Settings' , 'critical-css-for-wp') ); ?>          
		  </form>
	</div>
	<?php
}



add_action( 'admin_enqueue_scripts', 'ccfwp_settings_page_css' );

function ccfwp_settings_page_css( $hook ) {
	global $current_screen;
	$pagenow = false;

	if ( isset( $current_screen->id ) && $current_screen->id == 'toplevel_page_critical-css-for-wp' ) {
		$pagenow = true;
	}

	if ( is_admin() && $pagenow == true ) {
		$min  = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_register_style( 'crtitcal-css-settings-style', untrailingslashit( CRITICAL_CSS_FOR_WP_PLUGIN_URI ) . "/admin/css/crtitcal-css-settings{$min}.css", false, CRITICAL_CSS_FOR_WP_VERSION );
		wp_enqueue_style( 'crtitcal-css-settings-style' );

	}
}

function ccfwp_admin_link( $tab = '' ) {

	$page = 'critical-css-for-wp';

	$link = admin_url( 'admin.php?page=' . $page );

	if ( $tab ) {
		$link .= '&tab=' . $tab;
	}

	return esc_url( $link );

}

function ccfwp_get_tab( $default = '', $available = array() ) {
	//phpcs:ignore -- Reason: $_GET['tab'] is used to show the tab only it is not saved or processed.
	$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash($_GET['tab'] )) : $default;

	if ( ! in_array( $tab, $available ) ) {
		$tab = $default;
	}

	return $tab;
}


function ccfwp_generate_time( $total_count = 0 ) {

	   $estimate_time = '';
	if ( $total_count > 0 ) {
		$hours = '';
		if ( intdiv( $total_count, 120 ) > 0 ) {
			   $hours = intdiv( $total_count, 120 ) . ' Hours, ';
		}

		if ( $hours ) {
			 $estimate_time = $hours . ( $total_count % 60 ) . ' Min';
		} else {

			if ( ( $total_count % 60 ) > 0 ) {
				$estimate_time = ( $total_count % 60 ) . ' Min';
			}
		}
	}
	   return $estimate_time;
}
	/**
	 * Url list will be shows
	 */
function ccfwp_urlslist_callback() {

	global $wpdb, $table_prefix;
	$table_name = $table_prefix . 'critical_css_for_wp_urls';
	$table_name_escaped = esc_sql( $table_name );
	$total_count  = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name_escaped}" ); //phpcs:ignore -- Reasone: $table_name_escaped is escaped
	$cached_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name_escaped} Where `status`=%s",  'cached' ) ); //phpcs:ignore -- Reasone: $table_name_escaped is escaped
	$inprogress   = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name_escaped} Where `status`=%s", 'inprocess' ) ); //phpcs:ignore -- Reasone: $table_name_escaped is escaped
	$failed_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name_escaped} Where `status`=%s",  'failed' ) ); //phpcs:ignore -- Reasone: $table_name_escaped is escaped
	$queue_count  = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name_escaped} Where `status`=%s", 'queue' ) ); //phpcs:ignore -- Reasone: $table_name_escaped is escaped
	$inprogress   = 0;
	$percentage   = 0;

	if ( $cached_count > 0 && $total_count ) {
		$percentage = ( $cached_count / $total_count ) * 100;
		$percentage = floor( $percentage );
	}

	?>
		<div class="ccfwp_urls_section">            
			<!-- process section -->
			<div class="ccfwp-css-optimization-wrapper">
			
				<strong style="font-size:18px;"><?php echo esc_html__( 'CSS Optimisation Status' ,'critical-css-for-wp'); ?></strong>
				<p><?php echo esc_html__( 'Optimisation is running in background. You can see latest result on page reload' ,'critical-css-for-wp'); ?></p>
				
				<div class="ccfwp_progress_bar">
					<div class="ccfwp_progress_bar_body" style="width: <?php echo esc_attr( $percentage ); ?>%;"><?php echo esc_attr( $percentage ); ?>%</div>
				</div>
				
				<div class="ccfwp_cached_status_bar">
				<div style="margin-top:20px;"><strong><?php echo esc_html__( 'Total :' ,'critical-css-for-wp'); ?></strong> 
																 <?php
																	echo esc_attr( $total_count ) . ' URLs';
																	?>
				 </div>
				 <div><strong><?php echo esc_html__( 'In Progress :' ,'critical-css-for-wp'); ?></strong> 
										 <?php
											echo esc_attr( $queue_count ) . ' URLs';
											?>
				 </div>
				<div><strong><?php echo esc_html__( 'Critical CSS Optimized  :' ,'critical-css-for-wp'); ?></strong> 
										<?php
										echo esc_attr( $cached_count ) . ' URLs';
										?>
				</div>
				<?php
				if ( ccfwp_generate_time( $queue_count ) ) {
					?>
						<div>
						<strong><?php echo esc_html__( 'Remaining Time :'  ,'critical-css-for-wp'); ?></strong>
						<?php
						echo esc_attr(ccfwp_generate_time( $queue_count ));
						?>
						</div>                        
						<?php
				}

				if ( $failed_count > 0 ) {
					?>
						   
							<div>
								<strong><?php echo esc_html__( 'Failed      :' ,'critical-css-for-wp');?></strong> <?php echo esc_attr( $failed_count ); ?>
								<a href="#" class="ccfwp-resend-urls button button-secondary"><?php echo esc_html__( 'Resend' ,'critical-css-for-wp'); ?></a>
							</div>                                                        
						<?php
				}
				?>
																
				</div>                                                                
			</div> 
			<!-- DataTable section -->
			<div class="ccfwp-table-url-wrapper">                         
			 <div id="cwvpb-global-tabs" style="margin-top: 10px;">
				<a data-id="cwvpb-general-container"><?php echo esc_html__( 'All' ,'critical-css-for-wp'); ?> (<?php echo esc_html( $total_count ); ?>)</a> |
				<a data-id="cwvpb-queue-container"><?php echo esc_html__( 'In Queue' ,'critical-css-for-wp');?> (<?php echo esc_html( $queue_count ); ?>)</a> |
				<a data-id="cwvpb-knowledge-container"><?php echo esc_html__( 'Completed' ,'critical-css-for-wp'); ?> (<?php echo esc_html( $cached_count ); ?>)</a> |
				<a data-id="cwvpb-default-container" ><?php echo esc_html__( 'Failed' ,'critical-css-for-wp'); ?> (<?php echo esc_html( $failed_count ); ?>)</a>
			 </div>
														
				<div class="cwvpb-global-container" id="cwvpb-general-container">
				<table class="table ccfwp-table-class" id="table_page_cc_style_all" style="width:100%">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'URL' ,'critical-css-for-wp'); ?></th>
						<th><?php echo esc_html__( 'Status' ,'critical-css-for-wp'); ?></th>
						<th><?php echo esc_html__( 'Size' ,'critical-css-for-wp'); ?></th>
						<th><?php echo esc_html__( 'Created Date' ,'critical-css-for-wp'); ?></th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th><?php echo esc_html__( 'URL' ,'critical-css-for-wp'); ?></th>
						<th><?php echo esc_html__( 'Status' ,'critical-css-for-wp'); ?></th>
						<th><?php echo esc_html__( 'Size' ,'critical-css-for-wp'); ?></th>
						<th><?php echo esc_html__( 'Created Date' ,'critical-css-for-wp'); ?></th>
					</tr>
				</tfoot>
				</table>
				</div>

				<div class="cwvpb-global-container" id="cwvpb-queue-container">
				<table class="table ccfwp-table-class" id="table_page_cc_style_queue" style="width:100%">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'URL' ,'critical-css-for-wp'); ?></th>
						<th><?php echo esc_html__( 'Status' ,'critical-css-for-wp');?></th>
						<th><?php echo esc_html__( 'Size' ,'critical-css-for-wp'); ?></th>
						<th><?php echo esc_html__( 'Created Date' ,'critical-css-for-wp'); ?></th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th><?php echo esc_html__( 'URL' ,'critical-css-for-wp'); ?></th>
						<th><?php echo esc_html__( 'Status' ,'critical-css-for-wp'); ?></th>
						<th><?php echo esc_html__( 'Size' ,'critical-css-for-wp'); ?></th>
						<th><?php echo esc_html__( 'Created Date' ,'critical-css-for-wp'); ?></th>
					</tr>
				</tfoot>
				</table>
				</div>

				<div class="cwvpb-global-container" id="cwvpb-knowledge-container">
				<table class="table ccfwp-table-class" id="table_page_cc_style_completed" style="width:100%">
			<thead>
					<tr>
						<th><?php echo esc_html__( 'URL' ,'critical-css-for-wp'); ?></th>
						<th><?php echo esc_html__( 'Status' ,'critical-css-for-wp'); ?></th>
						<th><?php echo esc_html__( 'Size' ,'critical-css-for-wp'); ?></th>
						<th><?php echo esc_html__( 'Created Date','critical-css-for-wp'); ?></th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th><?php echo esc_html__( 'URL' ,'critical-css-for-wp'); ?></th>
						<th><?php echo esc_html__( 'Status','critical-css-for-wp'); ?></th>
						<th><?php echo esc_html__( 'Size' ,'critical-css-for-wp'); ?></th>
						<th><?php echo esc_html__( 'Created Date' ,'critical-css-for-wp'); ?></th>
					</tr>
				</tfoot>
				</table>
				</div>

				<div class="cwvpb-global-container" id="cwvpb-default-container">
				<table class="table ccfwp-table-class" id="table_page_cc_style_failed" style="width:100%">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'URL' ,'critical-css-for-wp'); ?></th>
						<th><?php echo esc_html__( 'Status' ,'critical-css-for-wp'); ?></th>
						<th><?php echo esc_html__( 'Failed Date' ,'critical-css-for-wp'); ?></th>
						<th><?php echo esc_html__( 'Error' ,'critical-css-for-wp'); ?></th>
						
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th><?php echo esc_html__( 'URL' ,'critical-css-for-wp'); ?></th>
						<th><?php echo esc_html__( 'Status' ,'critical-css-for-wp'); ?></th>
						<th><?php echo esc_html__( 'Failed Date' ,'critical-css-for-wp'); ?></th>
						<th><?php echo esc_html__( 'Error' ,'critical-css-for-wp'); ?></th>                        
					</tr>
				</tfoot>
				</table>
				</div>
			
			</div>

			 <div class="ccfwp-advance-urls-container">
				<span class="ccfwp-advance-toggle"><?php echo esc_html__( 'Advance Settings','critical-css-for-wp'); ?> <span class="dashicons dashicons-admin-generic"></span></span>
				<div class="ccfwp-advance-btn-div cwvpb-display-none">
					<a class="button button-primary ccfwp-recheck-url-cache"><?php echo esc_html__( 'Recheck' ,'critical-css-for-wp'); ?></a>
					<a class="button button-primary ccfwp-reset-url-cache"><?php echo esc_html__( 'Reset Cache' ,'critical-css-for-wp'); ?></a>
				</div>
			 </div>       
			
		</div>
								
		<?php

}

function ccfwp_support_settings_callback() {
	?>
		<div class="ccfwp_support_div">
			<strong><?php echo esc_html__( 'If you have any query, please write the query in below box or email us at' ,'critical-css-for-wp'); ?> <a href="mailto:team@magazine3.in">team@magazine3.in</a>. <?php echo esc_html__( 'We will reply to your email address shortly' ,'critical-css-for-wp'); ?></strong>
		
			<ul>
				<li>
					<input type="text" id="ccfwp_query_email" name="ccfwp_query_email" placeholder="email">
				</li>
				<li>                    
					<div><textarea rows="5" cols="60" id="ccfwp_query_message" name="ccfwp_query_message" placeholder="Write your query"></textarea></div>
					<span class="ccfwp-query-success ccfwp_hide"><strong><?php echo esc_html__( 'Message sent successfully, Please wait we will get back to you shortly' ,'critical-css-for-wp'); ?></strong></span>
					<span class="ccfwp-query-error ccfwp_hide"><strong><?php echo esc_html__( 'Message not sent. please check your network connection' ,'critical-css-for-wp'); ?></strong></span>
				</li>
				<li>
					<strong><?php echo esc_html__( 'Are you a premium customer ?' ,'critical-css-for-wp'); ?></strong>  
					<select id="ccfwp_query_premium_cus" name="ccfwp_query_premium_cus">                       
						<option value=""><?php echo esc_html__( 'Select' ,'critical-css-for-wp'); ?></option>
						<option value="yes"><?php echo esc_html__( 'Yes' ,'critical-css-for-wp'); ?></option>
						<option value="no"><?php echo esc_html__( 'No' ,'critical-css-for-wp'); ?></option>
					</select>                      
				</li>
				<li><button class="button ccfwp-send-query"><?php echo esc_html__( 'Send Message' ,'critical-css-for-wp'); ?></button></li>
			</ul>            
					
		</div>
	<?php
}
function ccfwp_advance_settings_callback() {

	$settings = ccfwp_defaults();

	$taxonomies = get_taxonomies( array( 'public' => true ), 'names' );

	$post_types = array();
	$post_types = get_post_types( array( 'public' => true ), 'names' );
	$unsetdpost = array(
		'attachment',
		'saswp',
		'saswp_reviews',
		'saswp-collections',
	);
	foreach ( $unsetdpost as $value ) {
		unset( $post_types[ $value ] );
	}
	echo '<div class="ccfwp-section-container">';
	if ( $post_types ) {
			echo '<div class="ccfwp-section-content">';
			echo '<h2> ' . esc_html__( 'Generate Critical Css For','critical-css-for-wp') . '</h2>';
			echo '<ul>';
			echo '<li>';
			echo '<label for="ccfwp_settings[ccfwp_on_home]"><input class="" type="checkbox" id="ccfwp_settings[ccfwp_on_home]" name="ccfwp_settings[ccfwp_on_home]" value="1" ' . ( isset( $settings['ccfwp_on_home'] ) ? 'checked' : '' ) . ' /> ' . '<label for="ccfwp_settings[ccfwp_on_home]">' . esc_html__( 'Home' ,'critical-css-for-wp'). '</label>';
			echo '</li>';

		foreach ( $post_types as $key => $value ) {
			echo '<li>';
			echo '<input class="" type="checkbox" id="ccfwp_settings[ccfwp_on_cp_type][' . esc_attr( $key ) . ']" name="ccfwp_settings[ccfwp_on_cp_type][' . esc_attr( $key ) . ']" value="1" ' . ( isset( $settings['ccfwp_on_cp_type'][ $key ] ) ? 'checked' : '' ) . ' /> ' .  '<label for="ccfwp_settings[ccfwp_on_cp_type][' . esc_attr( $key ) . ']"> '. esc_html( ucwords($value) ) .'</label>' ;
			echo '</li>';
		}

		if ( $taxonomies ) {
			foreach ( $taxonomies as $key => $value ) {
				echo '<li>';
				echo '<input class="" type="checkbox" id="ccfwp_settings[ccfwp_on_tax_type][' . esc_attr( $key ) . ']" name="ccfwp_settings[ccfwp_on_tax_type][' . esc_attr( $key ) . ']" value="1" ' . ( isset( $settings['ccfwp_on_tax_type'][ $key ] ) ? 'checked' : '' ) . ' /> ' .'<label for="ccfwp_settings[ccfwp_on_tax_type][' . esc_attr( $key ) . ']">'. esc_html( ucwords( $value ) ) .'</label>';
				echo '</li>';
			}
		}

		echo '</ul>';
		echo '</div>';
	}
	echo '<div class="ccfwp-section-content">';

	echo '<div class="ccfwp-heading-title">' . esc_html__( 'Pages to scan' ,'critical-css-for-wp');
	echo '<div class="ccfwp-tooltip-box"><span class="dashicons dashicons-info"></span>
    <span class="ccfwp-tooltip-text">' . esc_html__( 'By default plugin will scan 30 urls and add that to processing queue. You can increase this value to quickly add pages to queue.In case your website seems slow you can decrease this value.We recommed not to increase this value above 1000.' ,'critical-css-for-wp') . '</span>
  </div></div>';
	
	$ccfwp_scan_urls = ( isset( $settings['ccfwp_scan_urls'] ) ) ? $settings['ccfwp_scan_urls'] : 30;
	echo '<input type="number" class="ccfwp-advance-width" value="' . esc_attr( $ccfwp_scan_urls ) . '" name="ccfwp_settings[ccfwp_scan_urls]">';

	echo '<div class="ccfwp-heading-title">' . esc_html__( 'Pages to generate critical css' ,'critical-css-for-wp');
	echo '<div class="ccfwp-tooltip-box"><span class="dashicons dashicons-info"></span>
    <span class="ccfwp-tooltip-text">' . esc_html__( 'By default plugin will generate critical css for 4 urls in every 30 seconds. You can increase this value to  quickly generate critical css.In case your website seems slow you can decrease this value.We recommed not to increase this value above 12.' ,'critical-css-for-wp') . '</span>
  </div></div>';
	
	$ccfwp_generate_urls = ( isset( $settings['ccfwp_generate_urls'] ) ) ? $settings['ccfwp_generate_urls'] : 4;
	echo '<input type="number" class="ccfwp-advance-width"  value="' . esc_attr( $ccfwp_generate_urls ) . '" name="ccfwp_settings[ccfwp_generate_urls]">';

	echo '<div class="ccfwp-heading-title">' . esc_html__( 'CSS Defer','critical-css-for-wp');
	echo '<div class="ccfwp-tooltip-box"><span class="dashicons dashicons-info"></span>
    <span class="ccfwp-tooltip-text">' . esc_html__( 'By default plugin our plugin will add critical css and defer css loading. You can disable the deferring  of css if you have any issue.' ,'critical-css-for-wp') . '</span>
  </div></div>';
	
	echo '<select class="ccfwp-advance-width"  name="ccfwp_settings[ccfwp_defer_css]">';
	$ccwp_defer_on  = ( isset( $settings['ccfwp_defer_css'] ) && $settings['ccfwp_defer_css'] == 'on' ) ? 'selected' : '';
	$ccwp_defer_off = ( isset( $settings['ccfwp_defer_css'] ) && $settings['ccfwp_defer_css'] == 'off' ) ? 'selected' : '';
	echo '<option value="on" ' . esc_attr( $ccwp_defer_on ) . '>' . esc_html__( 'Enable' ,'critical-css-for-wp') . ' </option>';
	echo '<option value="off" ' . esc_attr( $ccwp_defer_off ) . '>' . esc_html__( 'Disable' ,'critical-css-for-wp') . '</option></select>';

	echo '<div class="ccfwp-heading-title">' . esc_html__( 'Generate CSS on Plugin Page reload','critical-css-for-wp');
	echo '<div class="ccfwp-tooltip-box"><span class="dashicons dashicons-info"></span>
    <span class="ccfwp-tooltip-text">' . esc_html__( 'This option will only work when WP cron is disabled. Critical CSS will be generated plugin page is visited. There will be a gap of 1 minutes between two consecutive requests' ,'critical-css-for-wp') . '</span>
  </div></div>';
	
	echo '<select class="ccfwp-advance-width"  name="ccfwp_settings[ccfwp_generate_css]">';
	$ccwp_generate_on  = ( isset( $settings['ccfwp_generate_css'] ) && $settings['ccfwp_generate_css'] == 'on' ) ? 'selected' : '';
	$ccwp_generate_off = ( isset( $settings['ccfwp_generate_css'] ) && $settings['ccfwp_generate_css'] == 'off' ) ? 'selected' : '';
	if(!$ccwp_generate_on){
		$ccwp_generate_off = 'selected';
	}
	echo '<option value="on" ' . esc_attr( $ccwp_generate_on ) . '>' . esc_html__( 'Enable' ,'critical-css-for-wp') . ' </option>';
	echo '<option value="off" ' . esc_attr( $ccwp_generate_off ) . '>' . esc_html__( 'Disable' ,'critical-css-for-wp') . '</option></select>';

	echo '<div class="ccfwp-heading-title">' . esc_html__( 'CSS Defer Delay' ,'critical-css-for-wp');
	echo '<div class="ccfwp-tooltip-box"><span class="dashicons dashicons-info"></span>
    <span class="ccfwp-tooltip-text">' . esc_html__( 'Amount of time all css is deferred to load. You can add any value for delay which seems good for your website.This value is in milliseconds(ms). [1000ms = 1sec] ','critical-css-for-wp') . '</span>
  </div></div>';
	echo '<input type="number" class="ccfwp-advance-width" value="' . esc_attr( intval( $settings['ccfwp_defer_time'] ) ) . '" name="ccfwp_settings[ccfwp_defer_time]"> ms';

	echo '<div class="ccfwp-heading-title">' . esc_html__( 'Cache Alt path' ,'critical-css-for-wp');
	echo '<div class="ccfwp-tooltip-box"><span class="dashicons dashicons-info"></span>
    <span class="ccfwp-tooltip-text">' . esc_html__( 'Check this options if you critical css is getting overwritten or deleted ' ,'critical-css-for-wp'). '</span>
  </div></div>';
	$alt_check = ( isset( $settings['ccfwp_alt_cachepath'] ) && $settings['ccfwp_alt_cachepath'] == 1 ) ? 'checked' : '';
	echo '<p><input type="checkbox" value="1" name="ccfwp_settings[ccfwp_alt_cachepath]" ' . esc_attr($alt_check) . '> Alternative cache path</p>';

	echo '</div>';
	?>
	 
	<?php
	echo '</div>';

}

function ccfwp_options( $get = null )
{
	$options = array(
		'ccfwp_on_home'=>array( 'value' => 1, 'type' => 'number'),
		'ccfwp_on_cp_type'=>array( 'value' => array('post' => 1), 'type' => 'array'),
		'ccfwp_defer_css'=>array( 'value' => 'on', 'type' => 'string'),
		'ccfwp_scan_urls'=>array( 'value' => 30, 'type' => 'number'),
		'ccfwp_generate_urls'=>array( 'value' => 4, 'type' => 'number'),
		'ccfwp_defer_time'=>array( 'value' => 300, 'type' => 'number'),
		'ccfwp_alt_cachepath'=>array( 'value' => 0, 'type' => 'number'),
		'ccfwp_generate_css'=>array( 'value' => 'off', 'type' => 'string'),
	);
	
	if($get){
		$return_options = array();
		$get_val =  ('type' == $get) ? 'type' : 'value';

		foreach ( $options as $key => $option ) {
			$return_options[$key] = $option[$get_val];
		}
		return $return_options;
	}
	return $options;
}

function ccfwp_defaults() {
	$defaults = ccfwp_options('value');
	$settings = get_option( 'ccfwp_settings', $defaults );
	return $settings;
}


/*
  WP Settings API
*/
add_action( 'admin_init', 'ccfwp_settings_init' );

function ccfwp_settings_init() {

	register_setting( 'ccfwp_settings_group', 'ccfwp_settings','ccfwp_settings_validate' );

}

function ccfwp_settings_validate( $input = array() ) {
	$default_options = ccfwp_options();
	foreach ( $input as  $key => $value ) {

		if ( isset( $default_options[ $key ] ) ) {
			$type = $default_options[ $key ]['type'];
			if($type == 'array'){
				$input[ sanitize_key($key) ] = array_map( 'sanitize_text_field', wp_unslash($input[ $key ]));
			} else if($type == 'number'){
				$input[ sanitize_key($key) ] = absint( $input[ $key ] );
			}else{
				$input[ sanitize_key($key) ] = sanitize_text_field( $input[ $key ] );
			}
		} else{
			$input[ sanitize_key($key) ] = sanitize_text_field( $input[ $key ] );
		}
	}

    return $input;
}

add_action( 'wp_ajax_ccfwp_send_query_message', 'ccfwp_send_query_message' );

function ccfwp_send_query_message() {

	if ( ! isset( $_POST['ccfwp_security_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( $_POST['ccfwp_security_nonce'] , 'ccfwp_ajax_check_nonce' ) ) {
		return;
	}
	$customer_type = 'Are you a premium customer ? No';
	$message       = isset( $_POST['message'] ) ? ccfwp_sanitize_textarea_field( wp_unslash($_POST['message']) ) : '';
	$email         = sanitize_email( $_POST['email'] );
	$premium_cus   = isset( $_POST['premium_cus'] ) ? ccfwp_sanitize_textarea_field( wp_unslash( $_POST['premium_cus']) ) : '';

	if ( function_exists( 'wp_get_current_user' ) ) {

		$user = wp_get_current_user();

		if ( $premium_cus == 'yes' ) {
			$customer_type = 'Are you a premium customer ? Yes';
		}

		$message = '<p>' . $message . '</p><br><br>'
			 . $customer_type
			 . '<br><br>Query from plugin support tab';

		$user_data  = $user->data;
		$user_email = $user_data->user_email;

		if ( $email ) {
			$user_email = $email;
		}
		// php mailer variables.
		$sendto  = 'team@magazine3.in';
		$subject = 'Critical Css For WP Customer Query';

		$headers[] = 'Content-Type: text/html; charset=UTF-8';
		$headers[] = 'From: ' . esc_attr( $user_email );
		$headers[] = 'Reply-To: ' . esc_attr( $user_email );
		// Load WP components, no themes.
		$sent = wp_mail( $sendto, $subject, $message, $headers );

		if ( $sent ) {

			 echo wp_json_encode( array( 'status' => 't' ) );

		} else {

			echo wp_json_encode( array( 'status' => 'f' ) );

		}
	}

	wp_die();
}