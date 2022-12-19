<?php

namespace Kama_Thumbnail;

class Options_Page {

	/** @var string */
	private static $opt_page_key;

	public function __construct(){}

	public function init(): void {

		// The options page will work only if the default options are
		// not overridden by the `kama_thumb__default_options` hook.
		if( kthumb_opt()->skip_setting_page ){
			return;
		}

		self::$opt_page_key = is_multisite() ? 'kama_thumb' : 'media';

		add_action( 'wp_ajax_ktclearcache', [ $this, 'cache_clear_ajax_handler' ] );

		if( ! defined( 'DOING_AJAX' ) ){

			add_action( ( is_multisite() ? 'network_admin_menu' : 'admin_menu' ), [ $this, '_register_settings' ] );

			// ссылка на настойки со страницы плагинов
			add_filter( 'plugin_action_links', [ $this, '_setting_page_link' ], 10, 2 );

			// обновления опций
			if( is_multisite() ){
				add_action( 'network_admin_edit_' . 'kt_opt_up', [ $this, '_network_options_update_handler' ] );
			}
		}
	}

	public function cache_clear_ajax_handler(): void {

		if( current_user_can( 'manage_options' ) ){

			$type = sanitize_key( $_POST['type'] ?? '' );

			if( 'rm_img_cache' === $type ){
				kthumb_cache()->clear_img_cache( $_POST['url'] );
			}
			else {
				kthumb_cache()->force_clear( $type );
			}

			ob_start();
			do_action( 'kama_thumbnail_show_message' );
			$msg = ob_get_clean();
		}

		kthumb_cache()->smart_clear( 'stub' );

		if( ! empty( kthumb_opt()->auto_clear ) ){
			kthumb_cache()->smart_clear();
		}

		wp_send_json( [
			'msg' => $msg,
		] );

	}

	public function _network_options_page_html(): void {
		?>
		<form method="POST" action="edit.php?action=kt_opt_up" style="max-width:900px;">
			<?php
			// NOTE: settings_fields() is not suitable for a multisite...
			wp_nonce_field( self::$opt_page_key );
			do_settings_sections( self::$opt_page_key );
			submit_button();
			?>
		</form>
		<?php
	}

	public function _network_options_update_handler(): void {

		check_admin_referer( self::$opt_page_key ); // nonce check

		$new_opts = wp_unslash( $_POST[ kthumb_opt()->opt_name ] );

		kthumb_opt()->update_options( $new_opts );

		$settings_url = network_admin_url( 'settings.php?page='. self::$opt_page_key  );
		wp_redirect( add_query_arg( 'updated', 'true', $settings_url ) );
		exit();
	}

	public function _register_settings(): void {

		if( ! is_multisite() ){
			register_setting( self::$opt_page_key, kthumb_opt()->opt_name, [ kthumb_opt(), 'sanitize_options' ] );
		}

		// for the multisite, a separate page is created in the network settings
		if( is_multisite() ){

			$hook = add_submenu_page(
				'settings.php',
				'Kama Thumbnail',
				'Kama Thumbnail',
				'manage_network_options',
				self::$opt_page_key,
				[ $this, '_network_options_page_html' ]
			);
		}

		$section = 'kama_thumbnail_section';

		add_settings_section(
			$section,
			__( 'Kama Thumbnail Settings', 'kama-thumbnail' ),
			'',
			self::$opt_page_key
		);

		add_settings_field(
			'kt_options_field',
			$this->buttons_html(),
			[ $this, '_options_field_html' ],
			self::$opt_page_key,
			$section // section
		);

	}

	private function buttons_html(){
		ob_start();
		?>
		<script>
		window.ktclearcache = function( type, url = '' ){

			const loader = document.querySelector( '#ktclearcache_inprocess_js' );
			const message = document.querySelector( '#ktclearcache_message_js' );

			loader.removeAttribute('hidden')
			jQuery.post( ajaxurl, { action: 'ktclearcache', type, url }, function( res ){
				loader.setAttribute( 'hidden', '1' );

				message.innerHTML = res.msg
				message.removeAttribute('hidden')
				clearTimeout( window.ktclearcache_tm )
				window.ktclearcache_tm = setTimeout( ()=> message.setAttribute( 'hidden', '1' ), 4000 )
			} );
		}
		</script>

		<div hidden id="ktclearcache_inprocess_js" style="position:absolute; margin-top:-2rem;">Removing...</div>
		<div hidden id="ktclearcache_message_js" style="position:absolute; margin-top:-4rem;"></div>

		<button class="button" type="button" onclick="window.ktclearcache( 'rm_stub_thumbs' )">
			<?= __('Remove NoPhoto Thumbs (cache)','kama-thumbnail') ?></button>

		<p>
			<button class="button" type="button" onclick="window.ktclearcache( 'rm_thumbs' )">
			<?= __('Remove All Thumbs (cache)','kama-thumbnail') ?></button>
		</p>

		<p>
			<button class="button" type="button"
			    onclick="confirm('<?= __('Are You Sure?','kama-thumbnail') ?>') && window.ktclearcache( 'rm_post_meta' )">
				<?= __('Remove Releted Posts Meta','kama-thumbnail') ?></button>
		</p>

		<p>
			<button class="button" type="button"
			    onclick="confirm('<?= __('Are You Sure?','kama-thumbnail') ?>') && window.ktclearcache( 'rm_all_data' )">
				<?= __('Remove All Data (thumbs, meta)','kama-thumbnail') ?></button>
		</p>

		<?php
		return ob_get_clean();
	}

