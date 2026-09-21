<?php
/**
 * IndexNow integration for Lean SEO.
 *
 * Automatically notifies search engines (Bing, Yandex, etc.) when content
 * is published or updated via the IndexNow protocol.
 *
 * @package LeanSEO
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lean_SEO_IndexNow {

	/**
	 * Option key for the IndexNow API key.
	 */
	const OPTION_KEY = 'lean_seo_indexnow_key';

	/**
	 * IndexNow API endpoint.
	 */
	const API_URL = 'https://api.indexnow.org/indexnow';

	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		add_action( 'save_post', array( __CLASS__, 'on_post_save' ), 10, 3 );
		add_action( 'lean_seo_indexnow_submit', array( __CLASS__, 'submit_urls' ) );
		add_action( 'init', array( __CLASS__, 'maybe_serve_key_file' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Public URL where the key verification file is served.
	 *
	 * IndexNow requires this file to exist and contain the key verbatim.
	 *
	 * @since 1.7.1
	 * @param string $key API key.
	 * @return string
	 */
	public static function get_key_location( string $key ): string {
		return home_url( '/' . $key . '.txt' );
	}

	/**
	 * Serve the IndexNow key verification file.
	 *
	 * The plugin has no way to drop a real file in the web root, so the
	 * key file is served dynamically from the URL advertised as
	 * keyLocation. Without this, IndexNow rejects every submission.
	 *
	 * @since 1.7.1
	 */
	public static function maybe_serve_key_file(): void {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		$key = self::get_api_key();
		if ( '' === $key ) {
			return;
		}

		$requested = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$requested = (string) wp_parse_url( $requested, PHP_URL_PATH );
		$expected  = (string) wp_parse_url( self::get_key_location( $key ), PHP_URL_PATH );

		if ( '' === $requested || untrailingslashit( $requested ) !== untrailingslashit( $expected ) ) {
			return;
		}

		nocache_headers();
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=UTF-8' );
		echo esc_html( $key );
		exit;
	}

	/**
	 * Register the IndexNow settings section on the Lean SEO settings page.
	 *
	 * @since 1.7.1
	 */
	public static function register_settings(): void {
		register_setting( 'lean_seo_settings', self::OPTION_KEY, array(
			'type'              => 'string',
			'sanitize_callback' => array( __CLASS__, 'sanitize_key_setting' ),
			'default'           => '',
		) );

		add_settings_section(
			'lean_seo_indexnow_section',
			__( 'IndexNow', 'lean-seo' ),
			function () {
				echo '<p>' . esc_html__( 'Notify Bing, Yandex and other IndexNow engines whenever a post is published or updated. Leave the key blank to disable.', 'lean-seo' ) . '</p>';
			},
			'lean_seo_settings'
		);

		add_settings_field(
			'lean_seo_indexnow_key',
			__( 'API Key', 'lean-seo' ),
			array( __CLASS__, 'render_key_field' ),
			'lean_seo_settings',
			'lean_seo_indexnow_section'
		);
	}

	/**
	 * Render the API key field.
	 *
	 * @since 1.7.1
	 */
	public static function render_key_field(): void {
		$key       = self::get_api_key();
		$suggested = $key ? $key : self::generate_key();
		?>
		<input
			type="text"
			name="<?php echo esc_attr( self::OPTION_KEY ); ?>"
			id="lean_seo_indexnow_key"
			value="<?php echo esc_attr( $key ); ?>"
			class="regular-text code"
			placeholder="<?php echo esc_attr( $suggested ); ?>"
		>
		<p class="description">
			<?php esc_html_e( '8–128 characters, letters, numbers and dashes only.', 'lean-seo' ); ?>
			<?php if ( ! $key ) : ?>
				<br><?php esc_html_e( 'Suggested key:', 'lean-seo' ); ?> <code><?php echo esc_html( $suggested ); ?></code>
			<?php else : ?>
				<br><?php esc_html_e( 'Verification file:', 'lean-seo' ); ?>
				<a href="<?php echo esc_url( self::get_key_location( $key ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( self::get_key_location( $key ) ); ?></a>
				<?php esc_html_e( '(served automatically by this plugin)', 'lean-seo' ); ?>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Generate a random IndexNow-compatible key.
	 *
	 * @since 1.7.1
	 * @return string
	 */
	public static function generate_key(): string {
		return strtolower( wp_generate_password( 32, false, false ) );
	}

	/**
	 * Sanitize the API key.
	 *
	 * IndexNow only accepts [a-zA-Z0-9-] of length 8–128. Anything else is
	 * rejected outright rather than silently mangled into an invalid key.
	 *
	 * @since 1.7.1
	 * @param mixed $input Raw value.
	 * @return string
	 */
	public static function sanitize_key_setting( $input ): string {
		$key = preg_replace( '/[^a-zA-Z0-9-]/', '', (string) $input );

		if ( '' === $key ) {
			return '';
		}

		if ( strlen( $key ) < 8 || strlen( $key ) > 128 ) {
			add_settings_error(
				self::OPTION_KEY,
				'lean_seo_indexnow_key_length',
				__( 'The IndexNow key must be between 8 and 128 characters.', 'lean-seo' )
			);

			return (string) get_option( self::OPTION_KEY, '' );
		}

		return $key;
	}

	/**
	 * Get the configured API key.
	 *
	 * Falls back to legacy theme option if present.
	 *
	 * @return string API key or empty string.
	 */
	public static function get_api_key(): string {
		$key = get_option( self::OPTION_KEY, '' );

		// Fallback: migrate from legacy theme option.
		if ( empty( $key ) ) {
			$legacy_key = get_option( 'sarai_chinwag_indexnow_key', '' );
			if ( ! empty( $legacy_key ) ) {
				update_option( self::OPTION_KEY, $legacy_key );
				$key = $legacy_key;
			}
		}

		return $key;
	}

	/**
	 * Handle post save — submit URL to IndexNow.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 */
	public static function on_post_save( int $post_id, \WP_Post $post, bool $update ): void {
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! is_post_type_viewable( $post->post_type ) ) {
			return;
		}

		// No key configured: nothing to submit, so don't queue work either.
		if ( '' === self::get_api_key() ) {
			return;
		}

		$post_url = get_permalink( $post_id );
		if ( ! $post_url ) {
			return;
		}

		// Submit out of band. Doing the HTTP call inline would hold the
		// editor's save request open for the duration of the request to the
		// IndexNow API (up to the timeout below).
		$args = array( array( $post_url ) );
		if ( ! wp_next_scheduled( 'lean_seo_indexnow_submit', $args ) ) {
			wp_schedule_single_event( time() + 30, 'lean_seo_indexnow_submit', $args );
		}
	}

	/**
	 * Submit URLs to IndexNow.
	 *
	 * @param array $urls List of URLs to submit.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function submit_urls( array $urls ) {
		$key = self::get_api_key();
		if ( empty( $key ) ) {
			return new \WP_Error( 'indexnow_no_key', __( 'IndexNow API key not configured.', 'lean-seo' ) );
		}

		$host         = wp_parse_url( home_url(), PHP_URL_HOST );
		$key_location = self::get_key_location( $key );

		$data = array(
			'host'        => $host,
			'key'         => $key,
			'keyLocation' => $key_location,
			'urlList'     => array_values( $urls ),
		);

		$response = wp_remote_post(
			self::API_URL,
			array(
				'body'    => wp_json_encode( $data ),
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		return new \WP_Error(
			'indexnow_api_error',
			sprintf( __( 'IndexNow API returned %d', 'lean-seo' ), $code )
		);
	}
}
