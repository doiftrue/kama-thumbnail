<?php

/**
 * Class Kama_Thumbnail.
 *
 * @see kama_thumbnail()
 */
class Kama_Thumbnail {

	/**
	 * Plugin options.
	 *
	 * @var Kama_Thumbnail_Options
	 */
	public static $opt;

	/**
	 * Clear Cache.
	 *
	 * @var Kama_Thumbnail_Cache
	 */
	public static $cache;

	/**
	 * Integration with WP.
	 *
	 * @var Kama_Thumbnail_Integration
	 */
	public static $integ;


	/**
	 * @return self
	 */
	public static function instance(): self {
		static $instance;
		$instance || $instance = new self();

		return $instance;
	}

	private function __construct(){

		self::$opt = new Kama_Thumbnail_Options();
		self::$opt->init();

		self::$cache = new Kama_Thumbnail_Cache();
		self::$cache->init();

		self::$integ = new Kama_Thumbnail_Integration();
		self::$integ->init();

		if( is_admin() ){
			( new Kama_Thumbnail_Options_Page() )->init();
		}

		/**
		 * Allow to do something when Kama_Thumbnail initialized.
		 *
		 * @param Kama_Thumbnail_Options $options
		 */
		do_action( 'kama_thumb_inited', self::$opt );
	}

}
