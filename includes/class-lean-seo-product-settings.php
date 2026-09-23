<?php
/**
 * The "Product catalog" section of the settings: every active post type, a
 * checkbox to mark it as a catalog, and the field mapping, with taxonomies
 * and meta keys detected from the site's own data.
 *
 * @package Lean_SEO
 * @since 1.16.0 As Lean_SEO_Product_Admin.
 * @since 1.19.0 The report moved to Lean_SEO_Product_Report.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lean_SEO_Product_Settings {

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
            'lean_seo_products_section',
            __( 'Product catalog', 'lean-seo' ),
            function () {
                echo '<p>' . esc_html__( 'Tick the content type that lists your products. Each one is then described to search engines as a product, with the details below.', 'lean-seo' ) . '</p>';
            },
            Lean_SEO_Settings::GROUP
        );

        add_settings_field(
            'lean_seo_products',
            __( 'Content types', 'lean-seo' ),
            array( __CLASS__, 'render_field' ),
            Lean_SEO_Settings::GROUP,
            'lean_seo_products_section'
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
                'label' => __( 'Brand', 'lean-seo' ),
                'help'  => __( 'Taxonomy whose term is the brand.', 'lean-seo' ),
            ),
            'category_taxonomy'   => array(
                'label' => __( 'Category', 'lean-seo' ),
                'help'  => __( 'Taxonomy whose term is the product category.', 'lean-seo' ),
            ),
            'properties_meta_key' => array(
                'label' => __( 'Specifications', 'lean-seo' ),
                'help'  => __( 'Meta key holding a list of name/value pairs (JSON or array).', 'lean-seo' ),
            ),
            'gallery_meta_key'    => array(
                'label' => __( 'Gallery', 'lean-seo' ),
                'help'  => __( 'Meta key holding image IDs, added after the featured image.', 'lean-seo' ),
            ),
            'sku_meta_key'        => array(
                'label' => __( 'SKU', 'lean-seo' ),
                'help'  => __( 'Meta key holding your own product code.', 'lean-seo' ),
            ),
            'mpn_meta_key'        => array(
                'label' => __( 'MPN', 'lean-seo' ),
                'help'  => __( "Meta key holding the manufacturer's part number or model.", 'lean-seo' ),
            ),
            'gtin_meta_key'       => array(
                'label' => __( 'GTIN / EAN', 'lean-seo' ),
                'help'  => __( 'Meta key holding the barcode number. Invalid values are left out.', 'lean-seo' ),
            ),
        );
    }

    /**
     * Render the post type list and the mapping of each.
     *
     * @since 1.16.0
     */
    public static function render_field() {
        $config = Lean_SEO_Product::config();
        $name   = Lean_SEO_Product::OPTION_KEY;

        foreach ( Lean_SEO_Product::candidate_post_types() as $post_type => $object ) {
            $enabled = isset( $config[ $post_type ] );
            // A post type not yet configured is pre-filled with the detected
            // mapping, so ticking the box is usually all it takes.
            $mapping    = $enabled ? $config[ $post_type ] : Lean_SEO_Product::suggest( $post_type );
            $taxonomies = Lean_SEO_Product::candidate_taxonomies( $post_type );
            $meta_keys  = Lean_SEO_Product::detect_meta_keys( $post_type );
            $field_id   = 'lean_seo_products_' . $post_type;
            ?>
            <fieldset class="lean-seo-product-type" style="margin-bottom: 16px;" x-data="{ on: <?php echo $enabled ? 'true' : 'false'; ?> }">
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
                        $is_taxonomy = 'taxonomy' === Lean_SEO_Product::fields()[ $field ];
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
                                    <option value=""><?php esc_html_e( '— None —', 'lean-seo' ); ?></option>
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
