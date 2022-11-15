<?php
/**
 * Admin setup for the plugin
 *
 * @since 1.0
 * @function	superpwa_add_menu_links()			Add admin menu pages
 *
*/
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit; 
 
/**
 * Add admin menu pages
 *
 * @since   1.0
 * @refer   https://developer.wordpress.org/plugins/administration-menus/
 */
function critical_css_add_menu_links() {
    // Main menu page
    add_menu_page( __( 'Critical CSS For WP', 'critical-css' ), __( 'Critical CSS For WP', 'critical-css' ), 'manage_options', 'critical-css-for-wp','ccfwp_admin_interface_render', 'dashicons-performance', 100 );
}

add_action( 'admin_menu', 'critical_css_add_menu_links' );

/**
 * Admin interface renderer
 *
 * @since 1.0
 */

function ccfwp_admin_interface_render(){
      // Authentication
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    $tab = critical_css_get_tab('generatecss', array('generatecss','advance','support'));
    ?>
    <div class="ccfwp-container" id="ccfwp-wrap">

        <h1><?php echo ccfwp_t_string('Critical CSS For WP'); ?></h1>
        <div class="ccfwp-comp-cont">
      <h2 class="nav-tab-wrapper">
          <?php
          echo '<a href="' . esc_url(critical_css_admin_link('generatecss')) . '" class="nav-tab ' . esc_attr( $tab == 'generatecss' ? 'nav-tab-active' : '') . '">' . ccfwp_t_string('Generate CSS') . '</a>';

          echo '<a href="' . esc_url(critical_css_admin_link('advance')) . '" class="nav-tab ' . esc_attr( $tab == 'advance' ? 'nav-tab-active' : '') . '">' . ccfwp_t_string('Advance') . '</a>';

          echo '<a href="' . esc_url(critical_css_admin_link('support')) . '" class="nav-tab ' . esc_attr( $tab == 'support' ? 'nav-tab-active' : '') . '">' . ccfwp_t_string('Support') . '</a>';
           ?>
          </h2>
          <form action="options.php" method="post" enctype="multipart/form-data" class="ccfwp-settings-form">
          <?php
          // Output nonce, action, and option_page fields for a settings page.
                settings_fields( 'critical_css_settings_group' );  
             echo "<div class='ccfwp-section-tab ccfwp-generatecss' ".( $tab != 'generatecss' ? 'style="display:none;"' : '').">";
                   critical_css_urlslist_callback();
              echo "</div>";

              echo "<div class='ccfwp-section-tab ccfwp-advance' ".( $tab != 'advance' ? 'style="display:none;"' : '').">"; 
                   critical_css_advance_settings_callback();
              echo "</div>";

              echo "<div class='ccfwp-section-tab ccfwp-support' ".( $tab != 'support' ? 'style="display:none;"' : '').">"; 
                   critical_css_support_settings_callback();
              echo "</div>";
          ?>          
          <?php  submit_button( ccfwp_t_string('Save Settings') );?>          
          </form>
    </div>
<?php }



add_action( 'admin_enqueue_scripts', 'critical_css_settings_page_css' );

function critical_css_settings_page_css( $hook ) {
    global $current_screen;
    $pagenow = false; 

    if(isset($current_screen->id) && $current_screen->id == 'toplevel_page_critical-css-for-wp'){
        $pagenow = true;
    }
    
    if( is_admin() && $pagenow ==true ) {

        wp_register_style( 'crtitcal-css-settings-style', untrailingslashit(CRITICAL_CSS_FOR_WP_PLUGIN_URI) . '/admin/crtitcal-css-settings.css',false,CRITICAL_CSS_FOR_WP_VERSION );
        wp_enqueue_style( 'crtitcal-css-settings-style' );

    }
}

function critical_css_admin_link($tab = ''){
    
    $page = 'critical-css-for-wp';

    $link = admin_url( 'admin.php?page=' . $page );

    if ( $tab ) {
        $link .= '&tab=' . $tab;
    }

    return esc_url($link);

}

