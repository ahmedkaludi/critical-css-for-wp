<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
function ccwp_get_atts_array($atts_string) {

    if(!empty($atts_string)) {
        $atts_array = array_map(
            function(array $attribute) {
                return $attribute['value'];
            },
            wp_kses_hair($atts_string, wp_allowed_protocols())
        );
        return $atts_array;
    }
    return false;
}

function ccwp_get_atts_string($atts_array) {

    if(!empty($atts_array)) {
        $assigned_atts_array = array_map(
        function($name, $value) {
            if($value === '') {
                return $name;
            }
            return sprintf('%s="%s"', $name, esc_attr($value));
        },
            array_keys($atts_array),
            $atts_array
        );
        $atts_string = implode(' ', $assigned_atts_array);
        return $atts_string;
    }
    return false;
}

function ccwp_delay_js_main() {

    $is_admin = current_user_can('manage_options');

    if(is_admin() || $is_admin){
        return;
    }

    if ( function_exists('is_checkout') && is_checkout() || (function_exists('is_feed')&& is_feed()) ) {
        return;
    }
    if( class_exists( 'next_article_layout' ) ) {
        return ;
    }

    if ( function_exists('elementor_load_plugin_textdomain') && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
        return;
    }

    if(ccwp_wprocket_lazyjs()){
        return $html;   
     }
    add_filter('ccwp_complete_html_after_dom_loaded', 'ccwp_delay_js_html', 2);
    add_filter('ccwp_complete_html_after_dom_loaded', 'ccwp_remove_js_query_param', 99);
    add_action('wp_footer', 'ccwp_delay_js_load', PHP_INT_MAX);
}
add_action('wp', 'ccwp_delay_js_main');

function ccwp_delay_js_html($html) {

    $html_no_comments = $html;//preg_replace('/<!--(.*)-->/Uis', '', $html);
    preg_match_all('#(<script\s?([^>]+)?\/?>)(.*?)<\/script>#is', $html_no_comments, $matches);
    if(!isset($matches[0])) {
        return $html;
    }
    $combined_ex_js_arr = array();
    foreach($matches[0] as $i => $tag) {
        $atts_array = !empty($matches[2][$i]) ? ccwp_get_atts_array($matches[2][$i]) : array();
        if(isset($atts_array['type']) && stripos($atts_array['type'], 'javascript') == false || 
            isset($atts_array['id']) && stripos($atts_array['id'], 'corewvps-mergejsfile') !== false ||
            isset($atts_array['id']) && stripos($atts_array['id'], 'corewvps-cc') !== false
        ) {
            continue;
        }
        $delay_flag = false;
        $excluded_scripts = array(
            'ccwp-delayed-scripts',
        );

        if(!empty($excluded_scripts)) {
            foreach($excluded_scripts as $excluded_script) {
                if(strpos($tag, $excluded_script) !== false) {
                    continue 2;
                }
            }
        }

        $delay_flag = true;
        if(!empty($atts_array['type'])) {
            $atts_array['data-cwvpsb-type'] = $atts_array['type'];
        }

        $atts_array['type'] = 'ccwpdelayedscript';
        $atts_array['defer'] = 'defer';

        $include = true;
        if(isset($atts_array['src'])){
            $regex = ccwp_delay_exclude_js();
        
            if($regex && preg_match( '#(' . $regex . ')#', $atts_array['src'] )){
                $combined_ex_js_arr[] = $atts_array['src'];
                //$html = str_replace($tag, '', $html);
                $include = false;       
            }
        }
        if($include && isset($atts_array['id'])){
            $regex = ccwp_delay_exclude_js();
            $file_path =  $atts_array['id'];
            if($regex && preg_match( '#(' . $regex . ')#',  $file_path)){
                $include = false;       
            }
        }
        if($include && isset($matches[3][$i])){
            $regex = ccwp_delay_exclude_js();
            $file_path =  $matches[3][$i];
            if($regex && preg_match( '#(' . $regex . ')#',  $file_path)){
                $include = false;       
            }
        }
        if(isset($atts_array['src']) && !$include){
            $include = true;
        }
        if($delay_flag && $include ) {//
    
            $delayed_atts_string = ccwp_get_atts_string($atts_array);
            $delayed_tag = sprintf('<script %1$s>', $delayed_atts_string) . (!empty($matches[3][$i]) ? $matches[3][$i] : '') .'</script>';
            $html = str_replace($tag, $delayed_tag, $html);
            continue;
        }
    }
    /*if($combined_ex_js_arr){
        $html = cwvpsb_combine_js_files($combined_ex_js_arr, $html);
    }*/
    return $html;
}

