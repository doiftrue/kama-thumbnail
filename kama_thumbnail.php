<?php
/**
 * Plugin Name: Kama Thumbnail
 *
 * Description: Creates post thumbnails on fly and cache it. The Image is taken from: post thumbnail OR first img in post content OR first post attachment. To show IMG use <code>kama_thumb_a_img()</code>, <code>kama_thumb_img()</code>, <code>kama_thumb_src()</code> functions in theme/plugin.
 *
 * Text Domain: kama-thumbnail
 * Domain Path: languages
 *
 * Author: Kama
 * Plugin URI: https://wp-kama.ru/142
 * Author URI: https://wp-kama.ru/
 *
 * Requires PHP: 7.2
 * Requires at least: 4.7
 *
 * Version: 3.4.2
 */

$ktdata = (object) get_file_data( __FILE__, [
	'ver'       => 'Version',
	'req_php'   => 'Requires PHP',
	'plug_name' => 'Plugin Name',
] );

// check is php compatible
if( ! version_compare( phpversion(), $ktdata->req_php, '>=' ) ){

	$message = sprintf( '%s requires PHP %s+, but current one is %s.',
		$ktdata->plug_name,
		$ktdata->req_php,
		phpversion()
	);

	if( defined( 'WP_CLI' ) ){
		WP_CLI::error( $message );
	}
	else {
		add_action( 'admin_notices', static function() use ( $message ){
			echo '<div id="message" class="notice notice-error"><p>' . $message . '</p></div>';
		} );
	}

	return;
}

define( 'KTHUMB_VER', $ktdata->ver );
unset( $ktdata );

define( 'KTHUMB_DIR', wp_normalize_path( __DIR__ ) );

// as plugin
if(
	false !== strpos( KTHUMB_DIR, wp_normalize_path( WP_PLUGIN_DIR ) )
	||
	false !== strpos( KTHUMB_DIR, wp_normalize_path( WPMU_PLUGIN_DIR ) )
){
	define( 'KTHUMB_URL', plugins_url( '', __FILE__ ) );
}
// in theme
else {
	define( 'KTHUMB_URL', strtr( KTHUMB_DIR, [ wp_normalize_path( get_template_directory() ) => get_template_directory_uri() ] ) );
}


// load files

spl_autoload_register( static function( $name ){

	if( false !== strpos( $name, 'Kama_Make_Thumb' ) || false !== strpos( $name, 'Kama_Thumbnail' ) ){

		require KTHUMB_DIR . "/classes/$name.php";
	}
} );

require KTHUMB_DIR . '/functions.php';


// stop if this file loads from uninstall.php file
if( defined( 'WP_UNINSTALL_PLUGIN' ) ){
	return;
}


// init

if( defined( 'WP_CLI' ) ){

	WP_CLI::add_command( 'kthumb', 'Kama_Thumbnail_CLI_Command', [
		'shortdesc' => 'Kama Thumbnail Plugin CLI Commands',
	] );
}


/**
 * Initialize the plugin later, so that we can use some hooks from the theme.
 */
add_action( 'init', 'kama_thumbnail_init' );

function kama_thumbnail_init(){

	if( ! defined( 'DOING_AJAX' ) ){
		load_plugin_textdomain( 'kama-thumbnail', false, basename( KTHUMB_DIR ) . '/languages' );
	}

	kama_thumbnail();

	// upgrade
	if( defined( 'WP_CLI' ) || is_admin() || wp_doing_ajax() ){
		require_once __DIR__ .'/upgrade.php';

		\Kama_Thumbnail\upgrade();
	}
}

