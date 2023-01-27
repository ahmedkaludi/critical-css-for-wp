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

    add_action('wp_footer', 'ccwp_delay_js_load', PHP_INT_MAX);

    if(ccwp_check_js_defer()){
        add_filter('rocket_delay_js_exclusions', 'ccwp_add_rocket_delay_js_exclusions');
        return;   
     }
    add_filter('ccwp_complete_html_after_dom_loaded', 'ccwp_delay_js_html', 2);
    //add_filter('ccwp_complete_html_after_dom_loaded', 'ccwp_remove_js_query_param', 99);
    
}
add_action('wp', 'ccwp_delay_js_main');

function ccwp_add_rocket_delay_js_exclusions( $patterns ) {
    $patterns[] = 'ccwp-delayed-scripts';	
	return $patterns;
}

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

        if(isset($atts_array['src']) && !empty($atts_array['src'])){
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
        if($delay_flag) {
    
            $delayed_atts_string = ccwp_get_atts_string($atts_array);
            $delayed_tag = sprintf('<script %1$s>', $delayed_atts_string) . (!empty($matches[3][$i]) ? $matches[3][$i] : '') .'</script>';
            $html = str_replace($tag, $delayed_tag, $html);
            continue;
        }
    }
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
    $inputs['exclude_js'] = array();
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

    if(ccwp_check_js_defer()){
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
    if(ccwp_check_js_defer()){
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
    if(ccwp_check_js_defer()){
        $js_content = '<script type="text/javascript" id="ccwp-delayed-scripts">
        /* ccwpPreloadStyles(); */ var time=Date.now,ccfw_loaded=!1;function calculate_load_times(){if(void 0===performance){console.log("= Calculate Load Times: performance NOT supported"),setTimeout(function(){ccwpTriggerDelayedScripts()},400),console.log("performance === undefined");return}var e=0,r=performance.getEntriesByType("resource");(void 0===r||r.length<=0)&&console.log("= Calculate Load Times: there are NO `resource` performance records"),r.length&&(e=r.length);let t=0;for(var l=0;l<r.length;l++)r[l].responseEnd>0&&(t+=1);let c=navigator.userAgent,a=c.match(/\sGoogle\s/gm),n=c.match(/\sChrome-/gm),o=400;(a||n)&&(o=3e3),t==r.length&&setTimeout(function(){console.log("is_last_resource==resources.length"),ccwpTriggerDelayedScripts()},o)}async function ccwpTriggerDelayedScripts(){!ccfw_loaded&&ctl()}function ccwpPreloadStyles(){for(var e=document.createDocumentFragment(),r=document.querySelectorAll("link[rel=ccwpdelayedstyle]"),t=0;t<=r.length;t++)if(r[t]){r[t].href=removeVersionFromLink(r[t].href);var l=document.createElement("link");l.href=r[t].href,l.rel="preload",l.as="style",e.appendChild(l)}document.head.appendChild(e)}function ctl(){console.log("ctl");for(var e=document.querySelectorAll("link[rel=ccwpdelayedstyle]"),r=0;r<=e.length;r++)e[r]&&(e[r].href=removeVersionFromLink(e[r].href),e[r].rel="stylesheet",e[r].type="text/css");for(var e=document.querySelectorAll("style[type=ccwpdelayedstyle]"),r=0;r<=e.length;r++)e[r]&&(e[r].type="text/css");ccfw_loaded=!0}function removeVersionFromLink(e){if(!ccfwIsValidUrl(e))return e;{let r=new URL(ccfwFormatLink(e));return r.searchParams.delete("ver"),r.searchParams.delete("time"),r.href}}function ccfwIsValidUrl(e){if(e){var r=RegExp(/[-a-zA-Z0-9@:%_\+.~#?&//=]{2,256}\.[a-z]{2,4}\b(\/[-a-zA-Z0-9@:%_\+.~#?&//=]*)?/gi);return e.match(r)}return!1}function ccfwFormatLink(e){let r=e.match("http:"),t=e.match("https:");return r||t?e:location.protocol+e}document.addEventListener("readystatechange",e=>{"complete"===e.target.readyState&&calculate_load_times()});
      </script>';  
     }

     else{

        $js_content = '<script type="text/javascript" id="ccwp-delayed-scripts">ccwpUserInteractions=["keydown","mousemove","wheel","touchmove","touchstart","touchend","touchcancel","touchforcechange"],ccwpDelayedScripts={normal:[],defer:[],async:[]},jQueriesArray=[];var ccwpDOMLoaded=!1;function ccwpTriggerDOMListener(){' . 'ccwpUserInteractions.forEach(function(e){window.removeEventListener(e,ccwpTriggerDOMListener,{passive:!0})}),"loading"===document.readyState?document.addEventListener("DOMContentLoaded",ccwpTriggerDelayedScripts):ccwpTriggerDelayedScripts()}
            var time=Date.now,ccfw_loaded=!1;function calculate_load_times(){if(void 0===performance){console.log("= Calculate Load Times: performance NOT supported");return}var e=0,t=performance.getEntriesByType("resource");(void 0===t||t.length<=0)&&console.log("= Calculate Load Times: there are NO `resource` performance records"),t.length&&(e=t.length);let n=0;for(var a=0;a<t.length;a++)t[a].responseEnd>0&&(n+=1);let r=navigator.userAgent,c=r.match(/\sGoogle\s/gm),s=r.match(/\sChrome-/gm),o=400;(c||s)&&(o=3e3),n==t.length&&setTimeout(function(){ccwpTriggerDelayedScripts()},o)}async function ccwpTriggerDelayedScripts(){!ccfw_loaded&&(ctl(),cwvpsbDelayEventListeners(),cwvpsbDelayJQueryReady(),cwvpsbProcessDocumentWrite(),cwvpsbSortDelayedScripts(),ccwpPreloadDelayedScripts(),await cwvpsbLoadDelayedScripts(ccwpDelayedScripts.normal),await cwvpsbLoadDelayedScripts(ccwpDelayedScripts.defer),await cwvpsbLoadDelayedScripts(ccwpDelayedScripts.async),await cwvpsbTriggerEventListeners())}function cwvpsbDelayEventListeners(){let e={};function t(t,n){function a(n){return e[t].delayedEvents.indexOf(n)>=0?"cwvpsb-"+n:n}e[t]||(e[t]={originalFunctions:{add:t.addEventListener,remove:t.removeEventListener},delayedEvents:[]},t.addEventListener=function(){arguments[0]=a(arguments[0]),e[t].originalFunctions.add.apply(t,arguments)},t.removeEventListener=function(){arguments[0]=a(arguments[0]),e[t].originalFunctions.remove.apply(t,arguments)}),e[t].delayedEvents.push(n)}function n(e,t){let n=e[t];Object.defineProperty(e,t,{get:n||function(){},set:function(n){e["cwvpsb"+t]=n}})}t(document,"DOMContentLoaded"),t(window,"DOMContentLoaded"),t(window,"load"),t(window,"pageshow"),t(document,"readystatechange"),n(window,"onload"),n(window,"onpageshow")}function cwvpsbDelayJQueryReady(){let e=window.jQuery;Object.defineProperty(window,"jQuery",{get:()=>e,set(t){if(t&&t.fn&&!jQueriesArray.includes(t)){t.fn.ready=t.fn.init.prototype.ready=function(e){ccwpDOMLoaded?e.bind(document)(t):document.addEventListener("cwvpsb-DOMContentLoaded",function(){e.bind(document)(t)})};let n=t.fn.on;t.fn.on=t.fn.init.prototype.on=function(){if(this[0]===window){function e(e){return e.split(" ").map(e=>"load"===e||0===e.indexOf("load.")?"cwvpsb-jquery-load":e).join(" ")}"string"==typeof arguments[0]||arguments[0]instanceof String?arguments[0]=e(arguments[0]):"object"==typeof arguments[0]&&Object.keys(arguments[0]).forEach(function(t){delete Object.assign(arguments[0],{[e(t)]:arguments[0][t]})[t]})}return n.apply(this,arguments),this},jQueriesArray.push(t)}e=t}})}function cwvpsbProcessDocumentWrite(){let e=new Map;document.write=document.writeln=function(t){var n=document.currentScript,a=document.createRange();let r=e.get(n);void 0===r&&(r=n.nextSibling,e.set(n,r));var c=document.createDocumentFragment();a.setStart(c,0),c.appendChild(a.createContextualFragment(t)),n.parentElement.insertBefore(c,r)}}function cwvpsbSortDelayedScripts(){document.querySelectorAll("script[type=ccwpdelayedscript]").forEach(function(e){e.hasAttribute("src")?e.hasAttribute("defer")&&!1!==e.defer?ccwpDelayedScripts.defer.push(e):e.hasAttribute("async")&&!1!==e.async?ccwpDelayedScripts.async.push(e):ccwpDelayedScripts.normal.push(e):ccwpDelayedScripts.normal.push(e)})}function ccwpPreloadDelayedScripts(){var e=document.createDocumentFragment();[...ccwpDelayedScripts.normal,...ccwpDelayedScripts.defer,...ccwpDelayedScripts.async].forEach(function(t){var n=removeVersionFromLink(t.getAttribute("src"));if(n){t.setAttribute("src",n);var a=document.createElement("link");a.href=n,a.rel="preload",a.as="script",e.appendChild(a)}}),document.head.appendChild(e)}async function cwvpsbLoadDelayedScripts(e){var t=e.shift();return t?(await cwvpsbReplaceScript(t),cwvpsbLoadDelayedScripts(e)):Promise.resolve()}async function cwvpsbReplaceScript(e){return await cwvpsbNextFrame(),new Promise(function(t){let n=document.createElement("script");[...e.attributes].forEach(function(e){let t=e.nodeName;"type"!==t&&("data-type"===t&&(t="type"),n.setAttribute(t,e.nodeValue))}),e.hasAttribute("src")?(n.addEventListener("load",t),n.addEventListener("error",t)):(n.text=e.text,t()),e.parentNode.replaceChild(n,e)})}function ctl(){for(var e=document.querySelectorAll("link[rel=ccwpdelayedstyle]"),t=0;t<=e.length;t++)e[t]&&(e[t].href=removeVersionFromLink(e[t].href),e[t].rel="stylesheet",e[t].type="text/css");for(var e=document.querySelectorAll("style[type=ccwpdelayedstyle]"),t=0;t<=e.length;t++)e[t]&&(e[t].type="text/css");ccfw_loaded=!0}function removeVersionFromLink(e){if(!ccfwIsValidUrl(e))return e;{let t=new URL(ccfwFormatLink(e));return t.searchParams.delete("ver"),t.searchParams.delete("time"),t.href}}function ccfwIsValidUrl(e){if(e){var t=RegExp(/[-a-zA-Z0-9@:%_\+.~#?&//=]{2,256}\.[a-z]{2,4}\b(\/[-a-zA-Z0-9@:%_\+.~#?&//=]*)?/gi);return e.match(t)}return!1}function ccfwFormatLink(e){let t=e.match("http:"),n=e.match("https:");return t||n?e:location.protocol+e}async function cwvpsbTriggerEventListeners(){ccwpDOMLoaded=!0,await cwvpsbNextFrame(),document.dispatchEvent(new Event("cwvpsb-DOMContentLoaded")),await cwvpsbNextFrame(),window.dispatchEvent(new Event("cwvpsb-DOMContentLoaded")),await cwvpsbNextFrame(),document.dispatchEvent(new Event("cwvpsb-readystatechange")),await cwvpsbNextFrame(),document.cwvpsbonreadystatechange&&document.cwvpsbonreadystatechange(),await cwvpsbNextFrame(),window.dispatchEvent(new Event("cwvpsb-load")),await cwvpsbNextFrame(),window.cwvpsbonload&&window.cwvpsbonload(),await cwvpsbNextFrame(),jQueriesArray.forEach(function(e){e(window).trigger("cwvpsb-jquery-load")}),window.dispatchEvent(new Event("cwvpsb-pageshow")),await cwvpsbNextFrame(),window.cwvpsbonpageshow&&window.cwvpsbonpageshow()}async function cwvpsbNextFrame(){return new Promise(function(e){requestAnimationFrame(e)})}document.addEventListener("readystatechange",e=>{"complete"===e.target.readyState&&calculate_load_times()}),ccwpUserInteractions.forEach(function(e){window.addEventListener(e,ccwpTriggerDOMListener,{passive:!0})});
           </script>';
     }
    
    echo $js_content;
}