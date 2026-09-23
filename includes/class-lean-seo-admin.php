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
     * Register settings page and fields.
     *
     * @since 1.3.0
     */
    public static function register_settings() {
        register_setting( 'lean_seo_settings', 'lean_seo_schema', array(
            'type'              => 'array',
            'sanitize_callback' => array( __CLASS__, 'sanitize_schema_settings' ),
            'default'           => array(),
        ) );

        add_settings_section(
            'lean_seo_schema_section',
            __( 'Schema / Publisher Defaults', 'lean-seo' ),
            function () {
                echo '<p>' . esc_html__( 'Fallback author used when a post has no WordPress author assigned.', 'lean-seo' ) . '</p>';
            },
            'lean_seo_settings'
        );

        $fields = array(
            'author_name' => __( 'Author Name', 'lean-seo' ),
            'author_url'  => __( 'Author URL', 'lean-seo' ),
            'author_type' => __( 'Author Type', 'lean-seo' ),
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
        $defaults = Lean_SEO_Schema::get_publisher_defaults();
        $key      = $args['key'];
        $value    = $defaults[ $key ];

        if ( 'author_type' === $key ) {
            printf(
                '<select name="lean_seo_schema[%s]" id="lean_seo_schema_%s">',
                esc_attr( $key ),
                esc_attr( $key )
            );
            foreach ( array( 'Person', 'Organization' ) as $type ) {
                printf(
                    '<option value="%s"%s>%s</option>',
                    esc_attr( $type ),
                    selected( $value, $type, false ),
                    esc_html( $type )
                );
            }
            echo '</select>';
        } else {
            printf(
                '<input type="%s" name="lean_seo_schema[%s]" id="lean_seo_schema_%s" value="%s" class="regular-text">',
                'author_url' === $key ? 'url' : 'text',
                esc_attr( $key ),
                esc_attr( $key ),
                esc_attr( $value )
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
     * Add the settings page as a top-level admin menu.
     *
     * @since 1.3.0
     * @since 1.15.0 Top-level menu instead of a submenu of Settings.
     */
    public static function add_settings_page() {
        add_menu_page(
            __( 'Lean SEO', 'lean-seo' ),
            __( 'Lean SEO', 'lean-seo' ),
            'manage_options',
            'lean-seo',
            array( __CLASS__, 'render_settings_page' ),
            'dashicons-search',
            81
        );
    }

    /**
     * Send the old Settings → Lean SEO URL to the new page.
     *
     * Keeps bookmarks and links from before 1.15.0 working.
     *
     * @since 1.15.0
     */
    public static function redirect_legacy_settings_url() {
        global $pagenow;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect.
        if ( 'options-general.php' !== $pagenow || ! isset( $_GET['page'] ) || 'lean-seo' !== $_GET['page'] ) {
            return;
        }

        wp_safe_redirect( admin_url( 'admin.php?page=lean-seo' ) );
        exit;
    }

    /**
     * Render the settings page.
     *
     * @since 1.3.0
     */
    public static function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Lean SEO Settings', 'lean-seo' ); ?></h1>
            <?php
            // Core prints these by itself only on pages under Settings.
            settings_errors();
            ?>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'lean_seo_settings' );
                do_settings_sections( 'lean_seo_settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

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
