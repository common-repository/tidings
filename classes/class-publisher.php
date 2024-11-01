<?php

class Tidings_Publisher {
	private static $instance;

	protected $option_name = 'tidings_settings';
	protected $options = null;
	protected $import_query_var = 'tidings-api';
	protected $base_url = 'https://app.tidings.com/api/v1/';
	protected $default_post_status = 'draft';

	public function __construct() {
		$this->run();
	}

	public function run() {
		add_action( 'init', array( $this, 'rewrite_rules' ), 1 );
		add_action( 'template_redirect', array( $this, 'handle_endpoint' ), 1 );

		register_deactivation_hook( TIDINGS_PLUGIN_FILE, 'flush_rewrite_rules' );
		register_activation_hook( TIDINGS_PLUGIN_FILE, array( $this, 'flush_rewrites' ) );
	}

	// Add rewrite rule for importing outside of WP cron
	function rewrite_rules() {
		$GLOBALS['wp']->add_query_var( $this->import_query_var );

		add_rewrite_rule( '^' . $this->import_query_var . '/([^/]+)$', 'index.php?' . $this->import_query_var . '=$matches[1]', 'top' );
	}

	public function flush_rewrites() {
		$this->rewrite_rules();
		flush_rewrite_rules();
	}

	function handle_endpoint() {
		$newsletter_id = get_query_var( $this->import_query_var );
		$edit_url = '';

		if ( empty( $newsletter_id ) ) {
			return false;
		}

		// Get JSON data from POST input
		$return_array = array(
			'error'    => true,
			'message'  => '',
			'edit_url' => '',
		);

		// Validate API key.
		$this->options = get_option( $this->option_name );
		$account_key   = isset( $this->options['account_key'] ) ? esc_attr( $this->options['account_key'] ) : false;

		if ( false === $account_key ) {
			$return_array['message'] = 'No account key has been setup in the WP admin.';
		} else {
			$response = $this->publish_post( $this->options['account_key'], $newsletter_id );

			if ( is_wp_error( $response ) ) {
				/** @var $response \WP_Error */
				$return_array['message'] = $response->get_error_message();
			} else {
				/** @var $response int (contains post ID) */
				$return_array['error']    = false;
				$edit_url = add_query_arg( array(
					'post'   => $response,
					'action' => 'edit',
				), trailingslashit( get_admin_url() ) . 'post.php' );
			}
		}

		if( true == $return_array['error'] ) {
			die( $return_array['message'] );
		}
		else {
			Header( 'HTTP/1.1 301 Moved Permanently' );
			Header( 'Location: ' . $edit_url );
			die();
		}
	}

	/**
	 * @param $account_key   string Account key from Tidings.com
	 * @param $newsletter_id int ID of the newsletter from Tidings.com
	 *
	 * @return int|WP_Error
	 */
	public function publish_post( $account_key, $newsletter_id ) {
		$newsletter_data = $this->get_newsletter_data( $account_key, $newsletter_id );

		if ( is_wp_error( $newsletter_data ) ) {
			return $newsletter_data;
		}

		$post_id = wp_insert_post( array(
			'post_title'   => $newsletter_data->subject,
			'post_content' => $this->get_newsletter_content( $newsletter_data ),
			'post_status'  => $this->get_default_post_status(),
			'post_author'  => $this->get_default_post_author(),
		) );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( 0 === $post_id ) {
			return new WP_Error( 'tidings-error', __( 'The post could not be created in WordPress.', 'tidings' ) );
		}

		$default_category = $this->get_default_post_category();
		if ( false !== $default_category ) {
			wp_set_object_terms( $post_id, (int) $default_category, 'category' );
		}

		return $post_id;
	}

	/**
	 * @param $account_key   string Account key from Tidings.com
	 * @param $newsletter_id int ID of the newsletter from Tidings.com
	 *
	 * @return object|WP_Error
	 */
	public function get_newsletter_data( $account_key, $newsletter_id ) {
		$response = wp_remote_get( $this->base_url . 'newsletters/' . $newsletter_id, array(
			'headers' => array(
				'X-Tidings-Access-Token' => $account_key,
			),
		) );

		$body = isset( $response['body'] ) ? $response['body'] : null;
		if( null !== $body ) {
			$body = json_decode( $response['body'] );
		}

		if ( $response['response']['code'] != 200 || empty( $body ) ) {
			return new WP_Error( 'tidings-error', 'No valid response from API call.' );
		}

		$newsletter_data = json_decode( $response['body'] );

		if ( $newsletter_data->status != 200 ) {
			return new WP_Error( 'tidings-error', $newsletter_data->message );
		}

		return $newsletter_data->data;
	}

