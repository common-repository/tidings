<?php

class Tidings_Settings {
	private static $instance = null;

	protected $option_name = 'tidings_settings';
	protected $base_url = 'https://app.tidings.com/api/v1/';
	protected $import_query_var = 'tidings-api';
	protected $rte_counter = 0;
	protected $formatting_options = array(
		'h1'  => 'Heading 1',
		'h2'  => 'Heading 2',
		'h3'  => 'Heading 3',
		'h4'  => 'Heading 4',
		'h5'  => 'Heading 5',
		'h6'  => 'Heading 6',
		'p'   => 'Paragraph',
		'div' => 'none',
	);

	public function __construct() {
		// Initialize theme options when in WP admin
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );

		// Initialize general theme settings
		add_action( 'admin_init', array( $this, 'init_settings' ) );
		add_action( 'admin_notices', array( $this, 'add_settings_errors' ) );
	}

	public function add_settings_errors() {
		//settings_errors();
	}

	/**
	 * This method sneds a message to tidings.com API, to register this website. When no (valid) key is entered, it will return an error message and the plugin cannot be used yet.
	 *
	 * @param array $data Contains all the saved data from the settings form.
	 *
	 * @return array
	 */
	public function validate_subscription( $data ) {
		if ( empty( $data['account_key'] ) ) {
			add_settings_error( $this->option_name, $this->option_name, __( 'You have to enter a key to use this plugin.', 'tidings' ), 'error' );
			return $data;
		}

		// Send wp_url again to tidings API when there is no key set before, or the key is changed.
		$current_option = get_option( $this->option_name);
		$current_key = isset( $current_option['account_key'] ) ? esc_attr( $current_option['account_key'] ) : false;

		if( true || empty( $current_key ) || $current_key != $data['account_key'] ) {
			$response = wp_remote_post( $this->base_url . 'core/wp_install/', array(
				'headers' => array(
					'X-Tidings-Access-Token' => $data['account_key'],
					'Content-Type' => 'application/json',
				),
				'body' => json_encode( array(
					'wp_url' => trailingslashit( trailingslashit( get_home_url() ) . $this->import_query_var ),
				) )
			) );

			$response_body = json_decode( wp_remote_retrieve_body( $response ) );

			if( $response['response']['code'] != 200 || false == $response_body || $response_body->status != 200 ) {
				add_settings_error( $this->option_name, $this->option_name, __( 'Please enter a valid account key to use this plugin. you can find this key in your account settings on tidings.com', 'tidings' ), 'error' );
				return $data;
			}
		}

		return $data;
	}

	/**
	 * add_settings_page - Adds Generates the settings page for the theme, with hooks for plugins to add their settings sections
	 */
	public function add_settings_page() {

		add_options_page(
			__( 'Tidings', 'tidings' ),
			__( 'Tidings', 'tidings' ),
			'manage_options',
			'tidings-settings',
			array(
				$this,
				'settings_page',
			)
		);

	}

	/**
	 * Draw the content of the settings page in WP admin
	 */
	public function settings_page() {
		?>

		<div class="wrap">
			<div id="icon-themes" class="icon32"></div>
			<h2><?php _e( 'Tidings settings', 'tidings' ) ?></h2>

			<?php //settings_errors(); ?>

			<form method="post" action="options.php">
				<?php
				do_action( 'tidings_display_settings_sections' );
				submit_button();
				?>
			</form>
		</div>

		<?php
	}


	/**
	 * Initiates the settings section
	 */
	public function init_settings() {

		add_action( 'tidings_display_settings_sections', function () {
			settings_fields( 'tidings_general_settings' );
			do_settings_sections( 'tidings_general_settings' );

			submit_button();

			settings_fields( 'tidings_formatting_settings' );
			do_settings_sections( 'tidings_formatting_settings' );
		} );

		// Register all these settings to the right option
		register_setting( 'tidings_general_settings', $this->option_name, array( $this, 'validate_subscription' ) );
		register_setting( 'tidings_formatting_settings', $this->option_name );

		// Initiate the section for our settings
		add_settings_section(
			$this->option_name,
			__( 'General Settings', 'tidings' ),
			function () {
			},
			'tidings_general_settings'
		);

		add_settings_section(
			$this->option_name,
			__( 'Post Formatting', 'tidings' ),
			function () {
			},
			'tidings_formatting_settings'
		);

		// Add all settings fields
		$this->add_general_settings_fields();
		$this->add_formatting_settings_fields();

		do_action( 'tidings_general_settings_sections' );
		do_action( 'tidings_formatting_settings_sections' );
	}

	/**
	 * add_general_settings_fields - Add the fields to the general settings section initiated in init_settings
	 */
	public function add_general_settings_fields() {

		// General setting fields
		$this->add_field( 'text', 'account_key', __( 'Tidings account key', 'tidings' ), 'tidings_general_settings', array() );

		// Get categories.
		$categories       = get_terms( array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
		) );
		$categories_array = array();
		$categories_array[''] = ' -- ' . __( 'No category', 'tidings' ) . ' -- ';

		foreach ( $categories as $category ) {
			$categories_array[ $category->term_id ] = $category->name;
		}

		$this->add_field( 'select', 'publication_category', __( 'Publication category', 'tidings' ), 'tidings_general_settings', array(
			'description' => __( 'The default category where you\'d like your Tidings postings to live.', 'tidings' ),
			'values'      => $categories_array,
		) );

		// Get users.
		$users       = get_users();
		$users_array = array();

		foreach ( $users as $user ) {
			$users_array[ $user->ID ] = $user->data->display_name;
		}

		$this->add_field( 'select', 'publication_author', __( 'Default author', 'tidings' ), 'tidings_general_settings', array(
			'description' => __( 'The default author which will create the posting. You can always change this author after the post have been created.', 'tidings' ),
			'values'      => $users_array,
		) );

	}

	/**
	 * add_formatting_settings_fields - Add the fields to the general settings section initiated in init_settings
	 */
	public function add_formatting_settings_fields() {
		$this->add_field( 'select', 'tag_intro', __( 'Intro', 'tidings' ) . '<br>(<code>.tidings-intro</code>)', 'tidings_formatting_settings', array(
			'values'  => $this->formatting_options,
			'default' => 'p',
		) );

		$this->add_field( 'select', 'tag_cta', __( 'Call-to-action', 'tidings' ) . '<br>(<code>.tidings-cta</code>)', 'tidings_formatting_settings', array(
			'values'  => $this->formatting_options,
			'default' => 'h3',
		) );

		$this->add_field( 'select', 'tag_article_headline', __( 'Article headline', 'tidings' ) . '<br>(<code>.tidings-article-headline</code>)', 'tidings_formatting_settings', array(
			'values'  => $this->formatting_options,
			'default' => 'h4',
		) );

		$this->add_field( 'select', 'tag_article_source', __( 'Article source', 'tidings' ) . '<br>(<code>.tidings-article-source</code>)', 'tidings_formatting_settings', array(
			'values'  => $this->formatting_options,
			'default' => 'h6',
		) );
		
		$this->add_field( 'select', 'tag_outro', __( 'Outro', 'tidings' ) . '<br>(<code>.tidings-outro</code>)', 'tidings_formatting_settings', array(
			'values'  => $this->formatting_options,
			'default' => 'p',
		) );
	}

	/**
	 * @param        $type
	 * @param        $key
	 * @param        $label
	 * @param string $settings_field
	 * @param array  $args
	 */
	public function add_field( $type, $key, $label, $settings_field = 'tidings_general_settings', $args = array() ) {
		$args = wp_parse_args( (array) $args, array(
			'id'          => $key,
			'option_name' => $this->option_name,
		) );

		add_settings_field( $key, $label, array( $this, $type ), $settings_field, $this->option_name, $args );
	}

	/**
	 * Get the value from an option
	 *
	 * @param string $option      Name of the value in the option. When option_name is given it will get the key from the option_name array
	 * @param string $option_name of option_key.
	 *
	 * @return bool|mixed
	 * @internal param string $val name of the value in the option. When option_name is given it will get the key from the option_name array
	 */
	public function get_option( $option, $option_name = null ) {
		if ( null == $option_name ) {
			$option_name = $this->option_name;
		}

		if ( $option_name != '' ) {
			$options       = get_option( $option_name );
			$return_option = ! empty( $options[ $option ] ) ? maybe_unserialize( $options[ $option ] ) : false;
		} else {
			$return_option = maybe_unserialize( get_option( $option ) );
		}

		return $return_option;
	}

	/**
	 * @param $args
	 */
	public function text( $args ) {
		$value = $this->get_option( $args['id'], $args['option_name'] );
		$type  = isset( $args['type'] ) ? $args['type'] : 'text';

		echo Tidings_Form_Fields::text( $args['option_name'] . '[' . $args['id'] . ']', $value, array(
			'id'          => $args['option_name'] . '-' . $args['id'],
			'type'        => $type,
			'placeholder' => isset( $args['placeholder'] ) ? $args['placeholder'] : '',
		) );

		if ( ! empty( $args['description'] ) ) {
			echo '<br /><small>' . $args['description'] . '</small>';
		}
	}

	/**
	 * @param $args
	 */
	public function textarea( $args ) {
		$value  = $this->get_option( $args['id'], $args['option_name'] );
		$is_rte = ! empty( $args['rte'] ) && true == $args['rte'];

		if ( $is_rte ) {
			$args['id'] = ! empty( $args['id'] ) ? $args['id'] : '';
			$args['id'] .= '-' . $this->rte_counter;
			$this->rte_counter ++;
		}

		echo Tidings_Form_Fields::textarea( $args['option_name'] . '[' . $args['id'] . ']', $value, array(
			'id'  => $args['id'],
			'rte' => $is_rte,
		) );

		if ( ! empty( $args['description'] ) ) {
			echo '<br /><small>' . $args['description'] . '</small>';
		}
	}

	/**
	 * @param $args
	 */
	public function checkbox( $args ) {
		$value = $this->get_option( $args['id'], $args['option_name'] );

		echo Tidings_Form_Fields::checkbox( $args['option_name'] . '[' . $args['id'] . ']', $value, array(
			'id' => $args['option_name'] . '-' . $args['id'],
		) );

		if ( ! empty( $args['description'] ) ) {
			echo ' <label for="' . $args['option_name'] . '-' . $args['id'] . '">' . $args['description'] . '</label>';
		}
	}

	/**
	 * @param $args
	 */
	public function file_upload( $args ) {
		$value = $this->get_option( $args['id'], $args['option_name'] );

		echo Tidings_Form_Fields::upload( $args['option_name'] . '[' . $args['id'] . ']', $value, array(
			'id' => $args['option_name'] . '-' . $args['id'],
		) );

	}

	/**
	 * @param $args
	 */
	public function radio( $args ) {
		$value = $this->get_option( $args['id'], $args['option_name'] );

		echo Tidings_Form_Fields::radio( $args['option_name'] . '[' . $args['id'] . ']', $value, $args['values'], array(
			'id' => $args['option_name'] . '-' . $args['id'],
		) );

		if ( ! empty( $args['description'] ) ) {
			echo '<br /><small>' . $args['description'] . '</small>';
		}
	}

	/**
	 * @param $args
	 */
	public function select( $args ) {
		$value = $this->get_option( $args['id'], $args['option_name'] );

		if ( empty( $value ) && isset( $args['default'] ) ) {
			$value = $args['default'];
		}

		echo Tidings_Form_Fields::select( $args['option_name'] . '[' . $args['id'] . ']', $value, $args['values'], array(
			'id' => $args['option_name'] . '-' . $args['id'],
		) );

		if ( ! empty( $args['description'] ) ) {
			echo '<br /><small>' . $args['description'] . '</small>';
		}
	}

	public function pageselect( $args ) {
		$value = $this->get_option( $args['id'], $args['option_name'] );

		echo Tidings_Form_Fields::page_select( $args['option_name'] . '[' . $args['id'] . ']', $value, $args['values'], array(
			'id' => $args['option_name'] . '-' . $args['id'],
		) );

		if ( ! empty( $args['description'] ) ) {
			echo '<br /><small>' . $args['description'] . '</small>';
		}

	}

	/**
	 * Returns an instance of this class. An implementation of the singleton design pattern.
	 *
	 * @return   object    A reference to an instance of this class.
	 * @since    1.0.0
	 */
	public static function instance() {
		// Check if we already have an instance alive
		if ( ! isset( static::$instance ) ) {
			static::$instance = new static;
		}

		return static::$instance;
	}

}
