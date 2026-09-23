<?php
/**
 * Admin screens for product catalogs.
 *
 * Two surfaces:
 *
 *   - A "Product catalogs" section on the settings page: every active post
 *     type, a checkbox to mark it as a catalog, and the field mapping, with
 *     taxonomies and meta keys detected from the site's own data.
 *   - The data of the "Products" view of the Lean SEO screen: each catalog
 *     entry and what its markup is missing.
 *
 * @package Lean_SEO
 * @since 1.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lean_SEO_Product_Admin {

    /**
     * Products per report page.
     */
    const PER_PAGE = 50;

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

    /**
     * Transient holding the catalog summary.
     */
    const SUMMARY_KEY = 'lean_seo_product_summary';

    /**
     * Drop the summary when anything it counts may have changed.
     *
     * Registered on every request, not only in wp-admin: products are also
     * saved from WP-CLI, the REST API and cron.
     *
     * @since 1.17.0
     */
    public static function register_cache() {
        add_action( 'save_post', array( __CLASS__, 'purge_summary' ) );
        add_action( 'deleted_post', array( __CLASS__, 'purge_summary' ) );
        add_action( 'set_object_terms', array( __CLASS__, 'purge_summary' ) );
        add_action( 'updated_post_meta', array( __CLASS__, 'purge_summary' ) );
        add_action( 'update_option_' . Lean_SEO_Product::OPTION_KEY, array( __CLASS__, 'purge_summary' ) );
    }

    /**
     * Forget the cached summary.
     *
     * @since 1.17.0
     */
    public static function purge_summary() {
        delete_transient( self::SUMMARY_KEY );
    }

    /**
     * How many catalog entries are complete, and how many are not.
     *
     * Validating renders each entry's description, so the whole catalog is
     * validated at most once an hour, or again after a change.
     *
     * @since 1.17.0
     * @return array{error: int, warning: int, info: int, ok: int, total: int}
     */
    public static function summary() {
        $cached = get_transient( self::SUMMARY_KEY );
        if ( is_array( $cached ) && isset( $cached['total'] ) ) {
            return $cached;
        }

        $summary = array(
            'error'   => 0,
            'warning' => 0,
            'info'    => 0,
            'ok'      => 0,
            'total'   => 0,
        );

        $post_types = Lean_SEO_Product::post_types();

        if ( $post_types ) {
            $ids = get_posts(
                array(
                    'post_type'      => $post_types,
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'no_found_rows'  => true,
                )
            );

            foreach ( $ids as $post_id ) {
                $summary[ self::worst_severity( Lean_SEO_Product::validate( $post_id ) ) ]++;
                $summary['total']++;
                Lean_SEO_Content::forget( $post_id );
            }
        }

        set_transient( self::SUMMARY_KEY, $summary, HOUR_IN_SECONDS );

        return $summary;
    }

    /**
     * One page of the validation report.
     *
     * One page of catalog entries at a time: validating renders each entry's
     * description, which is too much work for every product at once on a
     * large catalog. `wp lean-seo validate-products` covers the whole catalog.
     *
     * @since 1.17.0 Replaces the Product schema admin page's own renderer.
     * @param int $paged Page number.
     * @return array{rows: array, counts: array<string, int>, found: int, pages: int, paged: int}
     */
    public static function report( $paged = 1 ) {
        $paged  = max( 1, (int) $paged );
        $report = array(
            'rows'   => array(),
            'counts' => array(
                'error'   => 0,
                'warning' => 0,
                'info'    => 0,
                'ok'      => 0,
            ),
            'found'  => 0,
            'pages'  => 0,
            'paged'  => $paged,
        );

        $post_types = Lean_SEO_Product::post_types();
        if ( empty( $post_types ) ) {
            return $report;
        }

        $query = new WP_Query(
            array(
                'post_type'      => $post_types,
                'post_status'    => 'publish',
                'posts_per_page' => self::PER_PAGE,
                'paged'          => $paged,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );

        foreach ( $query->posts as $post ) {
            $issues = Lean_SEO_Product::validate( $post );
            $status = self::worst_severity( $issues );

            $report['counts'][ $status ]++;
            $report['rows'][] = array(
                'post'   => $post,
                'status' => $status,
                'issues' => $issues,
            );

            Lean_SEO_Content::forget( $post );
        }

        $report['found'] = (int) $query->found_posts;
        $report['pages'] = (int) $query->max_num_pages;

        return $report;
    }

    /**
     * The most serious severity among issues, 'ok' when there are none.
     *
     * @since 1.16.0
     * @param array $issues Issues.
     * @return string
     */
    public static function worst_severity( array $issues ) {
        $severities = wp_list_pluck( $issues, 'severity' );

        foreach ( array( 'error', 'warning', 'info' ) as $severity ) {
            if ( in_array( $severity, $severities, true ) ) {
                return $severity;
            }
        }

        return 'ok';
    }

    /**
     * Human label of a severity.
     *
     * @since 1.16.0
     * @param string $severity Severity.
     * @return string
     */
    public static function status_label( $severity ) {
        $labels = array(
            'error'   => __( 'Error', 'lean-seo' ),
            'warning' => __( 'Warning', 'lean-seo' ),
            'info'    => __( 'Note', 'lean-seo' ),
            'ok'      => __( 'Complete', 'lean-seo' ),
        );

        return isset( $labels[ $severity ] ) ? $labels[ $severity ] : $severity;
    }
}
