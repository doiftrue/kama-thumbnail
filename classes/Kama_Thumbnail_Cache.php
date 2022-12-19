<?php

/**
 * @see kthumb_cache()
 */
class Kama_Thumbnail_Cache {

	public function init(): void {

		if( is_admin() ){

			add_filter( 'save_post', [ $this, 'clear_post_meta' ] );
		}
	}

	/**
	 * Clears custom field with a link when you update the post,
	 * to create it again. Only if the meta-field of the post exists.
	 *
	 * @param int $post_id
	 */
	public function clear_post_meta( int $post_id ): void {
		global $wpdb;

		$meta_key = kthumb_opt()->meta_key;

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s", $post_id, $meta_key
		) );

		if( $row ){
			update_post_meta( $post_id, $meta_key, '' );
		}
	}

	/**
	 * Cache cleanup with expire verification.
	 *
	 * @param string $type
	 */
	public function smart_clear( string $type = '' ): void {

		$_stub = ( $type === 'stub' );
		$cache_dir = kthumb_opt()->cache_dir;
		$expire_file = "$cache_dir/". ( $_stub ? 'expire_stub' : 'expire' );

		if( ! is_dir( $cache_dir ) ){
			return;
		}

		$expire = $cleared = 0;
		if( file_exists( $expire_file ) ){
			$expire = (int) file_get_contents( $expire_file );
		}

		if( $expire < time() ){
			$cleared = $this->clear_thumb_cache( $_stub ? 'only_stub' : '' );
		}

		if( $cleared || ! $expire ){
			$expire_time = time() + ( $_stub ? DAY_IN_SECONDS : kthumb_opt()->auto_clear_days * DAY_IN_SECONDS );
			@ file_put_contents( $expire_file, $expire_time );
		}

	}

	/**
	 * ?kt_clear=clear_cache - clear the image cache
	 * ?kt_clear=delete_meta - removes custom fields
	 *
	 * @param $type
	 */
	public function force_clear( $type ): void {

		switch( $type ){
			case 'rm_stub_thumbs':
				$this->clear_thumb_cache('only_stub');
				break;
			case 'rm_thumbs':
				$this->clear_thumb_cache();
				break;
			case 'rm_post_meta':
				$this->delete_meta();
				break;
			case 'rm_all_data':
				$this->clear_thumb_cache();
				$this->delete_meta();
				break;
		}

	}

	/**
	 * @param string|int $url Image src OR WP attach ID.
	 *
	 * @return false|int Numbers of files deleted or false if nothing to delete.
	 */
	public function clear_img_cache( $url ){

		// attachment ID passed
		if( is_numeric( $url ) ){
			$url = wp_get_attachment_url( (int) $url );
		}

		if( ! $url ){
			Kama_Thumbnail_Helpers::show_error( __( 'No IMG URL was specified.', 'kama-thumbnail' ) );
			return false;
		}

		$url = esc_url_raw( $url );

		$glob_pattern = $this->glob_pattern( $url );

		if( ! $glob_pattern ){
			Kama_Thumbnail_Helpers::show_error( 'Something wrong in code - $glob_pattern not determined.' );
			return false;
		}

		$glob = glob( $glob_pattern );

		if( ! $glob ){
			Kama_Thumbnail_Helpers::show_message( __( 'Nothing to clear.', 'kama-thumbnail' ) );
			return false;
		}

		foreach( $glob as $file ){
			unlink( $file );
		}

		$deleted_files_num = count( $glob );

		$msg = sprintf( __( '%d cache files deleted.', 'kama-thumbnail' ), $deleted_files_num );
		Kama_Thumbnail_Helpers::show_message( $msg );

		return $deleted_files_num;
	}

	/**
	 * Gets pattern to use in glob() functions to get all IMG cached files to delete.
	 *
	 * @param string $url Original OR thumb URL.
	 *
	 * @return string Pattern OR Empty string if pattern can't be determined.
	 */
	private function glob_pattern( string $url ): string {

		// eg: https://example.com/wp-content/cache/thumb/29/db8317d19c70529_250x100_top.png
		$cache_dir_url_path = parse_url( kthumb_opt()->cache_dir_url, PHP_URL_PATH );

		// thumb URL passed
		if( false !== strpos( $url, $cache_dir_url_path ) ){

			$glob_pattern = $this->_glob_pattern_by_thumb_url( $url );
		}
		// original URL passed
		else {

			$thumb = new Kama_Make_Thumb( '', $url );

			// SVG or something wrong with URL.
			if( ! $thumb->thumb_url ){
				Kama_Thumbnail_Helpers::show_message( __( 'SVG or something wrong with URL.', 'kama-thumbnail' ) );
				return false;
			}

			$glob_pattern = $this->_glob_pattern_by_thumb_url( $thumb->thumb_url );
		}

		return $glob_pattern;
	}

	private function _glob_pattern_by_thumb_url( string $thumb_url ): string {

		$cache_dir_url_path = parse_url( kthumb_opt()->cache_dir_url, PHP_URL_PATH );

		$thumb_url_right_part = explode( $cache_dir_url_path, $thumb_url )[1];

		// create fake data to determine hash length for use in regex
		$thumb = new Kama_Make_Thumb( '', '/foo/bar.jpg' );
		$hash_length = strlen( $thumb->metadata->file_name_data->hash );

		if( ! $hash_length ){
			Kama_Thumbnail_Helpers::show_error( 'Something wrong in code - $hash_length not determined.' );
			return '';
		}

		$regex = sprintf( '#^.*/[a-f0-9]{%s}_#i', $hash_length );

		/**
		 * Allows to change regular expression to find cached files for deletion.
		 *
		 * @param string          $regex
		 * @param Kama_Make_Thumb $thumb
		 */
		$regex = apply_filters( 'kama_thumb__clear_img_cache_glob_pattern_regex', $regex, $thumb );

		if( ! preg_match( $regex, $thumb_url_right_part, $mm ) ){
			Kama_Thumbnail_Helpers::show_error( 'Something wrong in code - hash part not found in cache IMG URL.' );
			return '';
		}

		return kthumb_opt()->cache_dir . $mm[0] . '*';
	}

	/**
	 * Removes all cached images files.
	 */
	public function clear_thumb_cache( $only_stub = false ): bool {

		$cache_dir = kthumb_opt()->cache_dir;

		if( ! $cache_dir ){
			Kama_Thumbnail_Helpers::show_error( __( 'ERROR: Path to cache not set.', 'kama-thumbnail' ) );

			return false;
		}

		if( is_dir( $cache_dir ) ){

			// delete stub only
			if( $only_stub ){
				foreach( glob( "$cache_dir/stub_*" ) as $file ){
					unlink( $file );
				}

				if( defined( 'WP_CLI' ) || WP_DEBUG ){
					$msg = __( 'All nophoto thumbs was deleted from <b>Kama Thumbnail</b> cache.', 'kama-thumbnail' );
					Kama_Thumbnail_Helpers::show_info( $msg );
				}
			}
			// delete all
			else{
				self::clear_folder( $cache_dir );

				$msg = __( '<b>Kama Thumbnail</b> cache has been cleared.', 'kama-thumbnail' );
				Kama_Thumbnail_Helpers::show_message( $msg );
			}
		}

		return true;
	}

	/**
	 * Deletes all `photo_URL` meta-fields of posts.
	 */
	public function delete_meta(): void {
		global $wpdb;

		if( ! kthumb_opt()->meta_key ){
			Kama_Thumbnail_Helpers::show_error( 'meta_key option not set.' );

			return;
		}

		if( is_multisite() ){
			$deleted = [];
			$sites = get_sites( [
				'fields' => 'ids',
				'number' => 500,
			] );

			foreach( $sites as $blog_id ){
				$deleted[] = $wpdb->delete(
					$wpdb->get_blog_prefix( $blog_id ) .'postmeta', [ 'meta_key' => kthumb_opt()->meta_key ]
				);
			}

			$deleted = (bool) array_filter( $deleted );
		}
		else{
			$deleted = $wpdb->delete( $wpdb->postmeta, [ 'meta_key' => kthumb_opt()->meta_key ] );
		}

		if( $deleted ){
			Kama_Thumbnail_Helpers::show_message(
				sprintf( __( 'All custom fields <code>%s</code> was deleted.', 'kama-thumbnail' ), kthumb_opt()->meta_key )
			);
		}
		else{
			Kama_Thumbnail_Helpers::show_message(
				sprintf( __( 'Couldn\'t delete <code>%s</code> custom fields', 'kama-thumbnail' ), kthumb_opt()->meta_key )
			);
		}

		wp_cache_flush();
	}

	/**
	 * Deletes all files and folders in the specified directory.
	 *
	 * @param string $folder_path  The path to the folder you want to clear.
	 * @param bool   $del_current  Is delete $folder_path itself?
	 */
	public static function clear_folder( string $folder_path, bool $del_current = false ): void {

		$folder_path = untrailingslashit( $folder_path );

		foreach( glob( "$folder_path/*" ) as $file ){

			if( is_dir( $file ) ){
				call_user_func( __METHOD__, $file, true );
			}
			else{
				unlink( $file );
			}
		}

		if( $del_current ){
			rmdir( $folder_path );
		}
	}

}