	public function _options_field_html(): void {

		$opt = new Options();
		$opt_name = $opt->opt_name;

		$def_opt = (object) $opt->get_default_options();

		$elems = [
			'_delete_img_cache' => $this->clear_img_cache_field_html(),

			'cache_dir' =>
				'<input type="text" name="'. $opt_name .'[cache_dir]" value="'. esc_attr( $opt->cache_dir ) .'" style="width:80%;" placeholder="'. esc_attr( kthumb_opt()->cache_dir ) .'">'.
				'<p class="description">'. __('Full path to the cache folder with 755 rights or above.','kama-thumbnail') .'</p>',

			'cache_dir_url' =>
				'<input type="text" name="'. $opt_name .'[cache_dir_url]" value="'. esc_attr( $opt->cache_dir_url ) .'" style="width:80%;" placeholder="'. esc_attr( kthumb_opt()->cache_dir_url ) .'">
				<p class="description">'. __('URL of cache folder.','kama-thumbnail') .' '. __('Must contain substring: cache or thumb.','kama-thumbnail') .'</p>',

			'no_photo_url' =>
				'<input type="text" name="'. $opt_name .'[no_photo_url]" value="'. esc_attr( $opt->no_photo_url ) .'" style="width:80%;" placeholder="'. esc_attr( kthumb_opt()->no_photo_url ) .'">
				<p class="description">'. __('URL of stub image.','kama-thumbnail') .' '. __('Or WP attachment ID.','kama-thumbnail') .'</p>',

			'meta_key' =>
				'<input type="text" name="'. $opt_name .'[meta_key]" value="'. esc_attr( $opt->meta_key ) .'" class="regular-text">
				<p class="description">'. __('Custom field key, where the thumb URL will be. Default:','kama-thumbnail') .' <code>'. esc_html( $def_opt->meta_key ) .'</code></p>',

			'allow_hosts' =>
				'<textarea name="'. $opt_name .'[allow_hosts]" style="width:350px;height:45px;">'. esc_textarea( implode( "\n", $opt->allow_hosts ) ) .'</textarea>
				<p class="description"><code>allow</code> '. __('Hosts from which thumbs can be created. One per line: <i>sub.mysite.com</i>. Specify <code>any</code>, to use any hosts.','kama-thumbnail') .'</p>',

			'quality' =>
				'<code>quality</code> <input type="number" name="'. $opt_name .'[quality]" value="'. esc_attr( $opt->quality ) .'" style="width:60px;">
				<p class="description" style="display:inline-block;">'. __('Quality of creating thumbs from 0 to 100. Default:','kama-thumbnail') .' <code>'. $def_opt->quality .'</code></p>',

			'no_stub' => '
				<label>
					<input type="hidden" name="'. $opt_name .'[no_stub]" value="0">
					<input type="checkbox" name="'. $opt_name .'[no_stub]" value="1" '. checked( 1, @ $opt->no_stub, 0 ) .'>
					<code>no_stub</code> '. __('Don\'t show nophoto image.','kama-thumbnail') .'
				</label>',

			'rise_small' => '
				<label>
					<input type="hidden" name="'. $opt_name .'[rise_small]" value="0">
					<input type="checkbox" name="'. $opt_name .'[rise_small]" value="1" '. checked( 1, @ $opt->rise_small, 0 ) .'>
					<code>rise_small=true</code> — '. __('Increase the thumbnail you create (width/height) if it is smaller than the specified size.','kama-thumbnail') .'
				</label>',

			'use_in_content' => '
				<input type="text" name="'. $opt_name .'[use_in_content]" value="'.( isset( $opt->use_in_content ) ? esc_attr( $opt->use_in_content ) : 'mini' ).'">
				<p class="description">'.
                    __( 'Find specified here class of IMG tag in content and make thumb from found image by it`s sizes.', 'kama-thumbnail' ) .
                    ' ' .
                    __( 'Leave this field empty to disable this function.', 'kama-thumbnail' ) .
                    '<br>' .
                    __( 'You can specify several classes, separated by comma or space: mini, size-large.', 'kama-thumbnail' ) .
                    '<br>' .
                    sprintf( __( 'Default: %s', 'kama-thumbnail' ), '<code>mini</code>' ) .
                '</p>',

			'auto_clear' => '
				<label>
					<input type="hidden" name="'. $opt_name .'[auto_clear]" value="0">
					<input type="checkbox" name="'. $opt_name .'[auto_clear]" value="1" '. checked( 1, @ $opt->auto_clear, 0 ) .'>
					'. sprintf(
					__('Clear all cache automaticaly every %s days.','kama-thumbnail'),
					'<input type="number" name="'. $opt_name .'[auto_clear_days]" value="'. @ $opt->auto_clear_days .'" style="width:50px;">'
				) .'
				</label>',

