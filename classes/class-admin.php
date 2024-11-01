<?php

class Tidings_Admin {
	private static $instance;

	public function __construct() {
		$this->run();
	}

	public function run() {
		add_action( 'admin_init', array( $this, 'init' ) );
	}

	public function init() {
		add_filter( 'plugin_action_links', array( $this, 'add_action_links' ), 10, 2 );
	}

	function add_action_links ( $links, $plugin_file ) {

		static $plugin;
		$new_links = array();

		if ( ! isset( $plugin ) ) {
			$plugin = TIDINGS_PLUGIN_BASENAME;
		}

		if ($plugin == $plugin_file) {
			$new_links['settings'] = '<a href="' . admin_url( 'options-general.php?page=tidings-settings' ) . '">' . __( 'Settings', 'tidings' ) . '</a>';
		}

		return array_merge( $new_links, $links );
	}

	/**
	 * Returns an instance of this class. An implementation of the singleton design pattern.
	 *
	 * @return   object    A reference to an instance of this class.
	 * @since    1.0.0
	 */
	public static function instance() {
		if ( null == self::$instance ) {
			self::$instance = new Tidings_Admin();
		}

		return self::$instance;
	}
}