function ccwp_remove_js_query_param($html){

    $html = preg_replace('/type="ccwpdelayedscript"\s+src="(.*?)\.js\?(.*?)"/',  'type="ccwpdelayedscript" src="$1.js"', $html);
    if(preg_match('/<link(.*?)rel="ccwpdelayedstyle"(.*?)href="(.*?)\.css\?(.*?)"(.*?)>/m',$html)){
        $html = preg_replace('/<link(.*?)rel="ccwpdelayedstyle"(.*?)href="(.*?)\.css\?(.*?)"(.*?)>/',  '<link$1rel="ccwpdelayedstyle"$2href="$3.css"$5>', $html);
        }
    return $html;
}

function ccwp_delay_exclude_js(){
    $settings = critical_css_defaults();
    $inputs['exclude_js'] = $settings['exclude_delay_js'];
    if ( ! empty( $inputs['exclude_js'] ) ) {
        if ( ! is_array( $inputs['exclude_js'] ) ) {
            $inputs['exclude_js'] = explode( "\n", $inputs['exclude_js'] );
        }
        $inputs['exclude_js'] = array_map( 'trim', $inputs['exclude_js'] );
        $inputs['exclude_js'] = (array) array_filter( $inputs['exclude_js'] );
        $inputs['exclude_js'] = array_unique( $inputs['exclude_js'] );
    } else {
        $inputs['exclude_js'] = array();
    }
    $excluded_files = array();
    if($inputs['exclude_js']){
        foreach ( $inputs['exclude_js'] as $i => $excluded_file ) {
            // Escape characters for future use in regex pattern.
            $excluded_files[ $i ] = str_replace( '#', '\#', $excluded_file );
        }
    }
    if(is_array($excluded_files)){
        return implode( '|', $excluded_files );
    }else{
        return '';
    }
}
add_action( 'wp_enqueue_scripts',  'ccwp_scripts_styles' , 99999);
function ccwp_scripts_styles(){

    if(ccwp_wprocket_lazyjs()){
       return;   
    }
    global $wp_scripts;
    $wp_scripts->all_deps($wp_scripts->queue);

    $uniqueid = get_transient( CCWP_CACHE_NAME );
    global $wp;
    $url = home_url( $wp->request );
    $filename = md5($url.$uniqueid);
     $user_dirname = CCWP_JS_EXCLUDE_CACHE_DIR;
     $user_urlname = CCWP_JS_EXCLUDE_CACHE_URL;
    
    
    if(!file_exists($user_dirname.'/'.$filename.'.js')){
        $combined_ex_js_arr= array();
        $jscontent = '';
        $regex = ccwp_delay_exclude_js();
        include_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
        include_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
        if (!class_exists('WP_Filesystem_Direct')) {
             return false;
        }
        $wp_scripts->all_deps($wp_scripts->queue);
        foreach( $wp_scripts->to_do as $key=>$handle) 
        {
            $localize = $localize_handle = '';
            //$src = strtok($wp_scripts->registered[$handle]->src, '?');
            if($regex && preg_match( '#(' . $regex . ')#', $wp_scripts->registered[$handle]->src )){
                $localize_handle = $handle;
            }
            if($regex && preg_match( '#(' . $regex . ')#', $handle )){
                $localize_handle = $handle;
            }
            if($localize_handle){
                if(@array_key_exists('data', $wp_scripts->registered[$handle]->extra)) {
                    $localize = $wp_scripts->registered[$handle]->extra['data'] . ';';
                }
                $file_url = $wp_scripts->registered[$handle]->src;
                $parse_url = parse_url($file_url);
                $file_path = str_replace(array(get_site_url(),'?'.@$parse_url['query']),array(ABSPATH,''),$file_url);

                if(substr( $file_path, 0, 13 ) === "/wp-includes/"){
                    $file_path = ABSPATH.$file_path;    
                }
                $wp_filesystem = new WP_Filesystem_Direct(null);
                $js = $wp_filesystem->get_contents($file_path);
                unset($wp_filesystem);
                if (empty($js)) {
                     $request = wp_remote_get($file_url);
                     $js = wp_remote_retrieve_body($request);
                }


                //$combined_ex_js_arr[$handle] = ;
                $jscontent .= "\n/*File: $file_url*/\n".$localize.$js;
                
                //wp_deregister_script($handle);
            }
        }
        if($jscontent){
            $fileSystem = new WP_Filesystem_Direct( new StdClass() );
            if(!file_exists($user_dirname)) wp_mkdir_p($user_dirname);
            $fileSystem->put_contents($user_dirname.'/'.$filename.'.js', $jscontent, 644 );
            unset($fileSystem);
        }
    }


    $uniqueid = get_transient( CCWP_CACHE_NAME );
    global $wp;
    $url = home_url( $wp->request );
    $filename = md5($url.$uniqueid);
     $user_dirname = CCWP_JS_EXCLUDE_CACHE_DIR;
     $user_urlname = CCWP_JS_EXCLUDE_CACHE_URL;
     
     if(file_exists($user_dirname.'/'.$filename.'.js')){
        wp_register_script('corewvps-mergejsfile', $user_urlname.'/'.$filename.'.js', array(), CRITICAL_CSS_FOR_WP_VERSION, true);
        wp_enqueue_script('corewvps-mergejsfile');
     }
        
}
add_filter( 'script_loader_src', 'ccwp_remove_css_js_version', 9999, 2 );
function ccwp_remove_css_js_version($src, $handle ){
    if(ccwp_wprocket_lazyjs()){
        return $src;   
     }
    $handles_with_version = [ 'corewvps-mergejsfile', 'corewvps-cc','corewvps-mergecssfile' ];
    if ( strpos( $src, 'ver=' ) && in_array( $handle, $handles_with_version, true ) ){
        //$src = remove_query_arg( 'ver', $src );
    }
    $src = add_query_arg( 'time', time(), $src );
    return $src;
}

