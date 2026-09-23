<?php
/**
 * The "Product catalog" section of the settings: every active post type, a
 * checkbox to mark it as a catalog, and the field mapping, with taxonomies
 * and meta keys detected from the site's own data.
 *
 * @package ThatSeoAgent
 * @since 1.16.0 As ThatSeoAgent_Product_Admin.
 * @since 1.19.0 The report moved to ThatSeoAgent_Product_Report.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Product_Settings {

    /**
     * Register the hooks.
     *
     * @since 1.16.0
     */
    public static function register() {
        add_action( 'admin_init', array( __CLASS__, 'register_section' ) );
    }

    /**
     * Add the settings section.
     *
     * @since 1.16.0
     */
    public static function register_section() {
        add_settings_section(
            'thatseoagent_products_section',
            __( 'Product catalog', 'thatseoagent' ),
            function () {
                echo '<p>' . esc_html__( 'Tick the content type that lists your products. Each one is then described to search engines as a product, with the details below.', 'thatseoagent' ) . '</p>';
            },
            ThatSeoAgent_Settings::GROUP
        );

        add_settings_field(
            'thatseoagent_products',
            __( 'Content types', 'thatseoagent' ),
            array( __CLASS__, 'render_field' ),
            ThatSeoAgent_Settings::GROUP,
            'thatseoagent_products_section'
        );
    }

    /**
     * Labels of the mapped fields.
     *
     * @since 1.16.0
     * @return array<string, array{label: string, help: string}>
     */
    private static function field_labels() {
        return array(
            'brand_taxonomy'      => array(
                'label' => __( 'Brand', 'thatseoagent' ),
                'help'  => __( 'Taxonomy whose term is the brand.', 'thatseoagent' ),
            ),
            'category_taxonomy'   => array(
                'label' => __( 'Category', 'thatseoagent' ),
                'help'  => __( 'Taxonomy whose term is the product category.', 'thatseoagent' ),
            ),
            'properties_meta_key' => array(
                'label' => __( 'Specifications', 'thatseoagent' ),
                'help'  => __( 'Meta key holding a list of name/value pairs (JSON or array).', 'thatseoagent' ),
            ),
            'gallery_meta_key'    => array(
                'label' => __( 'Gallery', 'thatseoagent' ),
                'help'  => __( 'Meta key holding image IDs, added after the featured image.', 'thatseoagent' ),
            ),
            'sku_meta_key'        => array(
                'label' => __( 'SKU', 'thatseoagent' ),
                'help'  => __( 'Meta key holding your own product code.', 'thatseoagent' ),
            ),
            'mpn_meta_key'        => array(
                'label' => __( 'MPN', 'thatseoagent' ),
                'help'  => __( "Meta key holding the manufacturer's part number or model.", 'thatseoagent' ),
            ),
            'gtin_meta_key'       => array(
                'label' => __( 'GTIN / EAN', 'thatseoagent' ),
                'help'  => __( 'Meta key holding the barcode number. Invalid values are left out.', 'thatseoagent' ),
            ),
        );
    }

    /**
     * Render the post type list and the mapping of each.
     *
     * @since 1.16.0
     */
    public static function render_field() {
        $config = ThatSeoAgent_Product::config();
        $name   = ThatSeoAgent_Product::OPTION_KEY;

        foreach ( ThatSeoAgent_Product::candidate_post_types() as $post_type => $object ) {
            $enabled = isset( $config[ $post_type ] );
            // A post type not yet configured is pre-filled with the detected
            // mapping, so ticking the box is usually all it takes.
            $mapping    = $enabled ? $config[ $post_type ] : ThatSeoAgent_Product::suggest( $post_type );
            $taxonomies = ThatSeoAgent_Product::candidate_taxonomies( $post_type );
            $meta_keys  = ThatSeoAgent_Product::detect_meta_keys( $post_type );
            $field_id   = 'thatseoagent_products_' . $post_type;
            ?>
            <fieldset class="thatseoagent-product-type" style="margin-bottom: 16px;" x-data="{ on: <?php echo $enabled ? 'true' : 'false'; ?> }">
                <label for="<?php echo esc_attr( $field_id ); ?>">
                    <input
                        type="checkbox"
                        id="<?php echo esc_attr( $field_id ); ?>"
                        name="<?php echo esc_attr( $name . '[' . $post_type . '][enabled]' ); ?>"
                        value="1"
                        aria-controls="<?php echo esc_attr( $field_id . '_mapping' ); ?>"
                        :aria-expanded="on ? 'true' : 'false'"
                        x-model="on"
                        <?php checked( $enabled ); ?>
                    >
                    <strong><?php echo esc_html( $object->labels->name ); ?></strong>
                    <code><?php echo esc_html( $post_type ); ?></code>
                </label>

                <table class="form-table" role="presentation" id="<?php echo esc_attr( $field_id . '_mapping' ); ?>" style="margin-top: 0;"<?php echo $enabled ? '' : ' hidden'; ?> :hidden="! on">
                    <?php foreach ( self::field_labels() as $field => $text ) : ?>
                        <?php
                        $is_taxonomy = 'taxonomy' === ThatSeoAgent_Product::fields()[ $field ];
                        $options     = $is_taxonomy ? wp_list_pluck( $taxonomies, 'label' ) : array_combine( $meta_keys, $meta_keys );
                        $current     = isset( $mapping[ $field ] ) ? (string) $mapping[ $field ] : '';

                        // Keep a saved key selectable even when no post uses
                        // it right now.
                        if ( '' !== $current && ! isset( $options[ $current ] ) ) {
                            $options[ $current ] = $current;
                        }
                        $select_id = $field_id . '_' . $field;
                        ?>
                        <tr>
                            <th scope="row" style="padding-left: 24px;">
                                <label for="<?php echo esc_attr( $select_id ); ?>"><?php echo esc_html( $text['label'] ); ?></label>
                            </th>
                            <td>
                                <select id="<?php echo esc_attr( $select_id ); ?>" name="<?php echo esc_attr( $name . '[' . $post_type . '][' . $field . ']' ); ?>">
                                    <option value=""><?php esc_html_e( '— None —', 'thatseoagent' ); ?></option>
                                    <?php foreach ( $options as $value => $label ) : ?>
                                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, (string) $value ); ?>>
                                            <?php echo esc_html( $is_taxonomy ? sprintf( '%s (%s)', $label, $value ) : $label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php echo esc_html( $text['help'] ); ?></p>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </fieldset>
            <?php
        }

    }
}
