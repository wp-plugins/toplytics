<?php
/**
 * Plugin Name: Toplytics
 * Plugin URI: http://wordpress.org/extend/plugins/toplytics/
 * Description: Plugin for displaying most viewed content using data from a Google Analytics account. Relieves the DB from writing every click.
 * Author: PressLabs
 * Version: 2.1
 * Author URI: http://www.presslabs.com/
 */
define( 'TOPLYTICS_DEBUG_MODE', true );
define( 'TOPLYTICS_DEFAULT_POSTS', 5 );
define( 'TOPLYTICS_MIN_POSTS', 1 );
define( 'TOPLYTICS_MAX_POSTS', 20 );
define( 'TOPLYTICS_GET_MAX_RESULTS', 1000 );
define( 'TOPLYTICS_ADD_PAGEVIEWS', true );
define( 'TOPLYTICS_TEXTDOMAIN', 'toplytics-text-domain' );
define( 'TOPLYTICS_TEMPLATE_FILENAME', 'toplytics-template.php' );
define( 'TOPLYTICS_REALTIME_TEMPLATE_FILENAME', 'toplytics-template-realtime.php' );

$toplytics_periods = array(
	'month' => array(
		'label' => 'Monthly',
		'range' => date( 'Y-m-d', strtotime( '-30 days' ) ),
	),
	'today' => array(
		'label' => 'Daily',
		'range' => date( 'Y-m-d', strtotime( 'yesterday' ) ),
	),
	'2weeks' => array(
		'label' => '2 Weeks',
		'range' => date( 'Y-m-d', strtotime( '-14 days' ) ),
	),
	'week' => array(
		'label' => 'Weekly',
		'range' => date( 'Y-m-d', strtotime( '-7 days' ) ),
	)
);
$periods = apply_filters( 'toplytics_periods', $toplytics_periods );

global $ranges, $ranges_label;

foreach ( $periods as $index => $data ) {
	$ranges[ $index ]       = $data['range'];
	$ranges_label[ $index ] = $data['label'];
}

require_once 'toplytics-admin.php';            // interface
require_once 'toplytics-widget.php';           // Widget code integration
require_once 'class-toplytics-auth.php';       // the login logic
require_once 'class-toplytics-statistics.php'; // the statistics logic
$obj = new Toplytics_Auth();

if ( defined( 'TOPLYTICS_DEBUG_MODE' ) && TOPLYTICS_DEBUG_MODE ) {
	require_once 'toplytics-debug.php'; // debug page
}

function toplytics_log( $message ) {
	if ( defined( TOPLYTICS_DEBUG_MODE  )  ) {
		error_log( $message );
	}
}

function toplytics_activate() {
	add_option( 'toplytics_options', array( null ) );
	add_option( 'toplytics_services', 'analytics' );
}
register_activation_hook( __FILE__, 'toplytics_activate' );

function toplytics_deactivate() {
	wp_clear_scheduled_hook( 'toplytics_hourly_event' );
}
register_deactivation_hook( __FILE__, 'toplytics_deactivate' );

function toplytics_uninstall() {
	toplytics_remove_options();
}
add_action( 'uninstall_' . toplytics_plugin_basename(), 'toplytics_uninstall' );

function toplytics_init() {
	load_plugin_textdomain( TOPLYTICS_TEXTDOMAIN, false, dirname( toplytics_plugin_basename() ) . '/languages' );
}
add_action( 'plugins_loaded', 'toplytics_init' );

/**
 *  Return the template filename and path. First is searched in the theme directory and then in the plugin directory
 */
function toplytics_get_template_filename( $realtime = 0 ) {
	$toplytics_template_filename = TOPLYTICS_TEMPLATE_FILENAME;
	if ( 1 == $realtime ) {
		$toplytics_template_filename = TOPLYTICS_REALTIME_TEMPLATE_FILENAME;
	}

	$theme_template = get_stylesheet_directory() . "/$toplytics_template_filename";
	if ( file_exists( $theme_template ) ) {
		return $theme_template;
	}

	$plugin_template = plugin_dir_path( __FILE__ ) . $toplytics_template_filename;
	if ( file_exists( $plugin_template ) ) {
		return $plugin_template;
	}

	return '';
}

