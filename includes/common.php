<?php
/**
 * Critical CSS Common Functions
 *
 * @package Common.php
 */

/**
 * Internal helper function to sanitize a string from user input or from the db
 *
 * @since 1.9.94
 * @copied from WordPress 4.7.0 core to make compatible sanitize_textarea_field with WordPress v4.6.3
 *
 * @param string $str  String to sanitize.
 * @return string Sanitized string.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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

/**
 * Check if WP Rocket is active and if JS defer is enabled
 *
 * @return bool
 */
function ccfwp_check_js_defer() {
	if ( defined( 'WP_ROCKET_VERSION' ) ) {
		$ccwp_wprocket_options = get_option( 'wp_rocket_settings', null );

		if ( isset( $ccwp_wprocket_options['defer_all_js'] ) && 1 == $ccwp_wprocket_options['defer_all_js'] ) {
			return true;
		}
	}
	return false;
}

/**
 * Apply the final filter to the content, after the DOM is loaded
 *  - This is the last filter applied to the content before it is sent to the browser
 *
 * @param string $content The content to filter.
 * @return string The filtered content
 */
function ccwp_complete_html_after_dom_loaded( $content ) {
	if ( function_exists( 'is_feed' ) && is_feed() ) {
		return $content; }
		$content = apply_filters( 'ccwp_complete_html_after_dom_loaded', $content );
	return $content;
}

add_action(
	'wp',
	function () {
		ob_start( 'ccwp_complete_html_after_dom_loaded' );
	},
	999
);
/**
 * Get the contents of a file using the WP Filesystem API
 *
 * @param string $file_path The path to the file.
 *
 * @return string|bool The contents of the file or false on failure
 */
function ccwp_file_get_contents( $file_path ) {
	global $wp_filesystem;

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	if ( ! WP_Filesystem() ) {
		return false;
	}

	// Check if file exists.
	if ( ! $wp_filesystem->exists( $file_path ) ) {
		return false;
	}

	return $wp_filesystem->get_contents( $file_path );
}
/**
 * Write the contents to a file using the WP Filesystem API
 *
 * @param string $file_path The path to the file.
 * @param string $content The content to write to the file.
 * @param int    $mode Optional. The file permissions as octal number. Default: FS_CHMOD_FILE.
 * @param bool   $append Optional. Whether to append the content to the file. Default: false.
 *
 * @return bool True on success, false on failure.
 */
function ccwp_file_put_contents( $file_path, $content, $mode = 0644, $append = false ) {
	global $wp_filesystem;

	// Ensure WP Filesystem is loaded.
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	if ( ! WP_Filesystem() ) {
		return false;
	}

	if ( $append && $wp_filesystem->exists( $file_path ) ) {

		$existing_content = $wp_filesystem->get_contents( $file_path );
		if ( false === $existing_content ) {
			return false;
		}

		$content = $existing_content . $content;
	}
	

	// Write the content to the file.
	return $wp_filesystem->put_contents( $file_path, $content, $mode );
}

/**
 * Check if a file exists using the WP Filesystem API
 *
 * @param string $file_path The path to the file.
 *
 * @return bool True if the file exists, false otherwise.
 */
function ccwp_file_exists( $file_path ) {
	global $wp_filesystem;

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	if ( ! WP_Filesystem() ) {
		return false;
	}

	return $wp_filesystem->exists( $file_path );
}

/**
 * Fetches the content from a URL using wp_remote_get.
 *
 * @param string $target_url The URL to fetch.
 * @return string The response body 
 */
function ccfwp_fetch_remote_content( $target_url ) {
    // Validate the URL
    if ( ! esc_url_raw( $target_url ) ) {
        return '';
    }

    // Fetch the remote content
    $response = wp_remote_get( $target_url, array(
        'sslverify' => false,
        'timeout'   => 30, // Add a timeout for the request
    ) );

    // Check for errors
    if ( is_wp_error( $response ) ) {
        return '';
    }

    // Check for a valid response
    $response_code = wp_remote_retrieve_response_code( $response );
    if ( $response_code !== 200 ) {
        return '';
    }

    // Retrieve and return the body of the response
    $content = wp_remote_retrieve_body( $response );
    return $content;
}
