<?php
/**
 * The SEO meta box in the post editor: title and description fields with a
 * search result preview.
 *
 * Values go through ThatSeoAgent_Post_Seo, which owns the fields; this module is
 * the editor's form for them.
 *
 * @package ThatSeoAgent
 * @since 1.0.0
 * @since 1.19.0 Moved out of ThatSeoAgent_Admin.
 */

if (!defined('ABSPATH')) {
    exit;
}

class ThatSeoAgent_Meta_Box {

    /**
     * Register the hooks.
     *
     * @since 1.19.0
     */
    public static function register() {
        add_action('add_meta_boxes', array(__CLASS__, 'add'));
        add_action('save_post', array(__CLASS__, 'save'), 10, 1);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
    }

    /**
     * Add meta box
     */
    public static function add() {
        $post_types = ThatSeoAgent_Post_Seo::post_types();
        
        add_meta_box(
            'thatseoagent_meta',
            __( '🔍 SEO Settings', 'thatseoagent' ),
            array(__CLASS__, 'render'),
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
    public static function enqueue_assets( $hook_suffix ) {
        if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }

        if ( ! in_array( $screen->post_type, ThatSeoAgent_Post_Seo::post_types(), true ) ) {
            return;
        }

        wp_enqueue_style(
            'thatseoagent-meta-box',
            THATSEOAGENT_PLUGIN_URL . 'assets/admin-meta-box.css',
            array(),
            THATSEOAGENT_VERSION
        );

        wp_enqueue_script(
            'thatseoagent-meta-box',
            THATSEOAGENT_PLUGIN_URL . 'assets/admin-meta-box.js',
            array( 'jquery' ),
            THATSEOAGENT_VERSION,
            true
        );
    }

    /**
     * Render meta box
     */
    public static function render($post) {
        wp_nonce_field('thatseoagent_save', 'thatseoagent_nonce');

        $seo = ThatSeoAgent_Post_Seo::all($post);
        $seo_title = $seo['title'];
        $seo_desc = $seo['description'];
        ?>
        <div class="thatseoagent-field">
            <label for="thatseoagent_title">
                <?php esc_html_e( 'SEO Title', 'thatseoagent' ); ?>
                <span class="thatseoagent-counter" id="title-counter">0/60</span>
            </label>
            <input 
                type="text" 
                id="thatseoagent_title" 
                name="thatseoagent_title" 
                value="<?php echo esc_attr($seo_title); ?>" 
                maxlength="70"
                placeholder="<?php echo esc_attr($post->post_title); ?>"
            >
            <p class="description"><?php esc_html_e( 'Leave blank to use the post title. Recommended: 50-60 characters.', 'thatseoagent' ); ?></p>
        </div>

        <div class="thatseoagent-field">
            <label for="thatseoagent_description">
                <?php esc_html_e( 'Meta Description', 'thatseoagent' ); ?>
                <span class="thatseoagent-counter" id="desc-counter">0/160</span>
            </label>
            <textarea 
                id="thatseoagent_description" 
                name="thatseoagent_description" 
                rows="3" 
                maxlength="160"
                placeholder="<?php esc_attr_e( 'Leave blank to auto-generate from content...', 'thatseoagent' ); ?>"
                data-thatseoagent-generated="<?php echo esc_attr( ThatSeoAgent_Description::for_post( $post ) ); ?>"
            ><?php echo esc_textarea($seo_desc); ?></textarea>
            <p class="description"><?php esc_html_e( 'Recommended: 150-160 characters. This appears in search results.', 'thatseoagent' ); ?></p>
        </div>

        <div class="thatseoagent-preview">
            <div class="thatseoagent-preview-title" id="preview-title"><?php echo esc_html($seo_title ?: $post->post_title); ?></div>
            <div class="thatseoagent-preview-url"><?php echo esc_url(get_permalink($post)); ?></div>
            <div class="thatseoagent-preview-desc" id="preview-desc"><?php echo esc_html(ThatSeoAgent_Description::for_post($post)); ?></div>
        </div>

        <?php
    }

    /**
     * Save meta box data
     */
    public static function save($post_id) {
        // Verify nonce
        $nonce = isset($_POST['thatseoagent_nonce']) ? sanitize_text_field(wp_unslash($_POST['thatseoagent_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'thatseoagent_save')) {
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
            if (isset($_POST['thatseoagent_' . $field])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Passed on raw by design: ThatSeoAgent_Post_Seo::save_from_request() unslashes and sanitizes, and it must receive the still-slashed value to do so correctly. Sanitizing here would double-process it.
                $submitted[$field] = $_POST['thatseoagent_' . $field];
            }
        }

        ThatSeoAgent_Post_Seo::save_from_request($post_id, $submitted);
    }
}
