<?php
/**
 * Plugin Name:       Plugin Report
 * Plugin URI:        https://roytanck.com
 * Description:       Provides detailed information about currently installed plugins
 * Version:           1.1
 * Requires at least: 5.0
 * Requires PHP:      5.6
 * Author:            Roy Tanck
 * Text Domain:       plugin-report
 * Domain Path:       /languages
 * License:           GPLv3
 */

// if called without WordPress, exit
if( !defined('ABSPATH') ) { exit; }


if( is_admin() && !class_exists( 'RT_Plugin_Report' ) ) {

	class RT_Plugin_Report {

		public $cols_per_row = 7;
		public $cache_lifetime = DAY_IN_SECONDS;
		public $cache_lifetime_norepo = WEEK_IN_SECONDS;

		/**
		 * Constructor
		 */
		function __construct() {
			// intentionally left blank
		}


		/**
		 * Set up things like hooks and such
		 */
		public function init() {
			// load the plugin's text domain			
			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
			// hook for the admin page
			if( is_multisite() ) {
				add_action( 'network_admin_menu', array( $this, 'register_settings_page' ) );
			} else {
				add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
			}
			// hook for the admin js
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_js' ) );
			// add the AJAX hook
			add_action( 'wp_ajax_rt_get_plugin_info', array( $this, 'get_plugin_info' ) );
		}


		/**
		 * Load the translated strings
		 */
		public function load_textdomain() {
			load_plugin_textdomain( 'plugin-report', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}


		/**
		 * Add a new options page to the network admin
		 */
		public function register_settings_page() {
			add_plugins_page(
				__( 'Plugin Report', 'plugin-report' ),
				__( 'Plugin Report', 'plugin-report' ),
				'manage_options',
				'rt_plugin_report',
				array( $this, 'settings_page' )
			);
		}


		/**
		 * Render the options page
		 */
		public function settings_page() {
			// check user capabilites, just to be sure
			if( ! current_user_can('manage_options') ) {
				die();
			}
			// assemble information we'll need
			global $wp_version;
			$plugins = get_plugins();
			
			// check wether a core update is available
			$wp_latest = $this->check_core_updates();

			// refresh the cache, but only if this is a fresh timestamp (not if the page has been refreshed with the timestamp still in the URL)
			if( isset( $_GET['clear_cache'] ) ) {
				$new_timestamp = intval( $_GET['clear_cache'] );
				$last_timestamp = intval( get_site_transient( 'rt_plugin_report_cache_cleared' ) );
				if( ! $last_timestamp || $new_timestamp > $last_timestamp ) {
					$this->clear_cache();
					set_site_transient( 'rt_plugin_report_cache_cleared', $new_timestamp, $this->cache_lifetime );	
				}
			}

			// start the page's output
			echo '<div class="wrap">';
			echo '<h1>' . __( 'Plugin Report', 'plugin-report' ) . '</h1>';
			echo '<p>';
			$version_temp = '<span class="' . $this->get_version_risk_classname( $wp_version, $wp_latest ) . '">' . $wp_version . '</span>';
			echo sprintf( __( 'Currently running WordPress version: %s.', 'plugin-report' ), $version_temp );
			if( version_compare( $wp_version, $wp_latest, '<' ) ) {
				echo sprintf( ' (' . __( 'An upgrade to %s is available', 'plugin-report' ) . ')', $wp_latest );
			}
			echo '</p>';
			echo '<p>';
			// clear cache and reload
			if( is_multisite() ) {
				$page_url = 'network/plugins.php?page=rt_plugin_report';
			} else {
				$page_url = 'plugins.php?page=rt_plugin_report';
			}
			echo '<a href="' . admin_url( $page_url . '&clear_cache=' . current_time('timestamp') ) . '">' . __( 'Clear cached plugin data and reload', 'plugin-report' ) . '</a>';
			echo '</p>';
			echo '<h3>' . __( 'Currently installed plugins', 'plugin-report' ) . '</h3>';
			echo '<p id="rt-plugin-report-progress"></p>';
			echo '<p>';

			// the report's main table
			echo '<table id="rt-plugin-report-table" class="wp-list-table widefat fixed striped">';
			echo '<tr>';
			echo '<th>'. __( 'Name', 'plugin-report' ) . '</th>';
			echo '<th>'. __( 'Author', 'plugin-report' ) . '</th>';
			echo '<th>'. __( 'Installed version', 'plugin-report' ) . '</th>';
			echo '<th>'. __( 'Last update', 'plugin-report' ) . '</th>';
			echo '<th>'. __( 'Tested', 'plugin-report' ) . '</th>';
			echo '<th>'. __( 'Compatibility reports', 'plugin-report' ) . '</th>';
			echo '<th>'. __( 'Rating', 'plugin-report' ) . '</th>';
			echo '</tr>';

			foreach( $plugins as $key => $plugin ) {
				$slug = $this->get_plugin_slug( $key );
				$cache_key = 'rt_plugin_report_cache_' . $slug;
				$cache = get_site_transient( $cache_key );
				if( $cache ) {
					// use the cached report to create a table row
					echo $this->render_table_row( $cache );
				} else {
					// render a special table row that's used as a signal to the front-end js that new data is needed
					echo '<tr class="rt-plugin-report-row-temp-' . $slug . '"><td colspan="' . $this->cols_per_row . '">loading...</td></tr>';
				}
			}

			echo '</table>';

			echo '<div id="rt-debug"></div>';

			// wrap up
			echo '</p>';
			echo '</div>';
		}


		/**
		 * Enqueue admin javascript
		 */
		public function enqueue_js( $hook ) {
			// check if we're on the right screen
			if ( 'plugins_page_rt_plugin_report' != $hook ) {
				return;
			}
			// register the plugin's admin js, and require jquery
			wp_enqueue_script( 'rt-plugin-report-js', plugins_url( '/js/rt-plugin-report.js' , __FILE__ ), array( 'jquery' ) );
			// add some variables to the page, to be used by the javascript
			$slugs = $this->get_plugin_slugs();
			$slugs_str = implode( ',', $slugs );
			$vars = array(
				'plugin_slugs' => $slugs_str,
				'ajax_nonce' => wp_create_nonce('rt_plugin_report_nonce'),
			);
			wp_localize_script( 'rt-plugin-report-js', 'rt_plugin_report_vars', $vars );
			// enqueue admin CSS file
			wp_enqueue_style( 'rt-plugin-report-css', plugin_dir_url( __FILE__ ) . 'css/rt-plugin-report.css' );
		}


		/**
		 * Get the slugs for all currently installed plugins
		 */
		public function get_plugin_slugs() {
			$plugins = get_plugins();
			$slugs = array();
			foreach( $plugins as $key => $plugin ) {
				$slugs[] = $this->get_plugin_slug( $key );
			}
			return $slugs;
		}


		/**
		 * Convert a plugin's file path into its slug
		 */
		public function get_plugin_slug( $file ) {
			if( strpos( $file, '/' ) !== false ) {
				$parts = explode( '/', $file );
			} else {
				$parts = explode( '.', $file );	
			}
			return sanitize_title( $parts[0] );
		}


		/**
		 * AJAX handler
		 * Returns a full html table row with the plugin's data
		 */
		public function get_plugin_info() {

			// check the ajax nonce, display an error if the check fails
			if( ! check_ajax_referer( 'rt_plugin_report_nonce', 'nonce', false ) ) {
				echo 'oops';
				die();
			}

			// check user capabilites, just to be sure
			if( ! current_user_can('manage_options') ) {
				die();
			}

			// Check if get_plugins() function exists.
			if ( ! function_exists( 'plugins_api' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			}

			$slug = sanitize_title( $_POST['slug'] );

			$report = $this->assemble_plugin_report( $slug );

			if( $report ) {
				$table_row = $this->render_table_row( $report );
			} else {
				$table_row = $this->render_error_row( __( 'No plugin data available.', 'plugin-report' ) );
			}

			// formulate a response
			$response = array(
				'html' => $table_row,
				'message' => 'Success!',
			);
			// return te response
			echo json_encode( $response );

			wp_die();
		}


		/**
		 * Gather all the info we can get about a plugin.
		 * Uses transient caching to avoid doing repo API calls on every page visit
		 */
		public function assemble_plugin_report( $slug ) {
			if( ! empty( $slug ) ) {
				$report = array();
				$cache_key = 'rt_plugin_report_cache_' . $slug;
				$cache = get_site_transient( $cache_key );
				$plugins = get_plugins();

				if( empty( $cache ) ) {

					// add the plugin's slug to the report
					$report['slug'] = $slug;

					// get the locally available info, and add it  to the report
					foreach( $plugins as $key=>$plugin ) {
						if( $this->get_plugin_slug( $key ) == $slug ) {
							$report['local_info'] = $plugin;
							break;
						}
					}
					
					// Use the wordpress.org repository API to get detailed information
					$args = array(
						'slug' => $slug,
						'fields' => array(
							'description' => false,
							'sections' => false,
							'tags' => false,
							'version' => true,
							'tested' => true,
							'requires' => true,
							'compatibility' => true,
							'author' => true,
						),
					);
					$returned_object = plugins_api( 'plugin_information', $args );

					// add the repo info to the report
					if( ! is_wp_error( $returned_object ) ) {
						$report['repo_info'] = maybe_unserialize( $returned_object );
						// cache the report
						set_site_transient( $cache_key, $report, $this->cache_lifetime );
					} else {
						// cache for an extra long time when the plgin is not in the repo
						set_site_transient( $cache_key, $report, $this->cache_lifetime_norepo );
					}

				} else {
					$report = $cache;
				}

				return $report;
				
			} else {
				return null;
			}

		}


		/**
		 * From a report, generate an HTML table row with relevant data for the plugin
		 */
		public function render_table_row( $report ) {
			// get the latest WP release version number
			$wp_latest = $this->check_core_updates();
			// check if the report is valid
			if( $report == null ) {
				$html = $this->render_error_row( __( 'No plugin data available.', 'plugin-report' ) );
			} elseif( !isset( $report['repo_info'] ) ) {
				$html = $this->render_error_row( sprintf( __( 'This plugin "%s" does not appear to be in the wordpress.org repository.', 'plugin-report'), $report['slug'] ) );
			} else {
				$html = '<tr class="rt-plugin-report-row-' . $report['slug'] . '">';
				// name
				$html .= '<td><a href="https://wordpress.org/plugins/' . $report['slug'] . '">' . $report['repo_info']->name . '</a></td>';
				// author
				$html .= '<td>' . $report['repo_info']->author . '</td>';
				// installed / available version
				$html .= '<td><span class="' . $this->get_version_risk_classname( $report['local_info']['Version'], $report['repo_info']->version ) .  '">';
				$html .= $report['local_info']['Version'] . '</span>';
				if( $report['local_info']['Version'] != $report['repo_info']->version ) {
					$html .= ' (' . $report['repo_info']->version . ' available)';
				}
				$html .= '</td>';
				// last updates
				$time_update = new DateTime( $report['repo_info']->last_updated );
				$time_diff = human_time_diff( $time_update->getTimestamp(), current_time('timestamp') );
				$css_class = $this->get_timediff_risk_classname( current_time('timestamp') - $time_update->getTimestamp() );
				$html .= '<td class="' . $css_class . '">' . $time_diff . '</td>';
				// tested up to
				$html .= '<td class="' . $this->get_version_risk_classname( $report['repo_info']->tested, $wp_latest ) .  '">' . $report['repo_info']->tested . '</td>';
				// user-reported compatibility info
				$html .= '<td>' . $this->format_compatibility( $report['repo_info']->compatibility ) . '</td>';
				// overall user rating
				$css_class = ( intval( $report['repo_info']->num_ratings ) > 0 ) ? $this->get_percentage_risk_classname( intval( $report['repo_info']->rating ) ) : '';
				$html .= '<td class="' . $css_class . '">' . ( ( intval( $report['repo_info']->num_ratings ) > 0 ) ? $report['repo_info']->rating . '%' : __( 'No data available', 'plugin-report' ) ) . '</td>';
				$html .= '</tr>';
			}
			return $html;
		}


		/**
		 * Format an error message as a table row, so we can return it to javascript
		 */
		public function render_error_row( $message ) {
			return '<tr class="rt-pluginreport-row-error"><td colspan="' . $this->cols_per_row . '">' . $message . '</td></tr>';
		}


		/**
		 * Figure out what CSS class to use based on current and optimal version numbers
		 */
		public function get_version_risk_classname( $available, $optimal ) {
			// if the version match, indicate low risk
			if( version_compare( $available, $this->major_release_version_nr($optimal), '<=' ) == 0 ) {
				return 'rt-risk-low';
			}
			// if major version match, indicate medium risk
			if( version_compare( $this->major_release_version_nr( $available ), $this->major_release_version_nr( $optimal ) ) == 0 ) {
				return 'rt-risk-medium';
			}
			// else, indicate high risk
			return 'rt-risk-high';
		}


		/**
		 * Assess the risk associated with low ratings or poor compatibility feedback, return corresponding CSS class
		 */
		public function get_percentage_risk_classname( $perc ) {
			if( $perc < 70 ) {
				return 'rt-risk-high';
			}
			if( $perc < 90 ) {
				return 'rt-risk-medium';
			}
			return 'rt-risk-low';
		}


		/**
		 * Assess the risk associated with low ratings or poor compatibility feedback, return corresponding CSS class
		 */
		public function get_timediff_risk_classname( $time_diff ) {
			$days = $time_diff / ( DAY_IN_SECONDS );
			if( $days > 365 ) {
				return 'rt-risk-high';
			}
			if( $days > 90 ) {
				return 'rt-risk-medium';
			}
			return 'rt-risk-low';
		}
		

		/**
		 * Get the latest available WordPress version using WP core functions
		 * This way, we don't need to do any API calls. WP check this periodically anyway.
		 */
		public function check_core_updates() {
			global $wp_version;
			$update = get_preferred_from_update_core();
			// bail out of no valid response, or false
			if( !$update || $update == false ) {
				return $wp_version;
			}
			// if latest, return current version number
			if( $update->response == 'latest' ) {
				return $wp_version;	
			}
			// return the preferred update's version number
			return $update->version;
		}


		/**
		 * Interpret the 'compatibility' info from the repo, and return a summary string
		 */
		public function format_compatibility( $compatibility ) {
			if( empty( $compatibility ) || !is_array( $compatibility ) ) {
				return __( 'No data available', 'plugin-report' );
			}
			// get the latest WP release version number
			$wp_latest = $this->check_core_updates();

			$out = '';
			$score = array();
			foreach( $compatibility as $pluginversion=>$data ) {
				$out .= $pluginversion . ': ' . $data[0] . '% (' . $data[2] . '/' . $data[1] . ')';
				$score[] = intval( $data[0] );
			}
			$css_class = $this->get_percentage_risk_classname( $score[0] );
			return '<span class="' . $css_class . '">' . $out . '</span>';

		}


		/**
		 * Extract the major release number from a WP version nr
		 */
		public function major_release_version_nr( $version ) {
			$parts = explode( '.', $version );
			$parts = array_slice( $parts, 0, 2 );
			return implode( '.', $parts );
		}


		/**
		 * Clear all cached plugin info
		 */
		public function clear_cache() {
			$plugins = get_plugins();
			foreach( $plugins as $key=>$plugin ) {
				$slug = $this->get_plugin_slug( $key );
				$cache_key = 'rt_plugin_report_cache_' . $slug;
				delete_site_transient( $cache_key );
			}
		}

	}

	// instantiate the class
	$rt_plugin_report_instance = new RT_Plugin_Report();
	$rt_plugin_report_instance->init();
		
}

?>