function critical_css_get_tab( $default = '', $available = array() ) {

  $tab = isset( $_GET['tab'] ) ? sanitize_text_field($_GET['tab']) : $default;
        
  if ( ! in_array( $tab, $available ) ) {
    $tab = $default;
  }

  return $tab;
}


 function critical_css_generate_time($total_count){
        
        $estimate_time = '';      
        if($total_count > 0){
            $hours = '';
            if(intdiv($total_count, 120) > 0){
                $hours = intdiv($total_count, 120).' Hours, ';
            }
            
            if($hours){
                $estimate_time = $hours. ($total_count % 60). ' Min';
            }else{
                
                if(($total_count % 60) > 0){
                    $estimate_time = ($total_count % 60). ' Min';
                }                
            }            
            
        }
        return $estimate_time;  
    }
    /**
     * Url list will be shows
     */ 
    function critical_css_urlslist_callback(){

        global $wpdb, $table_prefix;
        $table_name = $table_prefix . 'critical_css_for_wp_urls';        

        $total_count        = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $cached_count       = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name Where `status`=%s", 'cached'));                
        $inprogress         = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name Where `status`=%s", 'inprocess'));                
        $failed_count       = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name Where `status`=%s", 'failed'));                        
        $queue_count        = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name Where `status`=%s", 'queue'));                
        $inprogress         = 0;
        $percentage         = 0;
                
        if($cached_count > 0 && $total_count){            
            $percentage      = ($cached_count/$total_count) * 100;        
            $percentage      = floor($percentage);
        }        
                        
        ?>
        <div class="ccfwp_urls_section">            
            <!-- process section -->
            <div class="ccfwp-css-optimization-wrapper">
            
                <strong style="font-size:18px;"><?php echo ccfwp_t_string('CSS Optimisation Status') ?></strong>
                <p><?php echo ccfwp_t_string('Optimisation is running in background. You can see latest result on page reload') ?></p>
                
                <div class="ccfwp_progress_bar">
                    <div class="ccfwp_progress_bar_body" style="width: <?php echo esc_attr($percentage); ?>%;"><?php echo esc_attr($percentage); ?>%</div>
                </div>
                
                <div class="ccfwp_cached_status_bar">
                <div style="margin-top:20px;"><strong><?php echo ccfwp_t_string('Total :') ?></strong> <?php echo esc_attr($total_count). ' URLs';                                         
                 ?></div>
                 <div><strong><?php echo ccfwp_t_string('In Progress :') ?></strong> <?php echo esc_attr($queue_count). ' URLs';                                         
                 ?></div>
                <div><strong><?php echo ccfwp_t_string('Critical CSS Optimized  :') ?></strong> <?php echo esc_attr($cached_count). ' URLs';                 
                ?></div>
                <?php
                    if(critical_css_generate_time($queue_count)){
                        ?>
                        <div>
                        <strong><?php echo ccfwp_t_string('Remaining Time :') ?></strong>
                        <?php
                            echo critical_css_generate_time($queue_count);
                        ?>
                        </div>                        
                        <?php
                    }

                    if($failed_count > 0){
                        ?>   
                            <div>
                                <strong><?php echo ccfwp_t_string('Failed      :') ?></strong> <?php echo esc_attr($failed_count);?>
                                <a href="#" class="ccfwp-resend-urls button button-secondary"><?php echo ccfwp_t_string('Resend'); ?></a>
                            </div>                                                        
                        <?php     
                    }
                ?>                                                
                </div>                                                                
            </div> 
            <!-- DataTable section -->
            <div class="ccfwp-table-url-wrapper">                         
             <div id="cwvpb-global-tabs" style="margin-top: 10px;">
                <a data-id="cwvpb-general-container"><?php echo ccfwp_t_string('All'); ?> (<?php echo esc_html($total_count); ?>)</a> |
                <a data-id="cwvpb-queue-container"><?php echo ccfwp_t_string('In Queue'); ?> (<?php echo esc_html($queue_count); ?>)</a> |
                <a data-id="cwvpb-knowledge-container"><?php echo ccfwp_t_string('Completed'); ?> (<?php echo esc_html($cached_count); ?>)</a> |
                <a data-id="cwvpb-default-container" ><?php echo ccfwp_t_string('Failed'); ?> (<?php echo esc_html($failed_count); ?>)</a>
             </div>
                                                        
                <div class="cwvpb-global-container" id="cwvpb-general-container">
                <table class="table ccfwp-table-class" id="table_page_cc_style_all" style="width:100%">
                <thead>
                    <tr>
                        <th><?php echo ccfwp_t_string('URL'); ?></th>
                        <th><?php echo ccfwp_t_string('Status'); ?></th>
                        <th><?php echo ccfwp_t_string('Size'); ?></th>
                        <th><?php echo ccfwp_t_string('Created Date'); ?></th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th><?php echo ccfwp_t_string('URL'); ?></th>
                        <th><?php echo ccfwp_t_string('Status'); ?></th>
                        <th><?php echo ccfwp_t_string('Size'); ?></th>
                        <th><?php echo ccfwp_t_string('Created Date'); ?></th>
                    </tr>
                </tfoot>
                </table>
                </div>

                <div class="cwvpb-global-container" id="cwvpb-queue-container">
                <table class="table ccfwp-table-class" id="table_page_cc_style_queue" style="width:100%">
                <thead>
                    <tr>
                        <th><?php echo ccfwp_t_string('URL'); ?></th>
                        <th><?php echo ccfwp_t_string('Status'); ?></th>
                        <th><?php echo ccfwp_t_string('Size'); ?></th>
                        <th><?php echo ccfwp_t_string('Created Date'); ?></th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th><?php echo ccfwp_t_string('URL'); ?></th>
                        <th><?php echo ccfwp_t_string('Status'); ?></th>
                        <th><?php echo ccfwp_t_string('Size'); ?></th>
                        <th><?php echo ccfwp_t_string('Created Date'); ?></th>
                    </tr>
                </tfoot>
                </table>
                </div>

                <div class="cwvpb-global-container" id="cwvpb-knowledge-container">
                <table class="table ccfwp-table-class" id="table_page_cc_style_completed" style="width:100%">
            <thead>
                    <tr>
                        <th><?php echo ccfwp_t_string('URL'); ?></th>
                        <th><?php echo ccfwp_t_string('Status'); ?></th>
                        <th><?php echo ccfwp_t_string('Size'); ?></th>
                        <th><?php echo ccfwp_t_string('Created Date'); ?></th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th><?php echo ccfwp_t_string('URL'); ?></th>
                        <th><?php echo ccfwp_t_string('Status'); ?></th>
                        <th><?php echo ccfwp_t_string('Size'); ?></th>
                        <th><?php echo ccfwp_t_string('Created Date'); ?></th>
                    </tr>
                </tfoot>
                </table>
                </div>

                <div class="cwvpb-global-container" id="cwvpb-default-container">
                <table class="table ccfwp-table-class" id="table_page_cc_style_failed" style="width:100%">
                <thead>
                    <tr>
                        <th><?php echo ccfwp_t_string('URL'); ?></th>
                        <th><?php echo ccfwp_t_string('Status'); ?></th>
                        <th><?php echo ccfwp_t_string('Failed Date'); ?></th>
                        <th><?php echo ccfwp_t_string('Error'); ?></th>
                        
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th><?php echo ccfwp_t_string('URL'); ?></th>
                        <th><?php echo ccfwp_t_string('Status'); ?></th>
                        <th><?php echo ccfwp_t_string('Failed Date'); ?></th>
                        <th><?php echo ccfwp_t_string('Error'); ?></th>                        
                    </tr>
                </tfoot>
                </table>
                </div>
            
            </div>

             <div class="ccfwp-advance-urls-container">
                <span class="ccfwp-advance-toggle"><?php echo ccfwp_t_string('Advance Settings'); ?> <span class="dashicons dashicons-admin-generic"></span></span>
                <div class="ccfwp-advance-btn-div cwvpb-display-none">
                    <a class="button button-primary ccfwp-recheck-url-cache"><?php echo ccfwp_t_string('Recheck'); ?></a>
                    <a class="button button-primary ccfwp-reset-url-cache"><?php echo ccfwp_t_string('Reset Cache'); ?></a>
                </div>
             </div>       
            
        </div>
                                
        <?php

    }

 function critical_css_support_settings_callback(){
        ?>
        <div class="ccfwp_support_div">
            <strong><?php echo ccfwp_t_string('If you have any query, please write the query in below box or email us at') ?> <a href="mailto:team@magazine3.in">team@magazine3.in</a>. <?php echo ccfwp_t_string('We will reply to your email address shortly') ?></strong>
        
            <ul>
                <li>
                    <input type="text" id="ccfwp_query_email" name="ccfwp_query_email" placeholder="email">
                </li>
                <li>                    
                    <div><textarea rows="5" cols="60" id="ccfwp_query_message" name="ccfwp_query_message" placeholder="Write your query"></textarea></div>
                    <span class="ccfwp-query-success ccfwp_hide"><?php echo ccfwp_t_string('Message sent successfully, Please wait we will get back to you shortly'); ?></span>
                    <span class="ccfwp-query-error ccfwp_hide"><?php echo ccfwp_t_string('Message not sent. please check your network connection'); ?></span>
                </li>
                <li>
                    <strong><?php echo ccfwp_t_string('Are you a premium customer ?'); ?></strong>  
                    <select id="ccfwp_query_premium_cus" name="ccfwp_query_premium_cus">                       
                        <option value=""><?php echo ccfwp_t_string('Select'); ?></option>
                        <option value="yes"><?php echo ccfwp_t_string('Yes'); ?></option>
                        <option value="no"><?php echo ccfwp_t_string('No'); ?></option>
                    </select>                      
                </li>
                <li><button class="button ccfwp-send-query"><?php echo ccfwp_t_string('Send Message'); ?></button></li>
            </ul>            
                    
        </div>
    <?php
 }   
 function critical_css_advance_settings_callback(){
    
    $settings = critical_css_defaults();     
    
    $taxonomies = get_taxonomies(array( 'public' => true ), 'names');    

    $post_types = array();
    $post_types = get_post_types( array( 'public' => true ), 'names' );    
    $unsetdpost = array(
        'attachment',
        'saswp',
        'saswp_reviews',
        'saswp-collections',
    );
    foreach ($unsetdpost as $value) {
        unset($post_types[$value]);
    }
    
    if($post_types){        

            echo '<h2> '.ccfwp_t_string('Generate Critical Css For').'</h2>';
            echo '<ul>';
            echo '<li>';
            echo '<input class="" type="checkbox" name="ccfwp_settings[ccfwp_on_home]" value="1" '.(isset($settings["ccfwp_on_home"]) ? "checked": "").' /> ' . esc_html('Home');
            echo '</li>';

            foreach ($post_types as $key => $value) {
                echo '<li>';
                echo '<input class="" type="checkbox" name="ccfwp_settings[ccfwp_on_cp_type]['.esc_attr($key).']" value="1" '.(isset($settings["ccfwp_on_cp_type"][$key]) ? "checked": "").' /> ' . ucwords(esc_html($value));
                echo '</li>';
            }            

            if($taxonomies){
                foreach ($taxonomies as $key => $value) {
                    echo '<li>';
                    echo '<input class="" type="checkbox" name="ccfwp_settings[ccfwp_on_tax_type]['.esc_attr($key).']" value="1" '.(isset($settings["ccfwp_on_tax_type"][$key]) ? "checked": "").' /> ' . ucwords(esc_html($value));
                    echo '</li>';
                }
            }

        echo '</ul>';
    }
    
    ?> 

    <?php

}


