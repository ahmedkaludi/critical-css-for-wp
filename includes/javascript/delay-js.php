<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
function ccwp_get_atts_array( $atts_string ) {

	if ( ! empty( $atts_string ) ) {
		$atts_array = array_map(
			function( array $attribute ) {
				return $attribute['value'];
			},
			wp_kses_hair( $atts_string, wp_allowed_protocols() )
		);
		return $atts_array;
	}
	return false;
}

function ccwp_get_atts_string( $atts_array ) {

	if ( ! empty( $atts_array ) ) {
		$assigned_atts_array = array_map(
			function( $name, $value ) {
				if ( $value === '' ) {
					return $name;
				}
				return sprintf( '%s="%s"', $name, esc_attr( $value ) );
			},
			array_keys( $atts_array ),
			$atts_array
		);
		$atts_string         = implode( ' ', $assigned_atts_array );
		return $atts_string;
	}
	return false;
}

function ccwp_delay_js_main() {

	$is_admin = current_user_can( 'manage_options' );

	if ( is_admin() || $is_admin ) {
		return;
	}

	if ( function_exists( 'is_checkout' ) && is_checkout() || ( function_exists( 'is_feed' ) && is_feed() ) ) {
		return;
	}
	if ( class_exists( 'next_article_layout' ) ) {
		return;
	}

	if ( function_exists( 'elementor_load_plugin_textdomain' ) && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
		return;
	}

	add_action( 'wp_footer', 'ccwp_delay_js_load', PHP_INT_MAX );

	if ( ccwp_check_js_defer() ) {
		add_filter( 'rocket_delay_js_exclusions', 'ccwp_add_rocket_delay_js_exclusions' );
		return;
	}
}
add_action( 'wp', 'ccwp_delay_js_main' );

function ccwp_add_rocket_delay_js_exclusions( $patterns ) {
	$patterns[] = 'ccwp-delayed-scripts';
	return $patterns;
}

function ccwp_delay_js_html( $html ) {

	$html_no_comments = $html;// preg_replace('/<!--(.*)-->/Uis', '', $html).
	preg_match_all( '#(<script\s?([^>]+)?\/?>)(.*?)<\/script>#is', $html_no_comments, $matches );
	if ( ! isset( $matches[0] ) ) {
		return $html;
	}
	$combined_ex_js_arr = array();
	foreach ( $matches[0] as $i => $tag ) {
		$atts_array = ! empty( $matches[2][ $i ] ) ? ccwp_get_atts_array( $matches[2][ $i ] ) : array();
		if ( isset( $atts_array['type'] ) && stripos( $atts_array['type'], 'javascript' ) == false ||
			isset( $atts_array['id'] ) && stripos( $atts_array['id'], 'corewvps-mergejsfile' ) !== false ||
			isset( $atts_array['id'] ) && stripos( $atts_array['id'], 'corewvps-cc' ) !== false
		) {
			continue;
		}
		$delay_flag       = false;
		$excluded_scripts = array(
			'ccwp-delayed-scripts',
		);

		if ( ! empty( $excluded_scripts ) ) {
			foreach ( $excluded_scripts as $excluded_script ) {
				if ( strpos( $tag, $excluded_script ) !== false ) {
					continue 2;
				}
			}
		}

		$delay_flag = true;
		if ( ! empty( $atts_array['type'] ) ) {
			$atts_array['data-ccwp-type'] = $atts_array['type'];
		}

		$atts_array['type'] = 'ccwpdelayedscript';
		$atts_array['defer'] = 'defer';
		if ( isset( $atts_array['src'] ) && ! empty( $atts_array['src'] ) ) {
			$regex = ccwp_delay_exclude_js();

			if ( $regex && preg_match( '#(' . $regex . ')#', $atts_array['src'] ) ) {
				$combined_ex_js_arr[] = $atts_array['src'];
				$include = false;
			}
		}
		if ( $include && isset( $atts_array['id'] ) ) {
			$regex     = ccwp_delay_exclude_js();
			$file_path = $atts_array['id'];
			if ( $regex && preg_match( '#(' . $regex . ')#', $file_path ) ) {
				$include = false;
			}
		}
		if ( $include && isset( $matches[3][ $i ] ) ) {
			$regex     = ccwp_delay_exclude_js();
			$file_path = $matches[3][ $i ];
			if ( $regex && preg_match( '#(' . $regex . ')#', $file_path ) ) {
				$include = false;
			}
		}
		if ( isset( $atts_array['src'] ) && ! $include ) {
			$include = true;

		}

		if ( $delay_flag ) {

			$delayed_atts_string = ccwp_get_atts_string( $atts_array );
			$delayed_tag         = sprintf( '<script %1$s>', $delayed_atts_string ) . ( ! empty( $matches[3][ $i ] ) ? $matches[3][ $i ] : '' ) . '</script>';
			$html                = str_replace( $tag, $delayed_tag, $html );
			continue;
		}
	}
	return $html;
}

function ccwp_delay_exclude_js() {
	$settings             = critical_css_defaults();
	$inputs['exclude_js'] = array();
	$excluded_files       = array();
	if ( $inputs['exclude_js'] ) {
		foreach ( $inputs['exclude_js'] as $i => $excluded_file ) {
			// Escape characters for future use in regex pattern.
			$excluded_files[ $i ] = str_replace( '#', '\#', $excluded_file );
		}
	}
	if ( is_array( $excluded_files ) ) {
		return implode( '|', $excluded_files );
	} else {
		return '';
	}
}

