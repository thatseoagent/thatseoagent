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
     * Add settings page under Settings menu.
     *
     * @since 1.3.0
     */
    public static function add_settings_page() {
        add_options_page(
            __( 'Lean SEO', 'lean-seo' ),
            __( 'Lean SEO', 'lean-seo' ),
            'manage_options',
            'lean-seo',
            array( __CLASS__, 'render_settings_page' )
        );
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
     * Add meta box
     */
    public static function add_meta_box() {
        $post_types = apply_filters('lean_seo_meta_box_post_types', array('post', 'page'));
        
        add_meta_box(
            'lean_seo_meta',
            '🔍 SEO Settings',
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

        $post_types = apply_filters( 'lean_seo_meta_box_post_types', array( 'post', 'page' ) );
        if ( ! in_array( $screen->post_type, (array) $post_types, true ) ) {
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
                SEO Title
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
            <p class="description">Leave blank to use the post title. Recommended: 50-60 characters.</p>
        </div>

        <div class="lean-seo-field">
            <label for="lean_seo_description">
                Meta Description
                <span class="lean-seo-counter" id="desc-counter">0/160</span>
            </label>
            <textarea 
                id="lean_seo_description" 
                name="lean_seo_description" 
                rows="3" 
                maxlength="160"
                placeholder="Leave blank to auto-generate from content..."
            ><?php echo esc_textarea($seo_desc); ?></textarea>
            <p class="description">Recommended: 150-160 characters. This appears in search results.</p>
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
                $submitted[$field] = $_POST['lean_seo_' . $field];
            }
        }

        Lean_SEO_Post_Seo::save_from_request($post_id, $submitted);
    }
}
