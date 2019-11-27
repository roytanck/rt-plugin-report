<?php
/**
 * Plugin Name:       Plugin Report
 * Plugin URI:        https://roytanck.com/?p=277
 * Description:       Provides detailed information about currently installed plugins
 * Version:           1.2
 * Requires at least: 4.6
 * Requires PHP:      5.6
 * Author:            Roy Tanck
 * Author URI:        https://roytanck.com
 * License:           GPLv3
 */

// If called without WordPress, exit.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( is_admin() && ! class_exists( 'RT_Plugin_Report' ) ) {

	class RT_Plugin_Report {

		public $cols_per_row          = 6;
		public $cache_lifetime        = DAY_IN_SECONDS;
		public $cache_lifetime_norepo = WEEK_IN_SECONDS;

		/**
		 * Constructor
		 */
		public function __construct() {
			// Intentionally left blank.
		}


		/**
		 * Set up things like hooks and such
		 */
		public function init() {
			// Hook for the admin page.
			if ( is_multisite() ) {
				add_action( 'network_admin_menu', array( $this, 'register_settings_page' ) );
			} else {
				add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
			}
			// Hook for the admin js.
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_js' ) );
			// Add the AJAX hook.
			add_action( 'wp_ajax_rt_get_plugin_info', array( $this, 'get_plugin_info' ) );
		}


		/**
		 * Add a new options page to the network admin
		 */
		public function register_settings_page() {
			add_plugins_page(
				esc_html__( 'Plugin Report', 'plugin-report' ),
				esc_html__( 'Plugin Report', 'plugin-report' ),
				'manage_options',
				'rt_plugin_report',
				array( $this, 'settings_page' )
			);
		}


		/**
		 * Render the options page
		 */
		public function settings_page() {
			// Check user capabilities, just to be sure.
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die();
			}
			// Assemble information we'll need.
			global $wp_version;
			$plugins = get_plugins();

			// Check wether a core update is available.
			$wp_latest = $this->check_core_updates();

			// Refresh the cache, but only if this is a fresh timestamp (not if the page has been refreshed with the timestamp still in the URL).
			if ( isset( $_GET['clear_cache'] ) ) {
				$new_timestamp  = intval( $_GET['clear_cache'] );
				$last_timestamp = intval( get_site_transient( 'rt_plugin_report_cache_cleared' ) );
				if ( ! $last_timestamp || $new_timestamp > $last_timestamp ) {
					$this->clear_cache();
					set_site_transient( 'rt_plugin_report_cache_cleared', $new_timestamp, $this->cache_lifetime );
				}
			}

			// Start the page's output.
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'Plugin Report', 'plugin-report' ) . '</h1>';
			echo '<p>';
			$version_temp = '<span class="' . $this->get_version_risk_classname( $wp_version, $wp_latest ) . '">' . $wp_version . '</span>';
			/* translators: %s = Current WordPress version number */
			echo sprintf( __( 'Currently running WordPress version: %s.', 'plugin-report' ), $version_temp );
			if ( version_compare( $wp_version, $wp_latest, '<' ) ) {
				/* translators: %s = Available new version number */
				echo sprintf( ' (' . esc_html__( 'An upgrade to %s is available', 'plugin-report' ) . ')', $wp_latest );
			}
			echo '</p>';
			echo '<p>';
			// Clear cache and reload.
			if ( is_multisite() ) {
				$page_url = 'network/plugins.php?page=rt_plugin_report';
			} else {
				$page_url = 'plugins.php?page=rt_plugin_report';
			}
			echo '<a href="' . admin_url( $page_url . '&clear_cache=' . current_time( 'timestamp' ) ) . '">' . esc_html__( 'Clear cached plugin data and reload', 'plugin-report' ) . '</a>';
			echo '</p>';
			echo '<h3>' . esc_html__( 'Currently installed plugins', 'plugin-report' ) . '</h3>';
			echo '<p id="rt-plugin-report-progress"></p>';
			echo '<p>';

			// The report's main table.
			echo '<table id="rt-plugin-report-table" class="wp-list-table widefat fixed striped">';
			echo '<thead>';
			echo '<tr>';
			echo '<th>' . esc_html__( 'Name', 'plugin-report' ) . '</th>';
			echo '<th>' . esc_html__( 'Author', 'plugin-report' ) . '</th>';
			echo '<th>' . esc_html__( 'Installed version', 'plugin-report' ) . '</th>';
			echo '<th>' . esc_html__( 'Last update', 'plugin-report' ) . '</th>';
			echo '<th>' . esc_html__( 'Tested up to WP version', 'plugin-report' ) . '</th>';
			echo '<th>' . esc_html__( 'Rating', 'plugin-report' ) . '</th>';
			echo '</tr>';
			echo '</thead>';
			echo '<tbody>';

			foreach ( $plugins as $key => $plugin ) {
				$slug      = $this->get_plugin_slug( $key );
				$cache_key = $this->create_cache_key( $slug );
				$cache     = get_site_transient( $cache_key );
				if ( $cache ) {
					// Use the cached report to create a table row.
					echo $this->render_table_row( $cache );
				} else {
					// Render a special table row that's used as a signal to the front-end js that new data is needed.
					echo '<tr class="rt-plugin-report-row-temp-' . $slug . '"><td colspan="' . $this->cols_per_row . '">loading...</td></tr>';
				}
			}

			echo '</tbody>';
			echo '</table>';

			// Wrap up.
			echo '</p>';
			echo '</div>';
		}


		/**
		 * Enqueue admin javascript
		 */
		public function enqueue_js( $hook ) {
			// Check if we're on the right screen.
			if ( 'plugins_page_rt_plugin_report' != $hook ) {
				return;
			}
			// register the plugin's admin js, and require jquery
			wp_enqueue_script( 'rt-plugin-report-js', plugins_url( '/js/rt-plugin-report.js', __FILE__ ), array( 'jquery' ) );
			// add some variables to the page, to be used by the javascript
			$slugs     = $this->get_plugin_slugs();
			$slugs_str = implode( ',', $slugs );
			$vars      = array(
				'plugin_slugs' => $slugs_str,
				'ajax_nonce'   => wp_create_nonce( 'rt_plugin_report_nonce' ),
			);
			wp_localize_script( 'rt-plugin-report-js', 'rt_plugin_report_vars', $vars );
			// Enqueue admin CSS file.
			wp_enqueue_style( 'rt-plugin-report-css', plugin_dir_url( __FILE__ ) . 'css/rt-plugin-report.css' );
		}


		/**
		 * Get the slugs for all currently installed plugins
		 */
		private function get_plugin_slugs() {
			$plugins = get_plugins();
			$slugs   = array();
			foreach ( $plugins as $key => $plugin ) {
				$slugs[] = $this->get_plugin_slug( $key );
			}
			return $slugs;
		}


		/**
		 * Convert a plugin's file path into its slug
		 */
		private function get_plugin_slug( $file ) {
			if ( strpos( $file, '/' ) !== false ) {
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

			// Check the ajax nonce, display an error if the check fails.
			if ( ! check_ajax_referer( 'rt_plugin_report_nonce', 'nonce', false ) ) {
				wp_die();
			}

			// Check user capabilites, just to be sure.
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die();
			}

			// Check if get_plugins() function exists.
			if ( ! function_exists( 'plugins_api' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			}

			$slug = sanitize_title( $_POST['slug'] );

			$report = $this->assemble_plugin_report( $slug );

			if ( $report ) {
				$table_row = $this->render_table_row( $report );
			} else {
				$table_row = $this->render_error_row( esc_html__( 'No plugin data available.', 'plugin-report' ) );
			}

			// Formulate a response.
			$response = array(
				'html'    => $table_row,
				'message' => 'Success!',
			);
			// Return the response.
			echo json_encode( $response );

			wp_die();
		}


		/**
		 * Gather all the info we can get about a plugin.
		 * Uses transient caching to avoid doing repo API calls on every page visit
		 */
		private function assemble_plugin_report( $slug ) {
			if ( ! empty( $slug ) ) {
				$report    = array();
				$cache_key = $this->create_cache_key( $slug );
				$cache     = get_site_transient( $cache_key );
				$plugins   = get_plugins();

				if ( empty( $cache ) ) {

					// Add the plugin's slug to the report.
					$report['slug'] = $slug;

					// Get the locally available info, and add it  to the report.
					foreach ( $plugins as $key => $plugin ) {
						if ( $this->get_plugin_slug( $key ) == $slug ) {
							$report['local_info'] = $plugin;
							break;
						}
					}

					// Use the wordpress.org repository API to get detailed information.
					$args            = array(
						'slug'   => $slug,
						'fields' => array(
							'description'   => false,
							'sections'      => false,
							'tags'          => false,
							'version'       => true,
							'tested'        => true,
							'requires'      => true,
							'compatibility' => true,
							'author'        => true,
						),
					);
					$returned_object = plugins_api( 'plugin_information', $args );

					// Add the repo info to the report.
					if ( ! is_wp_error( $returned_object ) ) {
						$report['repo_info'] = maybe_unserialize( $returned_object );
						// Cache the report.
						set_site_transient( $cache_key, $report, $this->cache_lifetime );
					} else {
						// Cache for an extra long time when the plgin is not in the repo.
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
		private function render_table_row( $report ) {
			// Get the latest WP release version number.
			$wp_latest = $this->check_core_updates();
			// Check if the report is valid.
			if ( $report == null ) {
				$html = $this->render_error_row( esc_html__( 'No plugin data available.', 'plugin-report' ) );
			} elseif ( ! isset( $report['repo_info'] ) ) {
				/* translators: %s = Slug of the plugin  */
				$html = $this->render_error_row( sprintf( esc_html__( 'The plugin "%s" does not appear to be in the wordpress.org repository.', 'plugin-report' ), $report['local_info']['Name'] ) );
			} else {
				$html = '<tr class="rt-plugin-report-row-' . $report['slug'] . '">';
				// Name.
				$html .= '<td><a href="https://wordpress.org/plugins/' . $report['slug'] . '">' . $report['repo_info']->name . '</a></td>';
				// Author.
				$html .= '<td>' . $report['repo_info']->author . '</td>';
				// Installed / available version.
				$html .= '<td><span class="' . $this->get_version_risk_classname( $report['local_info']['Version'], $report['repo_info']->version ) . '">';
				$html .= $report['local_info']['Version'] . '</span>';
				if ( $report['local_info']['Version'] != $report['repo_info']->version ) {
					$html .= ' (' . $report['repo_info']->version . ' available)';
				}
				$html .= '</td>';
				// Last updates.
				$time_update = new DateTime( $report['repo_info']->last_updated );
				$time_diff   = human_time_diff( $time_update->getTimestamp(), current_time( 'timestamp' ) );
				$css_class   = $this->get_timediff_risk_classname( current_time( 'timestamp' ) - $time_update->getTimestamp() );
				$html       .= '<td class="' . $css_class . '">' . $time_diff . '</td>';
				// Tested up to.
				$html .= '<td class="' . $this->get_version_risk_classname( $report['repo_info']->tested, $wp_latest ) . '">' . $report['repo_info']->tested . '</td>';
				// Overall user rating.
				$css_class = ( intval( $report['repo_info']->num_ratings ) > 0 ) ? $this->get_percentage_risk_classname( intval( $report['repo_info']->rating ) ) : '';
				$html     .= '<td class="' . $css_class . '">' . ( ( intval( $report['repo_info']->num_ratings ) > 0 ) ? $report['repo_info']->rating . '%' : esc_html__( 'No data available', 'plugin-report' ) ) . '</td>';
				$html     .= '</tr>';
			}
			return $html;
		}


		/**
		 * Format an error message as a table row, so we can return it to javascript
		 */
		private function render_error_row( $message ) {
			return '<tr class="rt-pluginreport-row-error"><td colspan="' . $this->cols_per_row . '">' . $message . '</td></tr>';
		}


		/**
		 * Figure out what CSS class to use based on current and optimal version numbers
		 */
		private function get_version_risk_classname( $available, $optimal ) {
			// If the version match, indicate low risk.
			if ( version_compare( $available, $optimal, '==' ) ) {
				return 'rt-risk-low';
			}
			// If major version match, indicate medium risk.
			if ( version_compare( $this->major_release_version_nr( $available ), $this->major_release_version_nr( $optimal ), '==' ) ) {
				return 'rt-risk-medium';
			}
			// Else, indicate high risk.
			return 'rt-risk-high';
		}


		/**
		 * Assess the risk associated with low ratings or poor compatibility feedback, return corresponding CSS class
		 */
		private function get_percentage_risk_classname( $perc ) {
			if ( $perc < 70 ) {
				return 'rt-risk-high';
			}
			if ( $perc < 90 ) {
				return 'rt-risk-medium';
			}
			return 'rt-risk-low';
		}


		/**
		 * Assess the risk associated with low ratings or poor compatibility feedback, return corresponding CSS class
		 */
		private function get_timediff_risk_classname( $time_diff ) {
			$days = $time_diff / ( DAY_IN_SECONDS );
			if ( $days > 365 ) {
				return 'rt-risk-high';
			}
			if ( $days > 90 ) {
				return 'rt-risk-medium';
			}
			return 'rt-risk-low';
		}


		/**
		 * Get the latest available WordPress version using WP core functions
		 * This way, we don't need to do any API calls. WP check this periodically anyway.
		 */
		private function check_core_updates() {
			global $wp_version;
			$update = get_preferred_from_update_core();
			// Bail out of no valid response, or false.
			if ( ! $update || $update == false ) {
				return $wp_version;
			}
			// If latest, return current version number.
			if ( $update->response == 'latest' ) {
				return $wp_version;
			}
			// Return the preferred update's version number.
			return $update->version;
		}


		/**
		 * Extract the major release number from a WP version nr
		 */
		private function major_release_version_nr( $version ) {
			$parts = explode( '.', $version );
			$parts = array_slice( $parts, 0, 2 );
			return implode( '.', $parts );
		}


		/**
		 * Create a cache key that is unique to the provided plugin slug.
		 */
		private function create_cache_key( $slug ) {
			// Create a hash for the plugiin slug.
			$slug_hash = hash( 'sha256', $slug );
			// Prefix and limit the string to 40 characters to avoid issues with long keys.
			$cache_key = 'rtpr_' . substr( $slug_hash, 0, 35 );
			// Return the key.
			return $cache_key;
		}


		/**
		 * Clear all cached plugin info
		 */
		private function clear_cache() {
			$plugins = get_plugins();
			foreach ( $plugins as $key => $plugin ) {
				$slug      = $this->get_plugin_slug( $key );
				$cache_key = $this->create_cache_key( $slug );
				delete_site_transient( $cache_key );
			}
		}

	}

	// Instantiate the class.
	$rt_plugin_report_instance = new RT_Plugin_Report();
	$rt_plugin_report_instance->init();

}
