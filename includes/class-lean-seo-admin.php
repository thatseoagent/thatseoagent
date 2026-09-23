<?php
/**
 * Admin Meta Box Handler
 *
 * @package Lean_SEO
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Lean_SEO_Admin {

    /**
     * Register the settings page sections and fields.
     *
     * The option itself is registered by Lean_SEO_Settings.
     *
     * @since 1.3.0
     */
    public static function register_settings() {
        add_settings_section(
            'lean_seo_schema_section',
            __( 'Default author', 'lean-seo' ),
            function () {
                echo '<p>' . esc_html__( 'Credited on posts that have no author assigned. Leave blank to credit the site itself.', 'lean-seo' ) . '</p>';
            },
            'lean_seo_settings'
        );

        $fields = array(
            'author_name' => __( 'Name', 'lean-seo' ),
            'author_url'  => __( 'Web address', 'lean-seo' ),
            'author_type' => __( 'The author is', 'lean-seo' ),
        );

        foreach ( $fields as $key => $label ) {
            add_settings_field(
                'lean_seo_schema_' . $key,
                $label,
                array( __CLASS__, 'render_schema_field' ),
                'lean_seo_settings',
                'lean_seo_schema_section',
                array( 'key' => $key, 'label' => $label )
            );
        }
    }

    /**
     * Render a single schema settings field.
     *
     * @since 1.3.0
     * @param array $args Field arguments.
     */
    public static function render_schema_field( $args ) {
        // Saved values only; the defaults are placeholders. Rendering the
        // defaults as values saved them on the first submit, and a saved
        // author name makes every authorless post credit a Person named after
        // the site — the fallback this setting exists to avoid.
        $saved    = get_option( 'lean_seo_schema', array() );
        $defaults = Lean_SEO_Schema::get_publisher_defaults();
        $key      = $args['key'];
        $value    = isset( $saved[ $key ] ) ? $saved[ $key ] : '';

        if ( 'author_type' === $key ) {
            printf(
                '<select name="lean_seo_schema[%s]" id="lean_seo_schema_%s">',
                esc_attr( $key ),
                esc_attr( $key )
            );
            $types = array(
                'Person'       => __( 'A person', 'lean-seo' ),
                'Organization' => __( 'An organization', 'lean-seo' ),
            );
            foreach ( $types as $type => $label ) {
                printf(
                    '<option value="%s"%s>%s</option>',
                    esc_attr( $type ),
                    selected( $value, $type, false ),
                    esc_html( $label )
                );
            }
            echo '</select>';
        } else {
            printf(
                '<input type="%s" name="lean_seo_schema[%s]" id="lean_seo_schema_%s" value="%s" placeholder="%s" class="regular-text">',
                'author_url' === $key ? 'url' : 'text',
                esc_attr( $key ),
                esc_attr( $key ),
                esc_attr( $value ),
                esc_attr( $defaults[ $key ] )
            );
        }
    }

    /**
     * Sanitize schema settings.
     *
     * @since 1.3.0
     * @param array $input Raw input.
     * @return array Sanitized values.
     */
    public static function sanitize_schema_settings( $input ) {
        $clean = array();

        if ( ! empty( $input['author_name'] ) ) {
            $clean['author_name'] = sanitize_text_field( $input['author_name'] );
        }
        if ( ! empty( $input['author_url'] ) ) {
            $clean['author_url'] = esc_url_raw( $input['author_url'] );
        }
        if ( ! empty( $input['author_type'] ) && in_array( $input['author_type'], array( 'Person', 'Organization' ), true ) ) {
            $clean['author_type'] = $input['author_type'];
        }

        return $clean;
    }

    /**
     * Hook suffix of the settings page, as passed to admin_enqueue_scripts.
     *
     * @since 1.15.0
     */
    const PAGE_HOOK = 'toplevel_page_lean-seo';

    /**
     * Post types that get the SEO meta box and the registered meta fields.
     *
     * Defaults to every post type with an editing screen. The rest of the
     * plugin already treats custom post types as first-class — they appear in
     * the sitemap, they get WebPage and BreadcrumbList schema, and a stored
     * `_lean_seo_description` is honoured on the front end for any post type
     * — so limiting the editing UI to posts and pages (the default before
     * 1.12.0) left no way to enter the values the plugin was already reading.
     *
     * Attachments are excluded: the media modal has no meta box.
     *
     * @since 1.12.0
     * @return array<int, string>
     */
    public static function get_meta_box_post_types() {
        $post_types = get_post_types(
            array(
                'public'  => true,
                'show_ui' => true,
            ),
            'names'
        );

        unset( $post_types['attachment'] );

        /**
         * Filter the post types that get the SEO meta box.
         *
         * @since 1.0.0
         * @param array<int, string> $post_types Post type names.
         */
        return (array) apply_filters( 'lean_seo_meta_box_post_types', array_values( $post_types ) );
    }

    /**
     * Bulk action on the posts list: write the generated description.
     */
    const BULK_ACTION = 'lean_seo_generate_descriptions';

    /**
     * Add the bulk action to every post type with the meta box.
     *
     * Runs on admin_init, after `init` has registered the post types.
     *
     * @since 1.16.0
     */
    public static function register_bulk_actions() {
        foreach ( self::get_meta_box_post_types() as $post_type ) {
            add_filter( 'bulk_actions-edit-' . $post_type, array( __CLASS__, 'add_bulk_action' ) );
            add_filter( 'handle_bulk_actions-edit-' . $post_type, array( __CLASS__, 'handle_bulk_action' ), 10, 3 );
        }
    }

    /**
     * Offer the bulk action.
     *
     * @since 1.16.0
     * @param array $actions Bulk actions.
     * @return array
     */
    public static function add_bulk_action( $actions ) {
        $actions[ self::BULK_ACTION ] = __( 'Generate meta description', 'lean-seo' );
        return $actions;
    }

    /**
     * Save the generated description of each selected post that has none.
     *
     * The description saved is exactly the one the front end was already
     * emitting — Lean_SEO_Description::generate() — so the page does not
     * change; the value just stops depending on the content staying as it is.
     * Posts with a description of their own are left alone. edit.php has
     * already checked the bulk-posts nonce by the time this runs.
     *
     * @since 1.16.0
     * @param string $redirect Redirect URL.
     * @param string $action   Chosen action.
     * @param array  $post_ids Selected post IDs.
     * @return string
     */
    public static function handle_bulk_action( $redirect, $action, $post_ids ) {
        if ( self::BULK_ACTION !== $action ) {
            return $redirect;
        }

        $generated = 0;
        $skipped   = 0;

        foreach ( (array) $post_ids as $post_id ) {
            $post_id = (int) $post_id;

            if ( ! current_user_can( 'edit_post', $post_id ) || '' !== Lean_SEO_Post_Seo::get( $post_id, 'description' ) ) {
                $skipped++;
                continue;
            }

            $description = Lean_SEO_Description::generate( $post_id );
            Lean_SEO_Content::forget( $post_id );

            if ( '' === $description ) {
                $skipped++;
                continue;
            }

            Lean_SEO_Post_Seo::save( $post_id, array( 'description' => $description ) );
            $generated++;
        }

        return add_query_arg(
            array(
                'lean_seo_generated' => $generated,
                'lean_seo_skipped'   => $skipped,
            ),
            $redirect
        );
    }

    /**
     * Report the result of the bulk action.
     *
     * @since 1.16.0
     */
    public static function bulk_action_notice() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only: counts from our own redirect.
        if ( ! isset( $_GET['lean_seo_generated'] ) ) {
            return;
        }

        $generated = absint( $_GET['lean_seo_generated'] );
        $skipped   = isset( $_GET['lean_seo_skipped'] ) ? absint( $_GET['lean_seo_skipped'] ) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $message = sprintf(
            /* translators: %d: number of posts. */
            _n( 'Meta description saved for %d post.', 'Meta description saved for %d posts.', $generated, 'lean-seo' ),
            $generated
        );

        if ( $skipped ) {
            $message .= ' ' . sprintf(
                /* translators: %d: number of posts. */
                _n( '%d skipped: it already had one or has no content to describe.', '%d skipped: they already had one or have no content to describe.', $skipped, 'lean-seo' ),
                $skipped
            );
        }

        printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $message ) );
    }

    /**
     * Add meta box
     */
    public static function add_meta_box() {
        $post_types = self::get_meta_box_post_types();
        
        add_meta_box(
            'lean_seo_meta',
            __( '🔍 SEO Settings', 'lean-seo' ),
            array(__CLASS__, 'render_meta_box'),
            $post_types,
            'normal',
            'high'
        );
    }

    /**
     * Enqueue the meta box stylesheet and script on post edit screens.
     *
     * @since 1.8.0
     * @param string $hook_suffix Current admin page hook.
     */
    public static function enqueue_meta_box_assets( $hook_suffix ) {
        if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }

        if ( ! in_array( $screen->post_type, self::get_meta_box_post_types(), true ) ) {
            return;
        }

        wp_enqueue_style(
            'lean-seo-meta-box',
            LEAN_SEO_PLUGIN_URL . 'assets/admin-meta-box.css',
            array(),
            LEAN_SEO_VERSION
        );

        wp_enqueue_script(
            'lean-seo-meta-box',
            LEAN_SEO_PLUGIN_URL . 'assets/admin-meta-box.js',
            array( 'jquery' ),
            LEAN_SEO_VERSION,
            true
        );
    }

    /**
     * Render meta box
     */
    public static function render_meta_box($post) {
        wp_nonce_field('lean_seo_save', 'lean_seo_nonce');

        $seo = Lean_SEO_Post_Seo::all($post);
        $seo_title = $seo['title'];
        $seo_desc = $seo['description'];
        ?>
        <div class="lean-seo-field">
            <label for="lean_seo_title">
                <?php esc_html_e( 'SEO Title', 'lean-seo' ); ?>
                <span class="lean-seo-counter" id="title-counter">0/60</span>
            </label>
            <input 
                type="text" 
                id="lean_seo_title" 
                name="lean_seo_title" 
                value="<?php echo esc_attr($seo_title); ?>" 
                maxlength="70"
                placeholder="<?php echo esc_attr($post->post_title); ?>"
            >
            <p class="description"><?php esc_html_e( 'Leave blank to use the post title. Recommended: 50-60 characters.', 'lean-seo' ); ?></p>
        </div>

        <div class="lean-seo-field">
            <label for="lean_seo_description">
                <?php esc_html_e( 'Meta Description', 'lean-seo' ); ?>
                <span class="lean-seo-counter" id="desc-counter">0/160</span>
            </label>
            <textarea 
                id="lean_seo_description" 
                name="lean_seo_description" 
                rows="3" 
                maxlength="160"
                placeholder="<?php esc_attr_e( 'Leave blank to auto-generate from content...', 'lean-seo' ); ?>"
                data-lean-seo-generated="<?php echo esc_attr( Lean_SEO_Description::for_post( $post ) ); ?>"
            ><?php echo esc_textarea($seo_desc); ?></textarea>
            <p class="description"><?php esc_html_e( 'Recommended: 150-160 characters. This appears in search results.', 'lean-seo' ); ?></p>
        </div>

        <div class="lean-seo-preview">
            <div class="lean-seo-preview-title" id="preview-title"><?php echo esc_html($seo_title ?: $post->post_title); ?></div>
            <div class="lean-seo-preview-url"><?php echo esc_url(get_permalink($post)); ?></div>
            <div class="lean-seo-preview-desc" id="preview-desc"><?php echo esc_html(Lean_SEO_Description::for_post($post)); ?></div>
        </div>

        <?php
    }

    /**
     * Save meta box data
     */
    public static function save_meta($post_id) {
        // Verify nonce
        $nonce = isset($_POST['lean_seo_nonce']) ? sanitize_text_field(wp_unslash($_POST['lean_seo_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'lean_seo_save')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Only fields actually present in the submission are touched; a
        // field absent from the form is left as it was.
        $submitted = array();
        foreach (array('title', 'description') as $field) {
            if (isset($_POST['lean_seo_' . $field])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Passed on raw by design: Lean_SEO_Post_Seo::save_from_request() unslashes and sanitizes, and it must receive the still-slashed value to do so correctly. Sanitizing here would double-process it.
                $submitted[$field] = $_POST['lean_seo_' . $field];
            }
        }

        Lean_SEO_Post_Seo::save_from_request($post_id, $submitted);
    }
}
