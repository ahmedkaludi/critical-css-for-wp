<?php

function ccfwp_t_string($string){
    return esc_html__( $string , 'criticalcssforwp');   
}
/**
 * Internal helper function to sanitize a string from user input or from the db
 *
 * @since 1.9.94
 * @copied from wordpress 4.7.0 core to make compatible sanitize_textarea_field with WordPress v4.6.3
 *
 * @param string $str           String to sanitize.
 * @param bool   $keep_newlines Optional. Whether to keep newlines. Default: false.
 * @return string Sanitized string.
 */
function ccfwp_sanitize_textarea_field( $str ) {
	if ( is_object( $str ) || is_array( $str ) ) {
		return '';
	}

	$str = (string) $str;

	$filtered = wp_check_invalid_utf8( $str );

	if ( strpos( $filtered, '<' ) !== false ) {
		$filtered = wp_pre_kses_less_than( $filtered );
		// This will strip extra whitespace for us.
		$filtered = wp_strip_all_tags( $filtered, false );

		// Use HTML entities in a special case to make sure no later
		// newline stripping stage could lead to a functional tag.
		$filtered = str_replace( "<\n", "&lt;\n", $filtered );
	}
	
	$filtered = trim( $filtered );

	$found = false;
	while ( preg_match( '/%[a-f0-9]{2}/i', $filtered, $match ) ) {
		$filtered = str_replace( $match[0], '', $filtered );
		$found    = true;
	}

	if ( $found ) {
		// Strip out the whitespace that may now exist after removing the octets.
		$filtered = trim( preg_replace( '/ +/', ' ', $filtered ) );
	}

	return $filtered;
}

function ccwp_complete_html_after_dom_loaded( $content ) {
    if(function_exists('is_feed')&& is_feed()){ return $content; }
    	$content = apply_filters('ccwp_complete_html_after_dom_loaded', $content);
    return $content;
}

add_action('wp', function(){ ob_start('ccwp_complete_html_after_dom_loaded'); }, 999);