function ccwp_delay_js_load() {
    $submit_url =  admin_url('admin-ajax.php?action=ccwp_delay_ajax_request');
    $js_content = '<script type="text/javascript" id="ccwp-delayed-scripts">' . 'ccwpUserInteractions=["keydown","mousemove","wheel","touchmove","touchstart","touchend","touchcancel","touchforcechange"],ccwpDelayedScripts={normal:[],defer:[],async:[]},jQueriesArray=[];var ccwpDOMLoaded=!1;function ccwpTriggerDOMListener(){' . 'ccwpUserInteractions.forEach(function(e){window.removeEventListener(e,ccwpTriggerDOMListener,{passive:!0})}),"loading"===document.readyState?document.addEventListener("DOMContentLoaded",ccwpTriggerDelayedScripts):ccwpTriggerDelayedScripts()}

           var time = Date.now;
            
           function calculate_load_times() {
                // Check performance support
                if (performance === undefined) {
                    console.log("= Calculate Load Times: performance NOT supported");
                    return;
                }
            
                // Get a list of "resource" performance entries
                var resources_length=0;
                var resources = performance.getEntriesByType("resource");
                if (resources === undefined || resources.length <= 0) {
                    console.log("= Calculate Load Times: there are NO `resource` performance records");
                }
                if(resources.length)
                {
                    resources_length=resources.length;
                }

                let is_last_resource = 0;
                for (var i=0; i < resources.length; i++) {
                    if(resources[i].responseEnd>0){
                        is_last_resource = is_last_resource + 1;
                    }
                }
            
                let uag = navigator.userAgent;
                let gpat = /\sGoogle\s/gm;
                let gres = uag.match(gpat);
                let cpat = /\sChrome-/gm;
                let cres = uag.match(cpat);
                let wait_till=400;
                if(gres || cres){
                    wait_till = 3000;
                }
                if(is_last_resource==resources.length){
                    setTimeout(function(){
                        ccwpTriggerDelayedScripts();
                    },wait_till);
                }
            }
            
            document.onreadystatechange = (e) => {
                if (e.target.readyState === "complete") {
                calculate_load_times();
                }
            };
             //console.log("when delay script executed log");
             //console.log(new Date().toLocaleTimeString());
            async function ccwpTriggerDelayedScripts(){ctl(),cwvpsbDelayEventListeners(),cwvpsbDelayJQueryReady(),cwvpsbProcessDocumentWrite(),cwvpsbSortDelayedScripts(),ccwpPreloadDelayedScripts(),await cwvpsbLoadDelayedScripts(ccwpDelayedScripts.normal),await cwvpsbLoadDelayedScripts(ccwpDelayedScripts.defer),await cwvpsbLoadDelayedScripts(ccwpDelayedScripts.async),await cwvpsbTriggerEventListeners()}function cwvpsbDelayEventListeners(){let e={};function t(t,n){function r(n){return e[t].delayedEvents.indexOf(n)>=0?"cwvpsb-"+n:n}e[t]||(e[t]={originalFunctions:{add:t.addEventListener,remove:t.removeEventListener},delayedEvents:[]},t.addEventListener=function(){arguments[0]=r(arguments[0]),e[t].originalFunctions.add.apply(t,arguments)},t.removeEventListener=function(){arguments[0]=r(arguments[0]),e[t].originalFunctions.remove.apply(t,arguments)}),e[t].delayedEvents.push(n)}function n(e,t){const n=e[t];Object.defineProperty(e,t,{get:n||function(){},set:function(n){e["cwvpsb"+t]=n}})}t(document,"DOMContentLoaded"),t(window,"DOMContentLoaded"),t(window,"load"),t(window,"pageshow"),t(document,"readystatechange"),n(document,"onreadystatechange"),n(window,"onload"),n(window,"onpageshow")}function cwvpsbDelayJQueryReady(){let e=window.jQuery;Object.defineProperty(window,"jQuery",{get:()=>e,set(t){if(t&&t.fn&&!jQueriesArray.includes(t)){t.fn.ready=t.fn.init.prototype.ready=function(e){ccwpDOMLoaded?e.bind(document)(t):document.addEventListener("cwvpsb-DOMContentLoaded",function(){e.bind(document)(t)})};const e=t.fn.on;t.fn.on=t.fn.init.prototype.on=function(){if(this[0]===window){function t(e){return e.split(" ").map(e=>"load"===e||0===e.indexOf("load.")?"cwvpsb-jquery-load":e).join(" ")}"string"==typeof arguments[0]||arguments[0]instanceof String?arguments[0]=t(arguments[0]):"object"==typeof arguments[0]&&Object.keys(arguments[0]).forEach(function(e){delete Object.assign(arguments[0],{[t(e)]:arguments[0][e]})[e]})}return e.apply(this,arguments),this},jQueriesArray.push(t)}e=t}})}function cwvpsbProcessDocumentWrite(){const e=new Map;document.write=document.writeln=function(t){var n=document.currentScript,r=document.createRange();let a=e.get(n);void 0===a&&(a=n.nextSibling,e.set(n,a));var o=document.createDocumentFragment();r.setStart(o,0),o.appendChild(r.createContextualFragment(t)),n.parentElement.insertBefore(o,a)}}
            
                    function cwvpsbSortDelayedScripts(){
                           document.querySelectorAll("script[type=ccwpdelayedscript]").forEach(function(e){e.hasAttribute("src")?e.hasAttribute("defer")&&!1!==e.defer?ccwpDelayedScripts.defer.push(e):e.hasAttribute("async")&&!1!==e.async?ccwpDelayedScripts.async.push(e):ccwpDelayedScripts.normal.push(e):ccwpDelayedScripts.normal.push(e)})
                    }
            
            function ccwpPreloadDelayedScripts(){var e=document.createDocumentFragment();[...ccwpDelayedScripts.normal,...ccwpDelayedScripts.defer,...ccwpDelayedScripts.async].forEach(function(t){var n=removeVersionFromLink(t.getAttribute("src"));t.setAttribute("src",n);if(n){var r=document.createElement("link");r.href=n,r.rel="preload",r.as="script",e.appendChild(r)}}),document.head.appendChild(e)}async function cwvpsbLoadDelayedScripts(e){var t=e.shift();return t?(await cwvpsbReplaceScript(t),cwvpsbLoadDelayedScripts(e)):Promise.resolve()}async function cwvpsbReplaceScript(e){return await cwvpsbNextFrame(),new Promise(function(t){const n=document.createElement("script");[...e.attributes].forEach(function(e){let t=e.nodeName;"type"!==t&&("data-type"===t&&(t="type"),n.setAttribute(t,e.nodeValue))}),e.hasAttribute("src")?(n.addEventListener("load",t),n.addEventListener("error",t)):(n.text=e.text,t()),e.parentNode.replaceChild(n,e)})}
    function ctl(){
            var cssEle = document.querySelectorAll("link[rel=ccwpdelayedstyle]");
                console.log(cssEle.length);
                for(var i=0; i <= cssEle.length;i++){
                    if(cssEle[i]){
                        var cssMain = document.createElement("link");
                        cssMain.href = removeVersionFromLink(cssEle[i].href);
                        cssMain.rel = "stylesheet";
                        cssMain.type = "text/css";
                        document.getElementsByTagName("head")[0].appendChild(cssMain);
                    }
                }
                
                
                var cssEle = document.querySelectorAll("style[type=ccwpdelayedstyle]");
                for(var i=0; i <= cssEle.length;i++){
                    if(cssEle[i]){
                        var cssMain = document.createElement("style");
                        cssMain.type = "text/css";
                        /*cssMain.rel = "stylesheet";*/
                        /*cssMain.type = "text/css";*/
                        cssMain.textContent = cssEle[i].textContent;
                        document.getElementsByTagName("head")[0].appendChild(cssMain);
                    }
                }
            }
            function removeVersionFromLink(link)
            {
                if(!link)
                { return "";}
                const url = new URL(link);
                url.searchParams.delete("ver");
                url.searchParams.delete("time");
                return url.href;
            }
        
    async function cwvpsbTriggerEventListeners(){ccwpDOMLoaded=!0,await cwvpsbNextFrame(),document.dispatchEvent(new Event("cwvpsb-DOMContentLoaded")),await cwvpsbNextFrame(),window.dispatchEvent(new Event("cwvpsb-DOMContentLoaded")),await cwvpsbNextFrame(),document.dispatchEvent(new Event("cwvpsb-readystatechange")),await cwvpsbNextFrame(),document.cwvpsbonreadystatechange&&document.cwvpsbonreadystatechange(),await cwvpsbNextFrame(),window.dispatchEvent(new Event("cwvpsb-load")),await cwvpsbNextFrame(),window.cwvpsbonload&&window.cwvpsbonload(),await cwvpsbNextFrame(),jQueriesArray.forEach(function(e){e(window).trigger("cwvpsb-jquery-load")}),window.dispatchEvent(new Event("cwvpsb-pageshow")),await cwvpsbNextFrame(),window.cwvpsbonpageshow&&window.cwvpsbonpageshow()}async function cwvpsbNextFrame(){return new Promise(function(e){requestAnimationFrame(e)})}ccwpUserInteractions.forEach(function(e){window.addEventListener(e,ccwpTriggerDOMListener,{passive:!0})});</script>';
    echo $js_content;
}