<?php
/**
 * IndexNow integration for ThatSeoAgent.
 *
 * Automatically notifies search engines (Bing, Yandex, etc.) when content
 * is published or updated via the IndexNow protocol.
 *
 * @package ThatSeoAgent
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ThatSeoAgent_IndexNow {

	/**
	 * Option key for the IndexNow API key.
	 */
	const OPTION_KEY = 'thatseoagent_indexnow_key';

	/**
	 * IndexNow API endpoint.
	 */
	const API_URL = 'https://api.indexnow.org/indexnow';

	/**
	 * WP-Cron hook that submits queued URLs.
	 */
	const CRON_HOOK = 'thatseoagent_indexnow_submit';

	/**
	 * The option as ThatSeoAgent_Settings registers it: type, sanitizer,
	 * default and REST schema.
	 *
	 * @since 1.20.0 Moved from ThatSeoAgent_Settings::definitions().
	 * @return array{type: string, sanitize: callable, default: mixed, schema: array}
	 */
	public static function setting(): array {
		return array(
			'type'     => 'string',
			'sanitize' => array( __CLASS__, 'sanitize_key_setting' ),
			'default'  => '',
			'schema'   => array( 'type' => 'string' ),
		);
	}

	/**
	 * Register the hooks.
	 *
	 * @since 1.7.1 As init().
	 */
	public static function register(): void {
		add_action( 'save_post', array( __CLASS__, 'on_post_save' ), 10, 3 );
		add_action( self::CRON_HOOK, array( __CLASS__, 'submit_urls' ) );
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

		$requested = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';
		$requested = untrailingslashit( (string) wp_parse_url( $requested, PHP_URL_PATH ) );

		if ( '' === $requested ) {
			return;
		}

		// Shape test before any option read. This callback runs on every
		// front-end request, and reading the key first cost two queries on
		// each one — on sites that never configured IndexNow as much as on
		// those that did. An IndexNow key file is always "<key>.txt" with a
		// key of 8-128 characters from [a-zA-Z0-9-].
		if ( ! preg_match( '#/[a-zA-Z0-9-]{8,128}\.txt$#', $requested ) ) {
			return;
		}

		$key = self::get_api_key();
		if ( '' === $key ) {
			return;
		}

		$expected = untrailingslashit( (string) wp_parse_url( self::get_key_location( $key ), PHP_URL_PATH ) );

		if ( $requested !== $expected ) {
			return;
		}

		nocache_headers();
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=UTF-8' );
		echo esc_html( $key );
		exit;
	}

	/**
	 * Register the IndexNow settings section on the ThatSeoAgent settings page.
	 *
	 * @since 1.7.1
	 */
	public static function register_settings(): void {
		add_settings_section(
			'thatseoagent_indexnow_section',
			__( 'IndexNow', 'thatseoagent' ),
			function () {
				echo '<p>' . esc_html__( 'Notify Bing, Yandex and other IndexNow engines whenever a post is published or updated. Leave the key blank to disable.', 'thatseoagent' ) . '</p>';
			},
			'thatseoagent_settings'
		);

		add_settings_field(
			'thatseoagent_indexnow_key',
			__( 'API Key', 'thatseoagent' ),
			array( __CLASS__, 'render_key_field' ),
			'thatseoagent_settings',
			'thatseoagent_indexnow_section'
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
			id="thatseoagent_indexnow_key"
			value="<?php echo esc_attr( $key ); ?>"
			class="regular-text code"
			placeholder="<?php echo esc_attr( $suggested ); ?>"
		>
		<p class="description">
			<?php esc_html_e( '8–128 characters, letters, numbers and dashes only.', 'thatseoagent' ); ?>
			<?php if ( ! $key ) : ?>
				<br><?php esc_html_e( 'Suggested key:', 'thatseoagent' ); ?> <code><?php echo esc_html( $suggested ); ?></code>
			<?php else : ?>
				<br><?php esc_html_e( 'Verification file:', 'thatseoagent' ); ?>
				<a href="<?php echo esc_url( self::get_key_location( $key ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( self::get_key_location( $key ) ); ?></a>
				<?php esc_html_e( '(served automatically by this plugin)', 'thatseoagent' ); ?>
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
			// add_settings_error() lives in wp-admin and is not loaded when the
			// option is saved through the REST API.
			if ( function_exists( 'add_settings_error' ) ) {
				add_settings_error(
					self::OPTION_KEY,
					'thatseoagent_indexnow_key_length',
					__( 'The IndexNow key must be between 8 and 128 characters.', 'thatseoagent' )
				);
			}

			return (string) get_option( self::OPTION_KEY, '' );
		}

		return $key;
	}

	/**
	 * Get the configured API key.
	 *
	 * @return string API key or empty string.
	 */
	public static function get_api_key(): string {
		return (string) get_option( self::OPTION_KEY, '' );
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
		if ( ! wp_next_scheduled( self::CRON_HOOK, $args ) ) {
			wp_schedule_single_event( time() + 30, self::CRON_HOOK, $args );
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
			return new \WP_Error( 'indexnow_no_key', __( 'IndexNow API key not configured.', 'thatseoagent' ) );
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
			/* translators: %d: HTTP status code returned by the IndexNow API. */
			sprintf( __( 'IndexNow API returned %d', 'thatseoagent' ), $code )
		);
	}
}