function critical_css_defaults(){
    $defaults = array(
       'ccfwp_on_home'      => 1,
       'ccfwp_on_cp_type'   => array( 'post' => 1 )
    );        
    $settings = get_option( 'ccfwp_settings', $defaults );
    return $settings;
}    


/*
  WP Settings API
*/
add_action('admin_init', 'ccfwp_settings_init');

function ccfwp_settings_init(){

  register_setting( 'critical_css_settings_group', 'ccfwp_settings' );

}

add_action('wp_ajax_ccfwp_send_query_message', 'ccfwp_send_query_message');

function ccfwp_send_query_message(){   
    
    if ( ! isset( $_POST['ccfwp_security_nonce'] ) ){
       return; 
    }
    if ( !wp_verify_nonce( $_POST['ccfwp_security_nonce'], 'ccfwp_ajax_check_nonce' ) ){
       return;  
    }   
    $customer_type  = 'Are you a premium customer ? No';
    $message        = ccfwp_sanitize_textarea_field($_POST['message']); 
    $email          = sanitize_email($_POST['email']); 
    $premium_cus    = ccfwp_sanitize_textarea_field($_POST['premium_cus']);   
                            
    if(function_exists('wp_get_current_user')){

        $user           = wp_get_current_user();

        if($premium_cus == 'yes'){
          $customer_type  = 'Are you a premium customer ? Yes';
        }
     
        $message = '<p>'.$message.'</p><br><br>'
             . $customer_type
             . '<br><br>'.'Query from plugin support tab';
        
        $user_data  = $user->data;        
        $user_email = $user_data->user_email;     
        
        if($email){
            $user_email = $email;
        }            
        //php mailer variables        
        $sendto    = 'team@magazine3.in';
        $subject   = "Critical Css For WP Customer Query";
        
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From: '. esc_attr($user_email);            
        $headers[] = 'Reply-To: ' . esc_attr($user_email);
        // Load WP components, no themes.                      
        $sent = wp_mail($sendto, $subject, $message, $headers); 

        if($sent){

             echo json_encode(array('status'=>'t'));  

        }else{

            echo json_encode(array('status'=>'f'));            

        }
        
    }
                    
    wp_die();           
}