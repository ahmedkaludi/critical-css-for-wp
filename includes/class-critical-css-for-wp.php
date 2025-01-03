<?php
/**
 * Critical CSS functionality
 *
 * @since 1.0
 * @package critical-css-for-wp
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Critical_Css_For_Wp {

	/**
	 * Set cache folder 
	 * @return mixed
	 */
	public function cachepath() {
		$cp_settings = ccfwp_defaults();
		if ( defined( CRITICAL_CSS_FOR_WP_CSS_DIR ) || isset( $cp_settings['ccfwp_alt_cachepath'] ) ) {
			if ( 1  == $cp_settings['ccfwp_alt_cachepath'] ) {
				return CRITICAL_CSS_FOR_WP_CSS_DIR_ALT;
			}
			return CRITICAL_CSS_FOR_WP_CSS_DIR;
		} else {
			return WP_CONTENT_DIR . '/cache/critical-css-for-wp/css/';
		}
	}

	/**
	 * Adding hooks for critical css
	 * @return void
	 */
	public function critical_hooks() {

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return;
		}
		if ( function_exists( 'elementor_load_plugin_textdomain' ) && isset( \Elementor\Plugin::$instance->preview ) && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			return;
		}
		if ( class_exists( 'FlexMLS_IDX' ) ) {
			add_action( 'init', array( $this, 'ccwp_flexmls_fix' ) );
		}
		add_action( 'admin_notices', array( $this, 'ccfwp_add_admin_notices' ) );
		add_action( 'wp', array( $this, 'delay_css_loadings' ), 999 );
		add_action(
			'create_term',
			function ( $term_id, $tt_id, $taxonomy ) {
				$this->on_term_create( $term_id, $tt_id, $taxonomy );
			},
			10,
			3
		);

		add_action(
			'save_post',
			function ( $post_ID, $post, $update ) {
				$this->on_post_change( $post_ID, $post );
			},
			10,
			3
		);
		add_action(
			'wp_insert_post',
			function ( $post_ID, $post, $update ) {
				$this->on_post_change( $post_ID, $post );
			},
			10,
			3
		);
		add_action( 'wp_enqueue_scripts', array( $this, 'ccfwp_add_styles' ) );

		add_action( 'wp_ajax_ccfwp_showdetails_data', array( $this, 'ccfwp_showdetails_data' ) );

		add_action( 'wp_ajax_ccfwp_showdetails_data_completed', array( $this, 'ccfwp_showdetails_data_completed' ) );
		add_action( 'wp_ajax_ccfwp_showdetails_data_failed', array( $this, 'ccfwp_showdetails_data_failed' ) );
		add_action( 'wp_ajax_ccfwp_showdetails_data_queue', array( $this, 'ccfwp_showdetails_data_queue' ) );

		add_action( 'wp_ajax_ccfwp_resend_urls_for_cache', array( $this, 'ccfwp_resend_urls_for_cache' ) );
		add_action( 'wp_ajax_ccfwp_resend_single_url_for_cache', array( $this, 'ccfwp_resend_single_url_for_cache' ) );

		add_action( 'wp_ajax_ccfwp_reset_urls_cache', array( $this, 'ccfwp_reset_urls_cache' ) );
		add_action( 'wp_ajax_ccfwp_recheck_urls_cache', array( $this, 'ccfwp_recheck_urls_cache' ) );

		add_action( 'wp_ajax_ccfwp_cc_all_cron', array( $this, 'ccfwp_generate_crtlcss_per_minute' ) );

		add_filter( 'cron_schedules', array( $this, 'ccfwp_generate_crtlcss' ) );
		if ( ! wp_next_scheduled( 'ccfwp_generate_crtlcss' ) ) {
			if ( $this->ccfwp_is_wpcron_active() ) {
				wp_schedule_event( time(), 'every_30_seconds', 'ccfwp_generate_crtlcss' );
			}
		}else{
			if ( ! $this->ccfwp_is_wpcron_active() ) {
				wp_clear_scheduled_hook( 'ccfwp_generate_crtlcss' );
			}

		}
		add_action( 'ccfwp_generate_crtlcss', array( $this, 'ccfwp_generate_crtlcss_per_minute' ) );
		add_action( 'current_screen', array( $this, 'ccfwp_custom_critical_css_generate' ) );
		add_action( 'wp_ajax_ccfwp_generate_css', array( $this, 'ccfwp_generate_css_cache' ) );
	}
	/**
	 * Add session variable for flexmls fix
	 * @return void
	 */

	public function ccwp_flexmls_fix() {
		$_SESSION['ccwp_current_uri'] = isset($_SERVER['REQUEST_URI']) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reason: sanitization using wp_unslash.
	}
	/**
	 * generate critical css for the page
	 * @return void
	 */
	public function ccfwp_custom_critical_css_generate() {
		$settings           = ccfwp_defaults();
		$ccfwp_generate_css = isset( $settings['ccfwp_generate_css'] ) ? $settings['ccfwp_generate_css'] : 'off';
		if ( is_admin() && ! $this->ccfwp_is_wpcron_active() && $ccfwp_generate_css == 'on' ) {
			$ccwp_last_cron_update = get_option( 'ccwp_last_altcron_update', 0 );
			if ( $ccwp_last_cron_update < strtotime( '-1 minute' ) ) {
				$this->ccfwp_generate_crtlcss_per_minute();
				update_option( 'ccwp_last_altcron_update', time() );
			}
		}
	}
	//create a ajax to generate the css manually and return the updated status
	public function ccfwp_generate_css_cache() {
		// check for nonce and manage_option permission
		if ( ! isset( $_POST['ccfwp_security_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ccfwp_security_nonce'] ) ), 'ccfwp_ajax_check_nonce' ) ) { //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reason: sanitization using wp_unslash.
			wp_die();
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}
		$this->save_posts_url();
		$this->save_terms_urls();
		$this->save_others_urls();
		$this->generate_css_on_interval();
		wp_send_json($this->ccfwp_get_status_summary());
	}

	/**
	 * Check if WP Cron is active
	 * @return bool
	 */
	private function ccfwp_is_wpcron_active() {

		$settings           = ccfwp_defaults();
		
		$ccfwp_generate_css = isset( $settings['ccfwp_generation_type'] ) ? $settings['ccfwp_generation_type'] : 'auto';
		if ( $ccfwp_generate_css == 'manual' ) {
			return false;
		}

		if ( ! defined( 'DISABLE_WP_CRON' ) ) {
			return true;
		}
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON == false ) {
			return true;
		}
		
		return false;
	}
	public function on_term_create( $term_id, $tt_id, $taxonomy ) {

		$settings   = ccfwp_defaults();
		$post_types = array();
		if ( ! empty( $settings['ccfwp_on_tax_type'] ) ) {
			foreach ( $settings['ccfwp_on_tax_type'] as $key => $value ) {
				if ( $value ) {
					$post_types[] = $key;
				}
			}
		}

		if ( in_array( $taxonomy, $post_types ) ) {
			$term = get_term( $term_id );
			if ( $term ) {
				$this->insert_update_terms_url( $term );
			}
		}
	}

	public function on_post_change( $post_id, $post ) {

		$settings   = ccfwp_defaults();
		$post_types = array( 'post' );
		if ( ! empty( $settings['ccfwp_on_cp_type'] ) ) {
			foreach ( $settings['ccfwp_on_cp_type'] as $key => $value ) {
				if ( $value ) {
					$post_types[] = $key;
				}
			}
		}

		if ( in_array( $post->post_type, $post_types ) ) {
			$permalink = get_permalink( $post_id );
			$permalink = $this->append_slash_permalink( $permalink );
			if ( $post->post_status == 'publish' ) {
				$this->insert_update_posts_url( $post_id );
			}
		}
	}
	/**
	 * Insert or update post url using post id
	 * @param int $post_id post id.
	 * @return void
	 */
	public function insert_update_posts_url( $post_id ) {

		global $wpdb, $table_prefix;
				$table_name         = $table_prefix . 'critical_css_for_wp_urls';
				$table_name_escaped = esc_sql( $table_name );

		$permalink = get_permalink( $post_id );
		if ( ! empty( $permalink ) ) {

			$permalink = $this->append_slash_permalink( $permalink );

			$pid = $wpdb->get_var( //phpcs:ignore -- Reason: Using custom query on non-core tables.
				$wpdb->prepare(
					//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
					"SELECT `url` FROM {$table_name_escaped} WHERE `url`=%s limit 1",
					$permalink
				)
			);

			if ( is_null( $pid ) ) {
				$wpdb->insert( //phpcs:ignore -- Reason: Using custom query on non-core tables.
					$table_name,
					array(
						'url_id'     => $post_id,
						'type'       => get_post_type( $post_id ),
						'type_name'  => get_post_type( $post_id ),
						'url'        => $permalink,
						'status'     => 'queue',
						'created_at' => gmdate( 'Y-m-d h:i:sa' ),
					),
					array( '%d', '%s', '%s', '%s', '%s', '%s' )
				);

			} else {
				$wpdb->update( //phpcs:ignore -- Reason: Using custom query on non-core tables.
					$table_name,
					array(
						'status'     => 'queue',
						'created_at' => gmdate( 'Y-m-d h:i:sa' ),
					),
					array( 'url' => $permalink ),
					array( '%s', '%s' ),
					array( '%s' )
				);
				$user_dirname = $this->cachepath();
				$user_dirname = trailingslashit( $user_dirname );
				$new_file     = $user_dirname . '/' . md5( $permalink ) . '.css';
				if ( ccwp_file_exists( $new_file ) ) {
					wp_delete_file( $new_file );
				}
			}
		}
	}
	/**
	 * Add cron schedule
	 * @param mixed $schedules
	 * @return mixed
	 */
	public function ccfwp_generate_crtlcss( $schedules ) {
		$schedules['every_30_seconds'] = array(
			'interval' => 30 * 1,
			'display'  => __( 'Every 30 Seconds', 'critical-css-for-wp' ),
		);
		return $schedules;
	}
	/**
	 * Insert or update term url using term object
	 * @param mixed $term
	 * @return void
	 */
	public function insert_update_terms_url( $term ) {
		if ( ! is_object( $term ) ) {
			return;
		}
		global  $wpdb, $table_prefix;
				$table_name         = $table_prefix . 'critical_css_for_wp_urls';
				$table_name_escaped = esc_sql( $table_name );
				$permalink          = get_term_link( $term );

		if ( ! empty( $permalink ) ) {

			$permalink = $this->append_slash_permalink( $permalink );

			$pid = $wpdb->get_var( //phpcs:ignore -- Reason: Using custom query on non-core tables.
				$wpdb->prepare(
					//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
					"SELECT `url` FROM {$table_name_escaped} WHERE `url`=%s limit 1",
					$permalink
				)
			);

			if ( is_null( $pid ) ) {
				$wpdb->insert( //phpcs:ignore -- Reason: Using custom query on non-core tables.
					$table_name,
					array(
						'url_id'     => $term->term_id,
						'type'       => $term->taxonomy,
						'type_name'  => $term->taxonomy,
						'url'        => $permalink,
						'status'     => 'queue',
						'created_at' => gmdate( 'Y-m-d h:i:sa' ),
					),
					array( '%d', '%s', '%s', '%s', '%s', '%s' )
				);

			} else {
				$wpdb->update( //phpcs:ignore -- Reason: Using custom query on non-core tables.
					$table_name,
					array(
						'status'     => 'queue',
						'created_at' => gmdate( 'Y-m-d h:i:sa' ),
					),
					array( 'url' => $permalink ),
					array( '%s', '%s' ),
					array( '%s' )
				);
				$user_dirname = $this->cachepath();
				$user_dirname = trailingslashit( $user_dirname );
				$new_file     = $user_dirname . '/' . md5( $user_dirname ) . '.css';
				if ( ccwp_file_exists( $new_file ) ) {
					wp_delete_file( $new_file );
				}
			}
		}
	}

	/**
	 * Save posts url in database for generating critical css
	 * @return void
	 */
	public function save_posts_url() {

			global $wpdb, $table_prefix;
			$settings = ccfwp_defaults();

			$post_types = array();

		if ( ! empty( $settings['ccfwp_on_cp_type'] ) ) {
			foreach ( $settings['ccfwp_on_cp_type'] as $key => $value ) {
				if ( $value ) {
					$post_types[] = $key;
				}
			}
		}

			$post_types     = array_map( 'esc_sql', $post_types );
			$imploded_types = "'" . implode( "','", $post_types ) . "'";
			$start          = get_option( 'ccfwp_current_post' ) ? get_option( 'ccfwp_current_post' ) : 0;
			$limit          = ( get_option( 'ccfwp_scan_urls' ) > 0 ) ? intval( get_option( 'ccfwp_scan_urls' ) ) : 30;
			$posts = $wpdb->get_results( //phpcs:ignore -- Reason: Using custom query on non-core tables.
				$wpdb->prepare(
					//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
					"SELECT `ID` FROM $wpdb->posts WHERE post_status='publish' AND ID > %d
					AND post_type IN($imploded_types) LIMIT %d", //phpcs:ignore -- Reason: $imploded is escaped above.
					$start,
					$limit
				),
				ARRAY_A
			);

		if ( ! empty( $posts ) ) {
			foreach ( $posts as $post ) {
				$this->insert_update_posts_url( $post['ID'] );
				$start = $post['ID'];
			}
		}
			update_option( 'ccfwp_current_post', $start );
	}

	/**
	 * Save other urls in database for generating critical css
	 * @return void
	 */
	public function save_others_urls() {

		global $wpdb, $table_prefix;
		$table_name         = $table_prefix . 'critical_css_for_wp_urls';
		$table_name_escaped = esc_sql( $table_name );

		$settings = ccfwp_defaults();
		$urls_to  = array();
		if ( isset( $settings['ccfwp_on_home'] ) && $settings['ccfwp_on_home'] == 1 ) {
			$urls_to[] = get_home_url(); // always purge home page if any other page is modified.
			$urls_to[] = get_home_url() . '/'; // always purge home page if any other page is modified.
			$urls_to[] = home_url( '/' ); // always purge home page if any other page is modified.
			$urls_to[] = site_url( '/' ); // always purge home page if any other page is modified.
		}

		if ( ! empty( $urls_to ) ) {

			foreach ( $urls_to as $key => $value ) {

				$pid = $wpdb->get_var( //phpcs:ignore -- Reason: Using custom query on non-core tables.
					$wpdb->prepare(
						//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
						"SELECT `url` FROM {$table_name_escaped} WHERE `url`=%s limit 1",
						$value
					)
				);
				$id = ( $key++ ) + 999999999;
				if ( is_null( $pid ) ) {

					$wpdb->insert( //phpcs:ignore -- Reason: Using custom query on non-core tables.
						$table_name,
						array(
							'url_id'     => $id,
							'type'       => 'others',
							'type_name'  => 'others',
							'url'        => $value,
							'status'     => 'queue',
							'created_at' => gmdate( 'Y-m-d' ),
						),
						array( '%d', '%s', '%s', '%s', '%s', '%s' )
					);

				} else {
					$wpdb->query( //phpcs:ignore -- Reason: Using custom query on non-core tables.
						$wpdb->prepare(
							//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
							"UPDATE {$table_name_escaped} SET `url` = %s WHERE `url_id` = %d",
							$value,
							$id
						)
					);

				}
			}
		}
	}

	/**
	 * Save terms urls in database for generating critical css
	 * @return void
	 */
	public function save_terms_urls() {

		global $wpdb;
		$table_name         = $wpdb->term_taxonomy;
		$table_name_escaped = esc_sql( $table_name );

		$settings = ccfwp_defaults();

		$taxonomy_types = array();

		if ( ! empty( $settings['ccfwp_on_tax_type'] ) ) {
			foreach ( $settings['ccfwp_on_tax_type'] as $key => $value ) {
				if ( $value ) {
					$taxonomy_types[] = $key;
				}
			}
		}
			$taxonomy_types_escaped = array_map( 'esc_sql', $taxonomy_types );
			$imploded_types_escaped = "'" . implode( "','", $taxonomy_types_escaped ) . "'";

			$start = get_option( 'ccfwp_current_term' ) ? get_option( 'ccfwp_current_term' ) : 0;
			$limit = ( get_option( 'ccfwp_scan_urls' ) > 0 ) ? intval( get_option( 'ccfwp_scan_urls' ) ) : 30;
			$terms = $wpdb->get_results( //phpcs:ignore -- Reason: Using custom query on non-core tables.
				$wpdb->prepare(
					//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
					"SELECT `term_id`, `taxonomy` FROM {$table_name_escaped}
					WHERE  taxonomy IN({$imploded_types_escaped}) AND term_id> %d LIMIT %d", //phpcs:ignore -- Reason: $imploded_types_escaped is escaped above.
					$start,
					$limit
				),
				ARRAY_A
			);

		if ( ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$term_obj = get_term( $term['term_id'] );
				if ( ! is_wp_error( $term_obj ) ) {
					$this->insert_update_terms_url( $term_obj );
				}   $start = $term['term_id'];
			}
		}
			update_option( 'ccfwp_current_term', $start );
	}

	/**
	 * Generate critical css for the page and
	 * save it in the cache directory
	 * @return array
	 */
	public function ccfwp_save_critical_css_in_dir_php( $current_url ) {

		$target_url    = $current_url;
		$user_dirname = $this->cachepath();
		$content     = ccfwp_fetch_remote_content( $target_url);
		$regex1       = '/<link(.*?)href="(.*?)"(.*?)>/';
		preg_match_all( $regex1, $content, $matches1, PREG_SET_ORDER );
		$regex2 = "/<link(.*?)href='(.*?)'(.*?)>/";
		preg_match_all( $regex2, $content, $matches2, PREG_SET_ORDER );
		$matches = array_merge( $matches1, $matches2 );

		$rowcss  = '';
		$all_css = array();

		if ( $matches ) {

			foreach ( $matches as $mat ) {
				if ( ( strpos( $mat[2], '.css' ) !== false ) && ( strpos( $mat[1], 'preload' ) === false ) ) {
					$all_css[]  = $mat[2];
					$rowcssdata = ccfwp_fetch_remote_content( $mat[2] );
					$regexn     = '/@import\s*(url)?\s*\(?([^;]+?)\)?;/';

					preg_match_all( $regexn, $rowcssdata, $matchen, PREG_SET_ORDER );

					if ( ! empty( $matchen ) ) {
						foreach ( $matchen as $matn ) {
							if ( isset( $matn[2] ) ) {
								$explod = explode( '/', $matn[2] );
								if ( is_array( $explod ) ) {
									$style = trim( end( $explod ), '"' );
									if ( strpos( $style, '.css' ) !== false ) {
										$pthemestyle = get_template_directory_uri() . '/' . $style;
										$rowcss     .= ccfwp_fetch_remote_content( $pthemestyle );
									}
								}
							}
						}
					}

					$rowcss .= $rowcssdata;
				}
			}
		}

		if ( $content ) {

			$d    = new DOMDocument();
			$mock = new DOMDocument();
			libxml_use_internal_errors( true );
			$d->loadHTML( $content );
			$body = $d->getElementsByTagName( 'body' )->item( 0 );
			foreach ( $body->childNodes as $child ) {
				$mock->appendChild( $mock->importNode( $child, true ) );
			}

			$raw_html = $mock->saveHTML();

			require_once CRITICAL_CSS_FOR_WP_PLUGIN_DIR . 'css-extractor/vendor/autoload.php';

			$extracted_css_arr = array();
			$page_specific     = new \PageSpecificCss\PageSpecificCss();
			$page_specific_css = preg_replace( '/@media[^{]*+{([^{}]++|{[^{}]*+})*+}/', '', $rowcss );
			$page_specific->addBaseRules( $page_specific_css );
			$page_specific->addHtmlToStore( $raw_html );
			$extracted_css        = $page_specific->buildExtractedRuleSet();
			$extracted_css_arr[] = $extracted_css;

		}

		preg_match_all( '/@media[^{]*+{([^{}]++|{[^{}]*+})*+}/', $rowcss, $matchess, PREG_SET_ORDER );

		if ( $matchess ) {

			foreach ( $matchess as $key => $value ) {

				if ( isset( $value[0] ) ) {
					$explod = explode( '{', $value[0] );
					if ( $explod[0] ) {
						$value[0] = str_replace( $explod[0] . '{', '', $value[0] );
						$value[0] = str_replace( $explod[0] . ' {', '', $value[0] );
						$value[0] = str_replace( $explod[0] . '  {', '', $value[0] );
						$value[0] = substr( $value[0], 0, -1 );

						if ( $value[0] ) {
							$page_specific = new \PageSpecificCss\PageSpecificCss();
							$page_specific->addBaseRules( $value[0] );
							$page_specific->addHtmlToStore( $raw_html );
							$extracted_css = $page_specific->buildExtractedRuleSet();
							if ( $extracted_css ) {
								$extracted_css        = $explod[0] . '{' . $extracted_css . '}';
								$extracted_css_arr[] = $extracted_css;
							}
						}
					}
				}
			}
		}

		if ( ! empty( $extracted_css_arr ) && is_array( $extracted_css_arr ) ) {

				$critical_css = implode( '', $extracted_css_arr );
				$target_url    = trailingslashit( $target_url );
				$critical_css = str_replace( "url('wp-content/", "url('" . get_site_url() . '/wp-content/', $critical_css );
				$critical_css = str_replace( 'url("wp-content/', 'url("' . get_site_url() . '/wp-content/', $critical_css );
				$new_file     = $user_dirname . '/' . md5( $target_url ) . '.css';

				$result = ccwp_file_put_contents( $new_file, $critical_css );
			if ( $result ) {
				return array(
					'status'  => true,
					'message' => esc_html__( 'CSS created sucessfully', 'critical-css-for-wp' ),
				);
			} else {
				return array(
					'status'  => false,
					'message' => esc_html__( 'Could not write into css file', 'critical-css-for-wp' ),
				);
			}
		} else {
			return array(
				'status'  => false,
				'message' => esc_html__( 'Critical css does not generated from server', 'critical-css-for-wp' ),
			);
		}
	}
	public function generate_css_on_interval() {

		global $wpdb;
		$table_name         = $wpdb->prefix . 'critical_css_for_wp_urls';
		$table_name_escaped = esc_sql( $table_name );
		$settings           = ccfwp_defaults();
		$limit              = ( intval( $settings['ccfwp_generate_urls'] ) > 0 ) ? intval( $settings['ccfwp_generate_urls'] ) : 4;

		$result = $wpdb->get_results( //phpcs:ignore -- Reason: Using custom query on non-core tables.
			$wpdb->prepare(
				//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
				"SELECT * FROM {$table_name_escaped} WHERE `status` IN  (%s) LIMIT %d",
				'queue',
				$limit
			),
			ARRAY_A
		);

		if ( ! empty( $result ) ) {

			$user_dirname = $this->cachepath();
			if ( ! is_dir( $user_dirname ) ) {
				wp_mkdir_p( $user_dirname );
			}

			if ( is_dir( $user_dirname ) ) {

				foreach ( $result as $value ) {

					if ( $value['url'] ) {
						$status       = 'inprocess';
						$cached_name  = '';
						$failed_error = '';
						$this->change_caching_status( $value['url'], $status );
						$result = $this->ccfwp_save_critical_css_in_dir_php( $value['url'] );
						if ( $result['status'] ) {
							$status      = 'cached';
							$cached_name = md5( $value['url'] );
						} else {
							$status       = 'failed';
							$failed_error = $result['message'];
						}

						$this->change_caching_status( $value['url'], $status, $cached_name, $failed_error );
					}
				}
			}
		}
	}

	public function change_caching_status( $url, $status, $cached_name = null, $failed_error = null ) {

		global $wpdb, $table_prefix;
		$table_name         = $table_prefix . 'critical_css_for_wp_urls';
		$table_name_escaped = esc_sql( $table_name );

		$result = $wpdb->query( //phpcs:ignore -- Reason: Using custom query on non-core tables.
			$wpdb->prepare(
				//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
				"UPDATE {$table_name_escaped} SET `status` = %s,  `cached_name` = %s,  `updated_at` = %s,  `failed_error` = %s WHERE `url` = %s",
				$status,
				$cached_name,
				gmdate( 'Y-m-d h:i:sa' ),
				$failed_error,
				$url
			)
		);
	}

	public function ccfwp_generate_crtlcss_per_minute() {
		$settings = ccfwp_defaults();
		$ccfwp_generate_css = isset( $settings['ccfwp_generation_type'] ) ? $settings['ccfwp_generation_type'] : 'auto';
		if ( $ccfwp_generate_css == 'auto' ) {
			$this->save_posts_url();
			$this->save_terms_urls();
			$this->save_others_urls();
			$this->generate_css_on_interval();
		}
	}

	public function append_slash_permalink( $permalink ) {

		$permalink_structure = get_option( 'permalink_structure' );
		$append_slash        = substr( $permalink_structure, -1 ) == '/' ? true : false;
		if ( $append_slash ) {
			$permalink = trailingslashit( $permalink );
		} else {
			$permalink = $permalink . $append_slash;
		}

		return $permalink;
	}

	public function ccfwp_add_styles() {
		$user_dirname = $this->cachepath();
		global $wp, $wpdb, $table_prefix;
		$table_name         = $table_prefix . 'critical_css_for_wp_urls';
		$table_name_escaped = esc_sql( $table_name );
		$url = home_url( $wp->request );
		if ( class_exists( 'FlexMLS_IDX' ) && isset( $_SESSION['ccwp_current_uri'] ) ) {
			$url = esc_url( home_url( $_SESSION['ccwp_current_uri'] ) ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reason: sanitization using esc_url.
		}
		$custom_css = '';
		if ( in_array( 'elementor/elementor.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			$custom_css = '.elementor-location-footer:before{content:"";display:table;clear:both;}.elementor-icon-list-items .elementor-icon-list-item .elementor-icon-list-text{display:inline-block;}.elementor-posts__hover-gradient .elementor-post__card .elementor-post__thumbnail__link:after {display: block;content: "";background-image: -o-linear-gradient(bottom,rgba(0,0,0,.35) 0,transparent 75%);background-image: -webkit-gradient(linear,left bottom,left top,from(rgba(0,0,0,.35)),color-stop(75%,transparent));background-image: linear-gradient(0deg,rgba(0,0,0,.35),transparent 75%);background-repeat: no-repeat;height: 100%;width: 100%;position: absolute;bottom: 0;opacity: 1;-webkit-transition: all .3s ease-out;-o-transition: all .3s ease-out;transition: all .3s ease-out;}';
		}
		$url = trailingslashit( $url );
		if ( ccwp_file_exists( $user_dirname . md5( $url ) . '.css' ) ) {
			$css      = '';
			$response = ccwp_file_get_contents( $user_dirname . md5( $url ) . '.css' );
			if ( $response ) {
				$css = $response;
			}
			$css .= $custom_css;
			$css  = $this->ccfwp_clean_css( $css );

			wp_register_style( 'critical-css-for-wp', false, array(), CRITICAL_CSS_FOR_WP_VERSION );
			wp_enqueue_style( 'critical-css-for-wp', false, array(), CRITICAL_CSS_FOR_WP_VERSION );
			wp_add_inline_style( 'critical-css-for-wp', $css );

		} else {
			$wpdb->query( //phpcs:ignore -- Reason: Using custom query on non-core tables.
				$wpdb->prepare(
					//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
					"UPDATE {$table_name_escaped} SET `status` = %s,  `cached_name` = %s WHERE `url` = %s",
					'queue',
					'',
					$url
				)
			);
		}
	}

	public function delay_css_loadings() {

		$is_admin = current_user_can( 'manage_options' );

		if ( is_admin() || $is_admin ) {
			return;
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() || ( function_exists( 'is_feed' ) && is_feed() ) ) {
			return;
		}
		if ( function_exists( 'elementor_load_plugin_textdomain' ) && isset( \Elementor\Plugin::$instance->preview ) && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			return;
		}

		add_filter( 'ccwp_complete_html_after_dom_loaded', array( $this, 'ccwp_delay_css_html' ), 1, 1 );
	}

	public function ccwp_delay_css_html( $html ) {
		$jetpack_boost = false;
		$settings      = ccfwp_defaults();
		$url_arg       = '';

		if ( class_exists( 'FlexMLS_IDX' ) && isset( $_SESSION['ccwp_current_uri'] ) ) {
			$url_arg = esc_url( home_url( $_SESSION['ccwp_current_uri'] ) ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reason: sanitization using esc_url.
		}

		if ( ! $this->check_critical_css( $url_arg ) || preg_match( '/<style id="jetpack-boost-critical-css">/s', $html ) || ( isset( $settings['ccfwp_defer_css'] ) && $settings['ccfwp_defer_css'] == 'off' ) ) {
			return $html;
		}

		$html_no_comments = preg_replace( '/<!--(.|\s)*?-->/', '', $html );

		preg_match_all( '/<link\s?([^>]+)?>/is', $html_no_comments, $matches );

		if ( ! isset( $matches[0] ) ) {
			return $html;
		}

		foreach ( $matches[0] as $i => $tag ) {
			$atts_array = ! empty( $matches[1][ $i ] ) ? $this->ccwp_get_atts_array( $matches[1][ $i ] ) : array();

			if ( isset( $atts_array['rel'] ) && stripos( $atts_array['rel'], 'stylesheet' ) === false ) {
				continue;
			}

			$delay_flag       = false;
			$excluded_scripts = array( 'ccwp-delayed-styles' );

			if(function_exists('wp_is_mobile') &&  !wp_is_mobile()){
				$excluded_scripts[] = 'bootstrap-css'; 
			}

			if ( ! empty( $excluded_scripts ) ) {
				foreach ( $excluded_scripts as $excluded_script ) {
					if ( strpos( $tag, $excluded_script ) !== false ) {
						continue 2;
					}
				}
			}

			$delay_flag = true;
			if ( ! empty( $atts_array['rel'] ) ) {
				$atts_array['data-ccwp-rel'] = $atts_array['rel'];
			}

			$atts_array['rel']   = 'ccwpdelayedstyle';
			$atts_array['defer'] = 'defer';

			if ( $delay_flag ) {
				$delayed_atts_string = $this->ccwp_get_atts_string( $atts_array );
				$delayed_tag         = sprintf( '<link %1$s', $delayed_atts_string ) . ( ! empty( $matches[3][ $i ] ) ? $matches[3][ $i ] : '' ) . '/>';
				$html                = str_replace( $tag, $delayed_tag, $html );
			}
		}

		preg_match_all( '#(<style\s?([^>]+)?\/?>)(.*?)<\/style>#is', $html_no_comments, $matches1 );
		if ( isset( $matches1[0] ) ) {
			foreach ( $matches1[0] as $i => $tag ) {
				$atts_array = ! empty( $matches1[2][ $i ] ) ? $this->ccwp_get_atts_array( $matches1[2][ $i ] ) : array();
				if ( isset( $atts_array['id'] ) && $atts_array['id'] == 'critical-css-for-wp' ) {
					continue;
				}
				if ( isset( $atts_array['type'] ) ) {
					$atts_array['data-ccwp-cc-type'] = $atts_array['type'];
				}
				$delayed_atts_string = $this->ccwp_get_atts_string( $atts_array );
				$delayed_tag         = sprintf( '<style %1$s>', $delayed_atts_string ) . ( ! empty( $matches1[3][ $i ] ) ? $matches1[3][ $i ] : '' ) . '</style>';
				$html                = str_replace( $tag, $delayed_tag, $html );
			}
		}

		if ( $jetpack_boost == true && preg_match( '/<style\s+id="jetpack-boost-critical-css"\s+type="ccwpdelayedstyle">/s', $html ) ) {
			$html = preg_replace( '/<style\s+id="jetpack-boost-critical-css"\s+type="ccwpdelayedstyle">/s', '<style id="jetpack-boost-critical-css">', $html );
		}
		return $html;
	}

	function check_critical_css( $url = '' ) {
		$user_dirname = $this->cachepath();
		if ( ! $url ) {
			global $wp;
			$url = home_url( $wp->request );
		}
		$url = trailingslashit( $url );
		return ccwp_file_exists( $user_dirname . md5( $url ) . '.css' ) ? true : false;
	}

	function ccwp_get_atts_string( $atts_array ) {

		if ( ! empty( $atts_array ) ) {
			$assigned_atts_array = array_map(
				function ( $name, $value ) {
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

	function ccwp_get_atts_array( $atts_string ) {

		if ( ! empty( $atts_string ) ) {
			$atts_array = array_map(
				function ( array $attribute ) {
					return $attribute['value'];
				},
				wp_kses_hair( $atts_string, wp_allowed_protocols() )
			);
			return $atts_array;
		}
		return false;
	}

	public function ccfwp_resend_single_url_for_cache() {

		if ( ! isset( $_POST['ccfwp_security_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( wp_unslash( $_POST['ccfwp_security_nonce'] ) , 'ccfwp_ajax_check_nonce' ) ) { //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reason: using as nonce.
			return;
		}

		global $wpdb, $table_prefix;
		$table_name         = $table_prefix . 'critical_css_for_wp_urls';
		$table_name_escaped = esc_sql( $table_name );

		$url_id = ! empty( $_POST['url_id'] ) ? intval( wp_unslash($_POST['url_id']) ) : null;

		if ( $url_id ) {

			$result = $wpdb->query( //phpcs:ignore -- Reason: Using custom query on non-core tables.
				$wpdb->prepare(
					//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
					"UPDATE {$table_name_escaped} SET `status` = %s, `cached_name` = %s, `failed_error` = %s WHERE `id` = %d",
					'queue',
					'',
					'',
					$url_id
				)
			);

			if ( $result ) {
				echo wp_json_encode( array( 'status' => true ) );
			} else {
				echo wp_json_encode( array( 'status' => false ) );
			}
		} else {
			echo wp_json_encode( array( 'status' => false ) );
		}

		die;
	}

	public function ccfwp_recheck_urls_cache() {

		if ( ! isset( $_POST['ccfwp_security_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( wp_unslash( $_POST['ccfwp_security_nonce'] ) , 'ccfwp_ajax_check_nonce' ) ) { //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reason: using as nonce.
			return;
		}

		$limit  = 100;
		$page   = ! empty( $_POST['page'] ) ? intval( wp_unslash($_POST['page']) ) : 0;
		$offset = $page * $limit;
		global $wpdb, $table_prefix;
		$table_name         = $table_prefix . 'critical_css_for_wp_urls';
		$table_name_escaped = esc_sql( $table_name );

		$result = $wpdb->get_results( //phpcs:ignore -- Reason: Using custom query on non-core tables.
			$wpdb->prepare(
				//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
				"SELECT * FROM {$table_name_escaped} WHERE `status` = %s LIMIT %d, %d",
				'cached',
				$offset,
				$limit
			),
			ARRAY_A
		);

		if ( $result && count( $result ) > 0 ) {
			$user_dirname = $this->cachepath();
			foreach ( $result as $value ) {

				if ( ! ccwp_file_exists( $user_dirname . $value['cached_name'] . '.css' ) ) {
					$updated = $wpdb->query( //phpcs:ignore -- Reason: Using custom query on non-core tables.
						$wpdb->prepare(
							//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
							"UPDATE {$table_name_escaped} SET `status` = %s,  `cached_name` = %s WHERE `url` = %s",
							'queue',
							'',
							$value['url']
						)
					);
				}
			}

			echo wp_json_encode(
				array(
					'status' => true,
					'count'  => count( $result ),
				)
			);
			die;
		} else {
			echo wp_json_encode(
				array(
					'status' => true,
					'count'  => 0,
				)
			);
			die;
		}
	}

	public function ccfwp_resend_urls_for_cache() {

		if ( ! isset( $_POST['ccfwp_security_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( wp_unslash( $_POST['ccfwp_security_nonce'] )  , 'ccfwp_ajax_check_nonce' ) ) { //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reason: using as nonce.
			return;
		}

		global $wpdb, $table_prefix;
		$table_name         = $table_prefix . 'critical_css_for_wp_urls';
		$table_name_escaped = esc_sql( $table_name );

		$result = $wpdb->query( //phpcs:ignore -- Reason: Using custom query on non-core tables.
			$wpdb->prepare(
				//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
				"UPDATE {$table_name_escaped} SET `status` = %s, `cached_name` = %s, `failed_error` = %s WHERE `status` = %s",
				'queue',
				'',
				'',
				'failed'
			)
		);
		if ( $result ) {
			echo wp_json_encode( array( 'status' => true ) );
		} else {
			echo wp_json_encode( array( 'status' => false ) );
		}

		die;
	}

	public function ccfwp_reset_urls_cache() {

		if ( ! isset( $_POST['ccfwp_security_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( wp_unslash( $_POST['ccfwp_security_nonce'] ) , 'ccfwp_ajax_check_nonce' ) ) { //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reason: using as nonce.
			return;
		}

		global $wpdb;
		$table         = $wpdb->prefix . 'critical_css_for_wp_urls';
		$table_escaped = esc_sql( $table );
		//phpcs:ignore -- Reason: $table_escaped is escaped above.
		$result = $wpdb->query( "TRUNCATE TABLE {$table_escaped }" );
		update_option( 'ccfwp_current_post', 0 );
		update_option( 'ccfwp_current_term', 0 );
		$dir = $this->cachepath();
		WP_Filesystem();
		global $wp_filesystem;
		$wp_filesystem->rmdir( $dir, true );

		echo wp_json_encode( array( 'status' => true ) );
		die;
	}

	public function ccfwp_showdetails_data() {

		if ( ! isset( $_GET['ccfwp_security_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( wp_unslash( $_GET['ccfwp_security_nonce'] ) , 'ccfwp_ajax_check_nonce' ) ) { //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reason: using as nonce.
			return;
		}
		$page   = 1;
		$length = isset( $_GET['length'] ) ? intval( $_GET['length'] ) : 10;
		if ( isset( $_GET['start'] ) && $_GET['start'] > 0 ) {
			$page = intval( $_GET['start'] ) / intval( $length );
		}
		$page   = ++$page;
		$offset = isset( $_GET['start'] ) ? intval( $_GET['start'] ) : 0;
		$draw   = isset( $_GET['draw'] ) ? intval( $_GET['draw'] ) : 0;

		global $wpdb, $table_prefix;
		$table_name         = $table_prefix . 'critical_css_for_wp_urls';
		$table_name_escaped = esc_sql( $table_name );

		if ( ! empty( $_GET['search']['value'] ) ) {
			$search      = sanitize_text_field( wp_unslash($_GET['search']['value'] ));
			$total_count = $wpdb->get_var( //phpcs:ignore -- Reason: Using custom query on non-core tables.
				$wpdb->prepare(
					//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
					"SELECT COUNT(*) FROM {$table_name_escaped} WHERE `url` LIKE %s ",
					'%' . $wpdb->esc_like( $search ) . '%'
				),
			);

			$result = $wpdb->get_results( //phpcs:ignore -- Reason: Using custom query on non-core tables.
				$wpdb->prepare(
					//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
					"SELECT * FROM {$table_name_escaped} WHERE `url` LIKE %s LIMIT %d, %d",
					'%' . $wpdb->esc_like( $search ) . '%',
					$offset,
					$length
				),
				ARRAY_A
			);
		} else {
			//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
			$total_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name_escaped}");
			$result      = $wpdb->get_results( //phpcs:ignore -- Reason: Using custom query on non-core tables.
				$wpdb->prepare(
					//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
					"SELECT * FROM {$table_name_escaped} LIMIT %d, %d",
					$offset,
					$length
				),
				ARRAY_A
			);
		}

		$formated_result = array();

		if ( ! empty( $result ) ) {
			$size = '';
			foreach ( $result as $value ) {

				if ( $value['status'] == 'cached' ) {
					$user_dirname = $this->cachepath();
					$size         = ccwp_file_exists( $user_dirname . '/' . md5( trailingslashit( $value['url'] ) ) . '.css' ) ? filesize( $user_dirname . '/' . md5( trailingslashit( $value['url'] ) ) . '.css' ) : '';
					if ( ! $size && 0 != $size) {
						$size = '<abbr title="' . esc_attr__( 'File is not in cached directory. Please recheck in advance option', 'critical-css-for-wp' ) . '">' . esc_html__( 'Deleted', 'critical-css-for-wp' ) . '</abbr>';
					}
				}

				$formated_result[] = array(
					'<div><abbr title="' . esc_attr( $value['cached_name'] ) . '">' . esc_url( $value['url'] ) . '</abbr>' . ( $value['status'] == 'failed' ? '<a href="#" data-section="all" data-id="' . esc_attr( $value['id'] ) . '" class="cwvpb-resend-single-url dashicons dashicons-controls-repeat"></a>' : '' ) . ' </div>',
					'<span class="cwvpb-status-t">' . esc_html( $value['status'] ) . '</span>',
					ccfwp_format_size($size),
					$value['updated_at'],
				);
			}
		}

		$return_data = array(
			'draw'            => $draw,
			'recordsTotal'    => $total_count,
			'recordsFiltered' => $total_count,
			'data'            => $formated_result,
		);

		echo wp_json_encode( $return_data );
		die;
	}

	public function ccfwp_showdetails_data_completed() {

		if ( ! isset( $_GET['ccfwp_security_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( wp_unslash( $_GET['ccfwp_security_nonce'] ) , 'ccfwp_ajax_check_nonce' ) ) { //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reason: using as nonce.
			return;
		}

		$page = 1;

		$length = isset( $_GET['length'] ) ? intval( $_GET['length'] ) : 10;
		if ( isset( $_GET['start'] ) && $_GET['start'] > 0 ) {
			$page = intval( $_GET['start'] ) / intval( $length );
		}
		$page   = ++$page;
		$offset = isset( $_GET['start'] ) ? intval( $_GET['start'] ) : 0;
		$draw   = isset( $_GET['draw'] ) ? intval( $_GET['draw'] ) : 0;

		global $wpdb, $table_prefix;
		$table_name         = $table_prefix . 'critical_css_for_wp_urls';
		$table_name_escaped = esc_sql( $table_name );

		if ( ! empty( $_GET['search']['value'] ) ) {
			$search      = sanitize_text_field( wp_unslash($_GET['search']['value'] ) );
			$total_count = $wpdb->get_var( //phpcs:ignore -- Reason: Using custom query on non-core tables.
				$wpdb->prepare(
					//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
					"SELECT COUNT(*) FROM  {$table_name_escaped} WHERE `url` LIKE %s AND `status`=%s",
					'%' . $wpdb->esc_like( $search ) . '%',
					'cached'
				),
			);

			$result = $wpdb->get_results( //phpcs:ignore -- Reason: Using custom query on non-core tables.
				$wpdb->prepare(
					//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
					"SELECT * FROM {$table_name_escaped} WHERE `url` LIKE %s AND `status`=%s LIMIT %d, %d",
					'%' . $wpdb->esc_like( $search ) . '%',
					'cached',
					$offset,
					$length
				),
				ARRAY_A
			);
		} else {
			//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
			$total_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name_escaped} Where `status`=%s", 'cached' ) );
			$result      = $wpdb->get_results( //phpcs:ignore -- Reason: Using custom query on non-core tables.
				$wpdb->prepare(
					//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
					"SELECT * FROM {$table_name_escaped} Where `status`=%s LIMIT %d, %d",
					'cached',
					$offset,
					$length
				),
				ARRAY_A
			);
		}

		$formated_result = array();

		if ( ! empty( $result ) ) {
			$size = '';
			foreach ( $result as $value ) {

				if ( $value['status'] == 'cached' ) {
					$user_dirname = $this->cachepath();
					if ( ccwp_file_exists( $user_dirname . '/' . md5( $value['url'] ) . '.css' ) ) {
						$size = filesize( $user_dirname . '/' . md5( $value['url'] ) . '.css' );
					} else {
						$size = '<abbr title="' . esc_attr__( 'File is not in cached directory. Please recheck in advance option', 'critical-css-for-wp' ) . '">' . esc_html__( 'Deleted', 'critical-css-for-wp' ) . '</abbr>';
					}
				}

				$formated_result[] = array(
					'<abbr title="' . esc_attr( $value['cached_name'] ) . '">' . esc_url( $value['url'] ) . '</abbr>',
					'<span class="cwvpb-status-t">' . esc_html( $value['status'] ) . '</span>',
					ccfwp_format_size($size),
					$value['updated_at'],
				);
			}
		}

		$return_data = array(
			'draw'            => $draw,
			'recordsTotal'    => $total_count,
			'recordsFiltered' => $total_count,
			'data'            => $formated_result,
		);

		echo wp_json_encode( $return_data );
		die;
	}


	public function ccfwp_showdetails_data_failed() {

		if ( ! isset( $_GET['ccfwp_security_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( wp_unslash( $_GET['ccfwp_security_nonce'] ) , 'ccfwp_ajax_check_nonce' ) ) { //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reason: using as nonce.
			return;
		}

		$page = 1;

		$length = isset( $_GET['length'] ) ? intval( $_GET['length'] ) : 10;
		if ( isset( $_GET['start'] ) && $_GET['start'] > 0 ) {
			$page = intval( $_GET['start'] ) / $length;
		}
		$page   = ++$page;
		$offset = isset( $_GET['start'] ) ? intval( $_GET['start'] ) : 0;
		$draw   = isset( $_GET['draw'] ) ? intval( $_GET['draw'] ) : 0;

		global $wpdb, $table_prefix;
		$table_name         = $table_prefix . 'critical_css_for_wp_urls';
		$table_name_escaped = esc_sql( $table_name );

		if ( isset( $_GET['search']['value'] ) && !empty( $_GET['search']['value']) ) {
			$search      = sanitize_text_field( wp_unslash($_GET['search']['value'] ));
			$total_count = $wpdb->get_var( //phpcs:ignore -- Reason: Using custom query on non-core tables.
				$wpdb->prepare(
					//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
					"SELECT COUNT(*) FROM {$table_name_escaped} WHERE `url` LIKE %s AND `status`=%s",
					'%' . $wpdb->esc_like( $search ) . '%',
					'failed'
				),
			);

			$result = $wpdb->get_results( //phpcs:ignore -- Reason: Using custom query on non-core tables.
				$wpdb->prepare(
					//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
					"SELECT * FROM {$table_name_escaped} WHERE `url` LIKE %s AND `status`=%s LIMIT %d, %d",
					'%' . $wpdb->esc_like( $search ) . '%',
					'failed',
					$offset,
					$length
				),
				ARRAY_A
			);
		} else {
			//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
			$total_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name_escaped} Where `status`=%s", 'failed' ) );
			$result      = $wpdb->get_results( //phpcs:ignore -- Reason: Using custom query on non-core tables.
				$wpdb->prepare(
					//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
					"SELECT * FROM {$table_name_escaped} Where `status`=%s LIMIT %d, %d",
					'failed',
					$offset,
					$length
				),
				ARRAY_A
			);
		}

		$formated_result = array();

		if ( ! empty( $result ) ) {

			foreach ( $result as $value ) {

				if ( $value['status'] == 'cached' ) {
					$user_dirname = $this->cachepath();
					$size         = ccwp_file_exists( $user_dirname . '/' . md5( $value['url'] ) . '.css' ) ? filesize( $user_dirname . '/' . md5( $value['url'] ) . '.css' ) : '';
				}

				$formated_result[] = array(
					'<div>' . esc_url( $value['url'] ) . ' <a href="#" data-section="failed" data-id="' . esc_attr( $value['id'] ) . '" class="cwvpb-resend-single-url dashicons dashicons-controls-repeat"></a></div>',
					'<span class="cwvpb-status-t">' . esc_html( $value['status'] ) . '</span>',
					esc_html( $value['updated_at'] ),
					'<div><a data-id="id-' . esc_attr( $value['id'] ) . '" href="#" class="cwb-copy-urls-error button button-secondary">' . esc_html__( 'Copy Error', 'critical-css-for-wp' ) . '</a><input id="id-' . esc_attr( $value['id'] ) . '" class="cwb-copy-urls-text" type="hidden" value="' . esc_attr( $value['failed_error'] ) . '"></div>',
				);
			}
		}

		$return_data = array(
			'draw'            => $draw,
			'recordsTotal'    => $total_count,
			'recordsFiltered' => $total_count,
			'data'            => $formated_result,
		);

		echo wp_json_encode( $return_data );
		die;
	}
	public function ccfwp_showdetails_data_queue() {

		if ( ! isset( $_GET['ccfwp_security_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( wp_unslash( $_GET['ccfwp_security_nonce'] ), 'ccfwp_ajax_check_nonce' ) ) {  //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reason: using as nonce.
			return;
		}

		$page = 1;
		if ( isset( $_GET['start'] ) && $_GET['start'] > 0 && isset( $_GET['length'] ) && $_GET['length'] > 0 ) {
			$page = intval( $_GET['start'] ) / intval( $_GET['length'] );
		}

		$length = isset( $_GET['length'] ) ? intval( $_GET['length'] ) : 10;
		$page   = ++$page;
		$offset = isset( $_GET['start'] ) ? intval( $_GET['start'] ) : 0;
		$draw   = isset( $_GET['draw'] ) ? intval( $_GET['draw'] ) : 0;

		global $wpdb, $table_prefix;
		$table_name         = $table_prefix . 'critical_css_for_wp_urls';
		$table_name_escaped = esc_sql( $table_name );

		if ( isset( $_GET['search']['value'] ) && !empty( $_GET['search']['value'] ) ) {
			$search      = sanitize_text_field( wp_unslash( $_GET['search']['value'] ) );
			$total_count = $wpdb->get_var( //phpcs:ignore -- Reason: Using custom query on non-core tables.
				$wpdb->prepare(
					//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
					"SELECT COUNT(*) FROM {$table_name_escaped} WHERE `url` LIKE %s AND `status`=%s",
					'%' . $wpdb->esc_like( $search ) . '%',
					'queue'
				),
			);

			$result = $wpdb->get_results( //phpcs:ignore -- Reason: Using custom query on non-core tables.
				$wpdb->prepare(
					//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
					"SELECT * FROM {$table_name_escaped} WHERE `url` LIKE %s AND `status`=%s LIMIT %d, %d",
					'%' . $wpdb->esc_like( $search ) . '%',
					'queue',
					$offset,
					$length
				),
				ARRAY_A
			);
		} else {
			//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
			$total_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name_escaped} Where `status`=%s", 'queue' ) );
			$result      = $wpdb->get_results( //phpcs:ignore -- Reason: Using custom query on non-core tables.
				$wpdb->prepare(
					//phpcs:ignore -- Reason: $table_name_escaped is escaped above.
					"SELECT * FROM {$table_name_escaped} Where `status`=%s LIMIT %d, %d",
					'queue',
					$offset,
					$length
				),
				ARRAY_A
			);
		}

		$formated_result = array();

		if ( ! empty( $result ) ) {

			foreach ( $result as $value ) {
				$size = '';
				if ( 'cached' == $value['status'] ) {
					$user_dirname = $this->cachepath();
					$size         = ccwp_file_exists( $user_dirname . '/' . md5( $value['url'] ) . '.css' ) ? filesize( $user_dirname . '/' . md5( $value['url'] ) . '.css' ) : '';
				}

				$formated_result[] = array(
					$value['url'],
					$value['status'],
					$size,
					$value['updated_at'],
				);
			}
		}

		$return_data = array(
			'draw'            => $draw,
			'recordsTotal'    => $total_count,
			'recordsFiltered' => $total_count,
			'data'            => $formated_result,
		);

		echo wp_json_encode( $return_data );
		die;
	}
	/**
	 * Add admin notices
	 */
	public function ccfwp_add_admin_notices() {

		$user = wp_get_current_user();
		if ( in_array( 'administrator', (array) $user->roles ) ) {
			if ( ! filter_var( ini_get( 'allow_url_fopen' ), FILTER_VALIDATE_BOOLEAN ) ) {
				echo '<div class="notice notice-warning is-dismissible">
				  <p>' . esc_html__( 'Critical CSS For WP needs ' ,'critical-css-for-wp') . '<strong>' . esc_html__( '"allow_url_fopen"' ,'critical-css-for-wp') . '</strong>' . esc_html__( ' option to be enabled in PHP configuration to work.' ,'critical-css-for-wp') . ' </p>
				 </div>';
			}
			if ( $this->ccfwp_wprocket_criticalcss() ) {
				echo '<div class="notice notice-warning is-dismissible">
					  <p>' . esc_html__( 'For' ,'critical-css-for-wp') . ' <strong>' . esc_html__( 'Critical CSS For WP ' ,'critical-css-for-wp') . '</strong>' . esc_html__( ' to function properly ' ,'critical-css-for-wp') . esc_html__( 'disable ' ,'critical-css-for-wp') . '<strong>' . esc_html__( 'Remove Unused CSS option' ,'critical-css-for-wp') . '</strong> ' . esc_html__( 'in' ,'critical-css-for-wp') . ' <strong>' . esc_html__( 'WP Rocket' ,'critical-css-for-wp') . '</strong> </p>
					 </div>';
			}
		}
	}
	/**
	 * Check if WP Rocket is enabled for JS Defer
	 *
	 * @return bool
	 */
	public function ccfwp_check_js_defer() {
		if ( defined( 'WP_ROCKET_VERSION' ) ) {
			$ccwp_wprocket_options = get_option( 'wp_rocket_settings', null );

			if ( isset( $ccwp_wprocket_options['defer_all_js'] ) && 1 == $ccwp_wprocket_options['defer_all_js'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if WP Rocket is enabled for Critical CSS
	 *
	 * @return bool
	 */
	public function ccfwp_wprocket_criticalcss() {
		if ( defined( 'WP_ROCKET_VERSION' ) ) {
			$ccwp_wprocket_options = get_option( 'wp_rocket_settings', null );

			if ( ( isset( $ccwp_wprocket_options['critical_css'] ) && 1 == $ccwp_wprocket_options['critical_css'] ) || ( isset( $ccwp_wprocket_options['remove_unused_css'] ) && 1 == $ccwp_wprocket_options['remove_unused_css'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Clean the geenrated Critical CSS
	 *
	 * @param string $css  The generated Critical CSS.
	 * @return string
	 */
	public function ccfwp_clean_css( $css ) {
		// Remove comments.
		$css = preg_replace( '!/\*.*?\*/!s', '', $css );

		// Remove whitespace around specific characters.
		$css = preg_replace( '/\s*([{}|:;,])\s+/', '$1', $css );
		$css = preg_replace( '/\s\s+(.*)/', '$1', $css );

		// Remove universal selector rules.
		$css = preg_replace( '/\*\s*{[^}]*}/', '', $css );

		// Remove empty rules.
		$css = preg_replace( '/[^{}]+{\s*}/', '', $css );

		$css = preg_replace( '/\s+/', ' ', $css );

		return trim( $css );
	}

	public function ccfwp_get_status_summary() {
		global $wpdb, $table_prefix;
	
		$table_name = $table_prefix . 'critical_css_for_wp_urls';
		$table_name_escaped = esc_sql($table_name);
	
		// Initialize the counters.
		$total_pages = 0;
		$cached = 0;
		$pending = 0;
		$failed = 0;
	
		// Get total pages count.
		$total_pages = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_name_escaped}"
		);
	
		// Get cached count.
		$cached = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name_escaped} WHERE `status` = %s",
				'cached'
			)
		);

		// Get pending count.
		$pending = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name_escaped} WHERE `status` = %s",
				'pending'
			)
		);

		// Get failed count.
		$failed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name_escaped} WHERE `status` = %s",
				'failed'
			)
		);

		return array(
			'total_pages' => $total_pages,
			'cached'      => $cached,
			'pending'     => $pending,
			'failed'      => $failed,
		);
	}
	
}

$ccfwp_general_critical_css = new Critical_Css_For_Wp();
$ccfwp_general_critical_css->critical_hooks();