function toplytics_needs_configuration() {
	$toplytics_oauth_token = get_option( 'toplytics_oauth_token', '' );
	return empty( $toplytics_oauth_token );
}

function toplytics_has_configuration() {
	return ! toplytics_needs_configuration();
}

/**
 *  Add cron job if all options are set
 *  Scan Google Analytics statistics every hour
 */
if ( toplytics_has_configuration() ) {
	if ( ! wp_next_scheduled( 'toplytics_hourly_event' ) ) {
		wp_schedule_event( time(), 'hourly', 'toplytics_hourly_event' );
	}
} else {
	wp_clear_scheduled_hook( 'toplytics_hourly_event' );
}

function toplytics_do_this_hourly() {
	do_action( 'toplytics_do_this_hourly' );
	Toplytics_Statistics::get_results(); // get GA statistics
	toplytics_save_stats_in_json(); // save GA statistics in JSON file
}
add_action( 'toplytics_hourly_event', 'toplytics_do_this_hourly' );

function toplytics_remove_credentials() {
	delete_option( 'toplytics_oauth_token' );
	delete_option( 'toplytics_oauth_secret' );
	delete_option( 'toplytics_auth_token' );
	delete_option( 'toplytics_account_id' );
	delete_option( 'toplytics_cache_timeout' );
}

function toplytics_remove_options() {
	delete_option( 'toplytics_options' );
	delete_option( 'toplytics_services' );
	delete_transient( 'toplytics.cache' );
}

function toplytics_widgets_init() {
	if ( toplytics_has_configuration() ) {
		register_widget( 'Toplytics_WP_Widget_Most_Visited_Posts' );
	}
}
add_action( 'widgets_init', 'toplytics_widgets_init' );

function toplytics_enqueue_script() {
	wp_enqueue_script( 'toplytics', plugins_url( 'js/toplytics.js' , __FILE__ ) );
}
add_action( 'wp_enqueue_scripts', 'toplytics_enqueue_script' );

function toplytics_get_results( $args = '' ) {
	$args = toplytics_validate_args( $args );

	$results = get_transient( 'toplytics.cache' );
	if ( ! isset( $results[ $args['period'] ] ) ) {
		return false;
	}

	$counter = 1;
	foreach ( $results[ $args['period'] ] as $index => $value ) {
		if ( $counter > $args['numberposts'] ) { break; }
		$toplytics_new_results[ $index ] = $value;
		$counter++;
	}
	return $toplytics_new_results;
}

function toplytics_results( $args = '' ) {
	$args    = toplytics_validate_args( $args );
	$results = toplytics_get_results( $args );
	if ( ! $results ) { return ''; }

	$out = '<ol>';
	foreach ( $results as $post_id => $post_views ) {
		$out .= '<li><a href="' . get_permalink( $post_id )
			. '" title="' . esc_attr( get_the_title( $post_id ) ) . '">'
			. get_the_title( $post_id ) . '</a>';

		if ( $args['showviews'] ) {
			$out .= '<span class="post-views">'
				. sprintf( __( '%d Views', TOPLYTICS_TEXTDOMAIN ), $post_views )
				. '</span>';
		}
		$out .= '</li>';
	}
	$out .= '</ol>';

	return $out;
}
add_shortcode( 'toplytics', 'toplytics_results' );

function toplytics_save_stats_in_json() {
	$filename = 'toplytics.json';
	$filepath = dirname( __FILE__ ) . "/$filename";
	$toplytics_results = get_transient( 'toplytics.cache' );
	if ( false != $toplytics_results ) {
		// post data: id, permalink, title, views
		$post_data = '';
		foreach ( $toplytics_results as $period => $result ) {
			if ( '_ts' != $period ) {
				foreach ( $result as $post_id => $views ) {
					$data['permalink'] = get_permalink( $post_id );
					$data['title']     = get_the_title( $post_id );
					$data['post_id']   = $post_id;
					$data['views']     = $views;

					$post_data[ $period ][] = $data;
				}
			}
		}
		$data = apply_filters( 'toplytics_json_data', $post_data, $filepath );
		file_put_contents( $filepath, json_encode( $data, JSON_FORCE_OBJECT ) );
	}
}
