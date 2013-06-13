<?php
/**
 * Plugin Name: WP Random Password Generator
 * Plugin URI: http://fearnothingproductions.net/wp-random-password-generator
 * Description: Generates a random password when creating a new WP user using the Random.org API
 * Version: 1.0
 * Author: Robert Paprocki
 * Author URI: http://fearnothingproductions.net
 * License: GPL2
 *
 * @package WordPress
 * @subpackage WP Random Password Generator
 * @author Robert Paprocki
 */

define( 'WP_RANDOM_PASSWORD_GENERATOR_VERSION', '1.0' );

/**
 * Store plugin settings the wp_options table (key = 'wp-random-password-generator-opts')
 *
 * @return void
 * @uses get_option()
 * @uses update_option()
 * @since 1.0
 */
function wp_random_password_generator_install() {
	$default_opts = array(
		'version'     => WP_RANDOM_PASSWORD_GENERATOR_VERSION,
		'random-api'  => true, // use the random.org API by default
		'min-length'  => 10,
		'max-length'  => 20,
		'debug'       => true, // console.log( json object )
	);

	$current_opts = get_option( 'wp-random-password-generator-opts' );
	if ( $current_opts ) {
		// don't override any valid existing options
		if ( isset ( $current_opts['random-api'] ) && is_bool( $current_opts['random-api'] ) ) {
			$default_opts['random-api'] = $current_opts['random-api'];
		}
		if ( isset( $current_opts['min-length'] ) && intval( $current_opts['min-length'] ) > 0 ) {
			$default_opts['min-length'] = intval( $current_opts['min-length'] );
		}
		if ( isset( $current_opts['max-length'] ) && intval( $current_opts['max-length'] ) >= $default_opts['min-length'] ) {
			$default_opts['max-length'] = intval( $current_opts['max-length'] );
		}
		if ( isset ( $current_opts['debug'] ) && is_bool( $current_opts['debug'] ) ) {
			$default_opts['debug'] = $current_opts['debug'];
		}
		/*
		  We've checked what we need to. If there are other items in $stored, let them stay ($default_opts won't overwrite them)
		  as some dev has probably spent some time adding custom functionality to the plugin.
		*/
		$default_opts = array_merge( $current_opts, $default_opts );
	}
	update_option( 'wp-random-password-generator-opts', $default_opts );
}

/**
 * Remove the plugin options from the wp_options table
 *
 * @return void
 * @uses delete_option()
 * @since 1.0
 */
function wp_random_password_generator_uninstall() {
	delete_option('wp-random-password-generator-opts');
}

/**
 * Instantiate the plugin/enqueue wp-random-password-generator.js
 *
 * @return void
 * @uses get_current_screen()
 * @uses plugins_url()
 * @uses wp_enqueue_script()
 * @uses get_bloginfo()
 * @uses wp_localize_script()
 * @since 1.0
 */
function wp_random_password_generator_load() {
	$current_screen = get_current_screen();
	if ( in_array( $current_screen->base, array( 'profile', 'user', 'user-edit' ) ) ) {
		wp_enqueue_script( 'wp-random-password-generator', plugins_url( 'wp-random-password-generator.js', __FILE__ ), array( 'jquery' ), WP_RANDOM_PASSWORD_GENERATOR_VERSION, true );
		$jsVars = array(
			'plugin_dir'  => get_bloginfo('url') . '/wp-content/plugins/wp-random-password-generator',
		);
		wp_localize_script( 'wp-random-password-generator', 'wpRandomPasswordGenerator', $jsVars );
	}
}

/**
 * Handle an Ajax request for a password, print response.
 * Uses either the Random.org API or wp_generate_password(), a pluggable function within the WordPress core
 *
 * @return void (echoes password)
 * @uses get_option()
 * @uses wp_generate_password()
 * @uses wp_password_generator_install()
 * @since 1.0
 */
function wp_random_password_generator_generate() {
	$opts = get_option( 'wp-random-password-generator-opts', false );
	if ( ! $opts || $opts['version'] < WP_RANDOM_PASSWORD_GENERATOR_VERSION ) { // No options or an older version
		wp_random_password_generator_install();
		$opts = get_option( 'wp-random-password-generator-opts', false );
	}

	$len = mt_rand( $opts['min-length'], $opts['max-length'] ); // Min/max password lengths

	// setup the json-encoded response
	$response = array();
	$response['debug'] = $opts['debug'];
	$response['time']['begin'] = time();
	$response['length'] = $len;
	$response['api']['db'] = $opts['random-api'] ? 'random.org' : 'wp_generate_password';

	if ( file_exists( plugin_dir_path( __FILE__ ) . 'inc/class-rand-dot-org.php' ) ) {
		require( plugin_dir_path( __FILE__ ) . 'inc/class-rand-dot-org.php' );
		$rand = new RandDotOrg( true, 'WPRandomPasswordGenerator', 1000 ); // use SSL, set the User Agent, and make sure we have 1000 bits of API quota
	}

	// check if we should use the Random.org API
	if ( true == $opts['random-api'] && isset( $rand ) && $rand->quota() > $rand->get_quota_limit() ) {
		try {
			if ( $opts['debug'] ) {
				$quota_before = $rand->quota();
				$response['api']['used'] = 'random.org';
			}
			$response['status'] = 0;
			$response['result'] = $rand->get_strings( 1, $len );
			if ( $opts['debug'] ) {
				$response['bits'] = $quota_before - $rand->quota();
			}
		} catch ( Exception $e ) {
			$response['status'] = -1;
			$response['result'] = $e->getMessage();
		}
	} else { // use the built-in pluggable wp_generate_password function instead
		if ( $opts['debug'] ) {
			$response['api']['used'] = 'wp_generate_password';
		}
		$args = array(
			'length' => $len,
			'special_chars' => true,
			'extra_special_chars' => false,
		);
		$response['status'] = 0; // assume everything went well
		$response['result'] = call_user_func_array( 'wp_generate_password', apply_filters( 'wp_password_generator_args', $args, $opts ) );
	}

	print json_encode( $response );
	die(); // die so we can get rid of the extra '0' from admin-ajax.php. see http://bit.ly/12vmWvB
}

/**
 * Handle the creation of an admin menu.
 *
 * @return void
 * @since 1.0
 */
function options_page() {
	add_options_page( 'WPRPG', 'WPRPG', 'manage_options', 'WPRPG', 'options_page_display' );
}

/**
 * Displays the plugin options page.
 *
 * @return void
 * @since 1.0
 */
function options_page_display() {
	include( plugin_dir_path( __FILE__ ) . 'wp-random-password-generator-options.php' );
}

add_action( 'admin_print_scripts', 'wp_random_password_generator_load' ); // run wp_password_generator_load() during admin_print_scripts
add_action( 'wp_ajax_generate_password', 'wp_random_password_generator_generate' ); // Ajax hook
add_action( 'admin_menu', 'options_page' );
register_activation_hook( __FILE__, 'wp_random_password_generator_install' );
register_deactivation_hook( __FILE__, 'wp_random_password_generator_uninstall' );