	public function get_newsletter_content( $data ) {
		$content = '';

		// Okay, everything looks fine, let's build the content and post it.
		$tag_intro            = isset( $this->options['tag_intro'] ) ? esc_attr( $this->options['tag_intro'] ) : 'p';
		$tag_cta              = isset( $this->options['tag_cta'] ) ? esc_attr( $this->options['tag_cta'] ) : 'h3';
		$tag_article_headline = isset( $this->options['tag_article_headline'] ) ? esc_attr( $this->options['tag_article_headline'] ) : 'h4';
		$tag_article_source   = isset( $this->options['tag_article_source'] ) ? esc_attr( $this->options['tag_article_source'] ) : 'h6';
		$tag_outro            = isset( $this->options['tag_outro'] ) ? esc_attr( $this->options['tag_outro'] ) : 'p';

		$content .= '<' . $tag_intro . ' class="tidings-intro">' . $data->introduction . '</' . $tag_intro . '>';
		$content .= '<' . $tag_cta . ' class="tidings-cta">' . $data->cta . '</' . $tag_cta . '>';

		$content .= '<div class="tidings-articles">';
		foreach ( $data->articles as $i => $article ) {
			if( $i > 0 ) {
				$content .= '<hr class="tidings-break">';
			}

			$photo   = $article->photo;
			$content .= '<div class="tidings-article' . ( ! empty( $photo ) ? ' tidings-article--has-photo' : '' ) . '">';

			// Photo, align left.
			if ( ! empty( $photo ) ) {
				$content .= '<div class="tidings-article-image-holder">';
				$content .= '<a href="' . esc_url( $article->url ) . '" class="tidings-article-image-link"><img src="' . esc_url( $photo ) . '" class="tidings-article-image"></a>';
				$content .= '</div>';
				$content .= '<div class="tidings-article-content-holder">';
			}

			$url_parsed = parse_url( $article->url );
			$url_host = $article->url;
			if( false !== $url_parsed ) {
				$url_host = $url_parsed['host'];
			}

			// Article content.
			$content .= '<' . $tag_article_headline . ' class="tidings-article-headline"><a href="' . esc_url( $article->url ) . '">' . $article->title . '</a></' . $tag_article_headline . '>';
			$content .= '<' . $tag_article_source . ' class="tidings-article-source"><span class="tidings-article-source-prefix">' . __( 'Source', 'tidings' ) . ': </span><a href="' . esc_url( $article->url ) . '" class="tidings-article-source-url">' . esc_html( $url_host ) . '</a></' . $tag_article_source . '>';
			$content .= '<div class="tidings-article-content">' . wpautop( $article->content ) . '</div>';

			// Closing div for photo.
			if ( ! empty( $photo ) ) {
				$content .= '</div>';
			}

			$content .= '</div>';
		}
		$content .= '<div>';

		// Outro.
		$content .= '<' . $tag_outro . ' class="tidings-outro">' . $data->outro . '</' . $tag_outro . '>';

		return $content;
	}

	public function get_default_post_status() {
		return $this->default_post_status;
	}

	public function get_default_post_category() {
		return isset( $this->options['publication_category'] ) ? esc_attr( $this->options['publication_category'] ) : '';
	}

	public function get_default_post_author() {
		return isset( $this->options['publication_author'] ) ? esc_attr( $this->options['publication_author'] ) : '';
	}

	/**
	 * Returns an instance of this class. An implementation of the singleton design pattern.
	 *
	 * @return   object    A reference to an instance of this class.
	 * @since    1.0.0
	 */
	public static function instance() {
		if ( null == self::$instance ) {
			self::$instance = new Tidings_Publisher();
		}

		return self::$instance;
	}
}
