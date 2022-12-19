<?php

/**
 * Use following code instead of same-named functions where you want to show thumbnail:
 *
 *     echo apply_filters( 'kama_thumb_src',   '', $args, $src );
 *     echo apply_filters( 'kama_thumb_img',   '', $args, $src );
 *     echo apply_filters( 'kama_thumb_a_img', '', $args, $src );
 */
add_filter( 'kama_thumb_src',   'kama_thumb_hook_cb', 0, 3 );
add_filter( 'kama_thumb_img',   'kama_thumb_hook_cb', 0, 3 );
add_filter( 'kama_thumb_a_img', 'kama_thumb_hook_cb', 0, 3 );

function kama_thumb_hook_cb( $foo, $args = [], $src = 'notset' ){

	$cur_hook = current_filter(); // hook name

	// support for versions earlier than 3.4.0, in which the hooks have been renamed
	foreach( $GLOBALS['wp_filter'][ $cur_hook ]->callbacks as $priority => $callbacks ){

		foreach( $callbacks as $cb ){

			// skip current hook
			if( __FUNCTION__ === $cb['function'] ){
				continue;
			}

			// re-create hooks:
			// `kama_thumb_src`   → `kama_thumb__src`
			// `kama_thumb_img`   → `kama_thumb__img`
			// `kama_thumb_a_img` → `kama_thumb__a_img`
			remove_filter( $cur_hook, $cb['function'], $priority );
			$new_hook_name = str_replace( 'kama_thumb_', 'kama_thumb__', $cur_hook );
			add_filter( $new_hook_name, $cb['function'], $priority, $cb['accepted_args'] );

			if( WP_DEBUG ){
				trigger_error(
					sprintf(
						'Kama Thumbnail hook `%s` was renamed to `%s` in version %s. Fix code of your theme or plugin, please.',
						$cur_hook, $new_hook_name, '3.4.0'
					),
					E_USER_NOTICE
				);
			}
		}
	}

	// call function
	return $cur_hook( $args, $src );
}

/**
 * Make thumbnail and gets it URL.
 *
 * @param array  $args
 * @param string $src
 *
 * @return string
 */
function kama_thumb_src( $args = [], $src = 'notset' ){

	return ( new Kama_Make_Thumb( $args, $src ) )->src();
}

/**
 * Make thumbnail and gets it IMG tag.
 *
 * @param array  $args
 * @param string $src
 *
 * @return string
 */
function kama_thumb_img( $args = [], $src = 'notset' ){

	return ( new Kama_Make_Thumb( $args, $src ) )->img();
}

/**
 * Make thumbnail and gets it IMG tag wrapped with A tag.
 *
 * @param array  $args
 * @param string $src
 *
 * @return mixed|string|void
 */
function kama_thumb_a_img( $args = [], $src = 'notset' ){

	return ( new Kama_Make_Thumb( $args, $src ) )->a_img();
}

/**
 * Reference to the last Kama_Make_Thumb instance to read some properties: height, width, or other...
 *
 * @param string $deprecated
 *
 * @return mixed|Kama_Make_Thumb|null The value of specified property or
 *                                    `Kama_Make_Thumb` object if no property is specified.
 */
function kama_thumb( $deprecated = '' ) {

	$instance = Kama_Make_Thumb::$last_instance;

	if( $deprecated ){
		_deprecated_argument( __FUNCTION__, '3.4.12', '`$optname` parameter is deprecated use returned object properties instead.' );
	}

	if( ! $deprecated ){
		return $instance;
	}

	if( property_exists( $instance, $deprecated ) ){
		return $instance->$deprecated;
	}

	return null;
}

/**
 * Gets instance of Kama_Thumbnail class.
 *
 * @return Kama_Thumbnail
 */
function kama_thumbnail(){
	return Kama_Thumbnail::instance();
}

/**
 * @return Kama_Thumbnail_Options
 */
function kthumb_opt(){
	return Kama_Thumbnail::$opt;
}

/**
 * @return Kama_Thumbnail_Cache
 */
function kthumb_cache(){
	return Kama_Thumbnail::$cache;
}

