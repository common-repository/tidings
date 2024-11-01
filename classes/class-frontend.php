<?php

class Tidings_Frontend {
	private static $instance;

	public function __construct() {
		$this->run();
	}

	public function run() {
		add_action( 'wp_enqueue_scripts', array( $this, 'add_styles' ) );
	}

	public function add_styles() {
		wp_register_style( 'tidings-css', plugins_url( 'tidings/css/tidings.css' ), null, TIDINGS_VERSION );
		wp_enqueue_style( 'tidings-css' );
	}

	/**
	 * Returns an instance of this class. An implementation of the singleton design pattern.
	 *
	 * @return   object    A reference to an instance of this class.
	 * @since    1.0.0
	 */
	public static function instance() {
		if ( null == self::$instance ) {
			self::$instance = new Tidings_Frontend();
		}

		return self::$instance;
	}
}