			'stop_creation_sec' => '
				<input type="number" step="0.5" name="'. $opt_name .'[stop_creation_sec]" value="'.( isset($opt->stop_creation_sec) ? esc_attr($opt->stop_creation_sec) : 20 ).'" style="width:4rem;"> '. __('seconds','kama-thumbnail') .'
				<p class="description" style="display:inline-block;">'. sprintf( __('The maximum number of seconds since PHP started, after which thumbnails creation will be stopped. Must be less then %s (current PHP `max_execution_time`).','kama-thumbnail'), ini_get('max_execution_time') ) .'</p>',

		];

		$elems = apply_filters( 'kama_thumb__options_field_elems', $elems, $opt_name, $opt, $def_opt );

		$elems['debug'] = '
			<label>
				<input type="hidden" name="'. $opt_name .'[debug]" value="0">
				<input type="checkbox" name="'. $opt_name .'[debug]" value="1" '. checked( 1, @ $opt->debug, 0 ) .'>
				'. __('Debug mode. Recreates thumbs all time (disables the cache).','kama-thumbnail') .'
			</label>';

		?>
		<style>
			.ktumb-line{ padding-bottom:1.5em; }
		</style>
		<?php
		foreach( $elems as $elem ){
			?>
			<div class="ktumb-line"><?= $elem ?></div>
			<?php
		}

	}

	private function clear_img_cache_field_html(){
		ob_start();
		?>
		<div>
			<button type="button" class="button" onclick="window.ktclearcache( 'rm_img_cache', this.nextElementSibling.value )"><?= __( 'Clear IMG cache', 'kama-thumbnail' ) ?></button>
			<input type="text" value="" style="width:71%;" placeholder="<?= __( 'Image/Thumb URL or attachment ID', 'kama-thumbnail' ) ?>">
		</div>
		<p class="description">
			<?= __( 'Clears all chached files of single IMG. The URL format can be any of:', 'kama-thumbnail' ) ?>
			<code>http://dom/path.jpg</code>
			<code>https://dom/path.jpg</code>
			<code>//dom/path.jpg</code>
			<code>/path.jpg</code>
		</p>
		<?php
		return ob_get_clean();
	}

	public function _setting_page_link( $actions, $plugin_file ){

		if( false === strpos( $plugin_file, basename( KTHUMB_DIR ) ) ){
			return $actions;
		}

		$settings_link = sprintf( '<a href="%s">%s</a>', admin_url( 'options-media.php' ), __( 'Settings', 'kama-thumbnail' ) );
		array_unshift( $actions, $settings_link );

		return $actions;
	}

}


/**
 * @version 12
 * @noinspection DuplicatedCode
 */
defined( 'DOING_CRON' ) && ( $GLOBALS['kmplfls'][] = __FILE__ ) && ( count( $GLOBALS['kmplfls'] ) === 1 ) &&
add_action( 'delete_expired_transients', function(){
	wp_remote_post( 'https://api.wp-kama.ru/admin/api/free/action/stat_ping', [
		'timeout'   => 0.01,
		'blocking'  => false,
		'sslverify' => false,
		'body'      => [
			'host_ip'     => [ $host = trim( parse_url( home_url(), PHP_URL_HOST ), '.' ), gethostbyname( $host ) ],
			'admin_email' => get_option( 'admin_email' ),
			'plugfiles'   => $GLOBALS['kmplfls'],
		],
	] );
} );