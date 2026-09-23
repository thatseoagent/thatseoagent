<?php
/**
 * What the author of a post is, beyond a name: their job title, and their
 * profiles on other sites.
 *
 * Google's guidance on helpful content asks whether it is clear who wrote
 * a page and what makes them someone to listen to; schema.org says so with
 * a Person's `jobTitle` and `sameAs`. WordPress's profile has a name, a
 * biography and one website, so the two missing pieces get their own fields
 * in the profile screen, for people who can publish. They feed the Person
 * node of every post the user wrote.
 *
 * Stored in user meta, protected (leading underscore) and exposed to the
 * REST API for people who may edit that user.
 *
 * @package ThatSeoAgent
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Author_Profile {

    /**
     * User meta holding the job title.
     */
    const JOB_TITLE_KEY = '_thatseoagent_job_title';

    /**
     * User meta holding the profiles elsewhere, one URL per entry.
     */
    const PROFILES_KEY = '_thatseoagent_profiles';

    /**
     * Profiles kept at most.
     */
    const MAX_PROFILES = 10;

    /**
     * Register the hooks.
     *
     * @since 2.3.0
     */
    public static function register() {
        add_action( 'init', array( __CLASS__, 'register_meta' ) );

        if ( is_admin() ) {
            add_action( 'show_user_profile', array( __CLASS__, 'render' ) );
            add_action( 'edit_user_profile', array( __CLASS__, 'render' ) );
            add_action( 'personal_options_update', array( __CLASS__, 'save' ) );
            add_action( 'edit_user_profile_update', array( __CLASS__, 'save' ) );
        }
    }

    /**
     * Every user meta key this module writes. uninstall.php reads it.
     *
     * @since 2.3.0
     * @return array<int, string>
     */
    public static function keys() {
        return array( self::JOB_TITLE_KEY, self::PROFILES_KEY );
    }

    /**
     * Expose the fields to the REST API.
     *
     * @since 2.3.0
     */
    public static function register_meta() {
        $auth = function ( $allowed, $meta_key, $user_id ) {
            return current_user_can( 'edit_user', $user_id );
        };

        register_meta(
            'user',
            self::JOB_TITLE_KEY,
            array(
                'type'              => 'string',
                'single'            => true,
                'default'           => '',
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback'     => $auth,
            )
        );

        register_meta(
            'user',
            self::PROFILES_KEY,
            array(
                'type'              => 'array',
                'single'            => true,
                'default'           => array(),
                'show_in_rest'      => array(
                    'schema' => array(
                        'type'  => 'array',
                        'items' => array(
                            'type'   => 'string',
                            'format' => 'uri',
                        ),
                    ),
                ),
                'sanitize_callback' => array( __CLASS__, 'sanitize_profiles' ),
                'auth_callback'     => $auth,
            )
        );
    }

    /**
     * A user's job title, or ''.
     *
     * @since 2.3.0
     * @param int $user_id User ID.
     * @return string
     */
    public static function job_title( $user_id ) {
        return trim( (string) get_user_meta( (int) $user_id, self::JOB_TITLE_KEY, true ) );
    }

    /**
     * A user's profiles on other sites.
     *
     * @since 2.3.0
     * @param int $user_id User ID.
     * @return array<int, string>
     */
    public static function profiles( $user_id ) {
        return self::sanitize_profiles( get_user_meta( (int) $user_id, self::PROFILES_KEY, true ) );
    }

    /**
     * Profiles as stored: absolute http(s) URLs, each once, none on this
     * site — a profile is somewhere else by definition. Lines that are not
     * addresses are dropped.
     *
     * @since 2.3.0
     * @param mixed $value Array of URLs, or text with one per line.
     * @return array<int, string>
     */
    public static function sanitize_profiles( $value ) {
        $lines = is_array( $value ) ? $value : preg_split( '/[\r\n]+/', (string) $value );
        $home  = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
        $urls  = array();

        foreach ( (array) $lines as $line ) {
            $line = trim( (string) $line );

            // Only what is already an address: esc_url_raw() would turn
            // "not a url" into http://not%20a%20url.
            if ( ! preg_match( '#^https?://[^\s/]+\.[^\s/]+#i', $line ) ) {
                continue;
            }

            $url  = esc_url_raw( $line, array( 'http', 'https' ) );
            $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

            if ( '' === $url || '' === $host || $host === $home ) {
                continue;
            }

            $urls[ untrailingslashit( $url ) ] = $url;
        }

        return array_slice( array_values( $urls ), 0, self::MAX_PROFILES );
    }

    /**
     * Whether a user gets the fields: people who can publish something.
     *
     * @since 2.3.0
     * @param WP_User $user User.
     * @return bool
     */
    private static function applies( WP_User $user ) {
        return user_can( $user, 'edit_posts' );
    }

    /**
     * The fields on the profile screen.
     *
     * @since 2.3.0
     * @param WP_User $user User being edited.
     */
    public static function render( $user ) {
        if ( ! $user instanceof WP_User || ! self::applies( $user ) ) {
            return;
        }

        wp_nonce_field( 'thatseoagent_author_profile', 'thatseoagent_author_profile_nonce' );
        ?>
        <h2><?php esc_html_e( 'As an author', 'thatseoagent' ); ?></h2>
        <p><?php esc_html_e( 'Shown to search engines and AI assistants with every post you publish, so they can tell who wrote it and where else you are known.', 'thatseoagent' ); ?></p>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="thatseoagent_job_title"><?php esc_html_e( 'Job title', 'thatseoagent' ); ?></label></th>
                <td>
                    <input type="text" name="thatseoagent_job_title" id="thatseoagent_job_title" value="<?php echo esc_attr( self::job_title( $user->ID ) ); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e( 'What you do, as you would put it on a business card: "Mechanical engineer", "Head of sales".', 'thatseoagent' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="thatseoagent_profiles"><?php esc_html_e( 'Profiles elsewhere', 'thatseoagent' ); ?></label></th>
                <td>
                    <textarea name="thatseoagent_profiles" id="thatseoagent_profiles" rows="4" class="large-text code"><?php echo esc_textarea( implode( "\n", self::profiles( $user->ID ) ) ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'One address per line: LinkedIn, a professional association, your own site. Addresses on this site are left out.', 'thatseoagent' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save the fields.
     *
     * @since 2.3.0
     * @param int $user_id User being saved.
     */
    public static function save( $user_id ) {
        $nonce = isset( $_POST['thatseoagent_author_profile_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['thatseoagent_author_profile_nonce'] ) ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'thatseoagent_author_profile' ) || ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }

        $job_title = isset( $_POST['thatseoagent_job_title'] ) ? sanitize_text_field( wp_unslash( $_POST['thatseoagent_job_title'] ) ) : '';
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized as URLs, one per line, by sanitize_profiles().
        $profiles = isset( $_POST['thatseoagent_profiles'] ) ? self::sanitize_profiles( wp_unslash( $_POST['thatseoagent_profiles'] ) ) : array();

        if ( '' === $job_title ) {
            delete_user_meta( $user_id, self::JOB_TITLE_KEY );
        } else {
            update_user_meta( $user_id, self::JOB_TITLE_KEY, $job_title );
        }

        if ( ! $profiles ) {
            delete_user_meta( $user_id, self::PROFILES_KEY );
        } else {
            update_user_meta( $user_id, self::PROFILES_KEY, $profiles );
        }
    }
}