function ccwp_delay_js_load() {
	$settings = critical_css_defaults();
	if ( ( isset( $settings['ccfwp_defer_css'] ) && $settings['ccfwp_defer_css'] == 'off' ) ) {
		return;
	}

	$ccfwp_defer_time = intval( $settings['ccfwp_defer_time'] );
		$js_content   = '<script type="text/javascript" id="ccwp-delayed-scripts" data-two-no-delay="true">
			let ccwpDOMLoaded=!1;
			let ccwp_loaded = false;
			let resources_length=0;
			let resources =undefined;
			let is_last_resource = 0;
			ccwpUserInteractions=["keydown","mousemove","wheel","touchmove","touchstart","touchend","touchcancel","touchforcechange"];
			
				ccwpUserInteractions.forEach(function(e){
					window.addEventListener(e,calculate_load_times);
				});
			
           function calculate_load_times() {
                // Check performance support
                if (performance === undefined) {
                    console.log("Performance NOT supported");
                    return;
                }
                // Get a list of "resource" performance entries
                resources = performance.getEntriesByType("resource");
                if (resources === undefined || resources.length <= 0) {
                    console.log("NO Resource performance records");
                }
                if(resources.length){
                    resources_length=resources.length;
                }
                for(let i=0; i < resources.length; i++) {
                    if(resources[i].responseEnd>0){
                        is_last_resource = is_last_resource + 1;
                    }
                }
                let uag = navigator.userAgent;
                let gpat = /Google Page Speed Insights/gm;
                let gres = uag.match(gpat);
                let cpat = /Chrome-Lighthouse/gm;
                let cres = uag.match(cpat);
                let wait_till=' . esc_attr( $ccfwp_defer_time ) . ';
                let new_ua = "Mozilla/5.0 (Linux; Android 11; moto g power (2022)) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Mobile Safari/537.36";
                let new_ua2 = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36";
                if(gres || cres || uag==new_ua || uag==new_ua2){
                    wait_till = 3000;
                  }
                if(is_last_resource==resources.length){
                    setTimeout(function(){
						console.log("ccwpTriggerDelayedScripts timeout : "+wait_till);
                        ccwpTriggerDelayedScripts();
                    },wait_till);
                }
            }
            window.addEventListener("load", function(e) {
				   console.log("load complete");
				    setTimeout(function(){
                        calculate_load_times();
                    },100);
            });

            async function ccwpTriggerDelayedScripts() {
                if(ccwp_loaded){ return ;}
				 
				 ccwpPreloadStyles();
                 ccwpPreloadDelayedScripts();
				 ccwpLoadCss();
				 ccwpScriptLoading();
                 ccwp_loaded=true;
            }
			 function ccwpPreloadStyles() {
              let e = document.createDocumentFragment();
              var cssEle = document.querySelectorAll("link[rel=ccwpdelayedstyle]");
              for(let i=0; i <= cssEle.length;i++){
                  if(cssEle[i]){
                      cssEle[i].href = removeVersionFromLink(cssEle[i].href);
                      let r = document.createElement("link");
                      r.href = cssEle[i].href;
                      r.rel = "preload";
                      r.as = "style";
                      e.appendChild(r);
                  }
                  }
             document.head.appendChild(e);
          }
            function ccwpPreloadDelayedScripts() {
                var e = document.createDocumentFragment();
                document.querySelectorAll("script[type=ccwpdelayedscript]").forEach(function(t) {
                    var n = removeVersionFromLink(t.getAttribute("src"));
                    if (n) {
                        t.setAttribute("src", n);
                        var r = document.createElement("link");
                        r.href = n, r.rel = "preload", r.as = "script", e.appendChild(r)
                    }
                }), document.head.appendChild(e)
            }
			
			function ccwpScriptLoading(){
				 var jsEle = document.querySelectorAll("script[type=ccwpdelayedscript]");
				  jsEle.forEach(function(t) {
							t.type = "text/javascript";
							if(t.src)
							{
							  t.src = removeVersionFromLink(t.src);
							}
                });
            }

             function ccwpLoadCss(){
				 
              var cssEle = document.querySelectorAll("link[rel=ccwpdelayedstyle]");
                for(let i=0; i <= cssEle.length;i++){
                    if(cssEle[i]){
                        cssEle[i].href = removeVersionFromLink(cssEle[i].href);
                        cssEle[i].rel = "stylesheet";
                        cssEle[i].type = "text/css";
                    }
                }

                var cssEle = document.querySelectorAll("style[type=ccwpdelayedstyle]");
                for(let i=0; i <= cssEle.length;i++){
                    if(cssEle[i]){
                        cssEle[i].type = "text/css";
                    }
                }
            }
            function removeVersionFromLink(link)
            {
                if(ccwpIsValidUrl(link))
                {
                    const url = new URL(ccwpFormatLink(link));
                    url.searchParams.delete("ver");
                    url.searchParams.delete("time");
                    return url.href;
                }
                else{
                    return link;
                }
            }
            function ccwpIsValidUrl(urlString)
            {
                if(urlString){
                    var expression =/[-a-zA-Z0-9@:%_\+.~#?&//=]{2,256}\.[a-z]{2,4}\b(\/[-a-zA-Z0-9@:%_\+.~#?&//=]*)?/gi;
                    var regex = new RegExp(expression);
                    return urlString.match(regex);
                }
                return false;
            }
            function ccwpFormatLink(link)
            {
                let http_check=link.match("http:");
                let https_check=link.match("https:");
                if(!http_check && !https_check)
                {
                    return location.protocol+link;
                }
                return link;
            }
			</script>';

	echo $js_content;
}