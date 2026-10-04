<?php
/**
 * Product catalogs built on custom post types.
 *
 * Not every catalog is a shop. A site can list products — machinery, parts,
 * a range of models — as a custom post type with its own taxonomies and meta,
 * no WooCommerce, no prices. The code that registers that post type declares
 * it as a catalog and says where its data lives; this module marks each entry
 * up as a schema.org Product.
 *
 * The mapping, per post type:
 *
 *     brand_taxonomy       → Product.brand (a Brand node)
 *     category_taxonomy    → Product.category ("Parent > Child")
 *     properties           → Product.additionalProperty (PropertyValue list)
 *     gallery              → more Product.image entries, after the featured one
 *     sku / mpn / gtin     → Product.sku, .mpn, .gtin
 *
 * The taxonomies are named; every other detail is a meta key or a callback
 * that gets the post. The theme or plugin that registers the content type
 * declares it, with thatseoagent_register_catalog() on thatseoagent_init.
 *
 * Name, URL, description and image come from the post itself.
 *
 * Every value is validated before it reaches the graph, and one that fails is
 * dropped rather than emitted half-formed: a malformed field invalidates the
 * whole node in Google's eyes, while a missing optional one does not.
 * validate() reports what was dropped and why.
 *
 * No `offers`: without a price there is nothing honest to put there. That also
 * means Google shows no product rich result — it needs offers, review or
 * aggregateRating — but the node still tells search engines and AI systems
 * what the page is about.
 *
 * @package ThatSeoAgent
 * @since 1.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Product {

    /**
     * Where versions before 3.0.0 kept the catalogs chosen on the settings
     * screen. Deleted on upgrade and on uninstall.
     */
    const LEGACY_OPTION = 'thatseoagent_products';

    /**
     * The details a declaration maps, and what each one takes: a taxonomy
     * name, or a meta key or a callback.
     *
     * @since 1.16.0
     * @since 3.0.0 The keys of a declaration, not of a settings form.
     * @return array<string, string> Key => 'taxonomy' | 'source'.
     */
    public static function fields() {
        return array(
            'brand_taxonomy'    => 'taxonomy',
            'category_taxonomy' => 'taxonomy',
            'properties'        => 'source',
            'gallery'           => 'source',
            'sku'               => 'source',
            'mpn'               => 'source',
            'gtin'              => 'source',
        );
    }

    /**
     * Register the hooks.
     * @since 2.7.0
     */
    public static function register() {
        add_filter( 'thatseoagent_main_taxonomy', array( __CLASS__, 'filter_main_taxonomy' ), 5, 2 );
    }

    /**
     * A catalog's primary category comes from its mapped category
     * taxonomy, even when the type has `category` too: the mapping is what
     * the catalog's owner said the category is.
     * @since 2.7.0
     * @param string $main      Taxonomy name, or ''.
     * @param string $post_type Post type.
     * @return string
     */
    public static function filter_main_taxonomy( $main, $post_type ) {
        $config  = self::config();
        $catalog = isset( $config[ $post_type ] ) ? $config[ $post_type ]['category_taxonomy'] : '';

        return '' !== $catalog && in_array( $catalog, ThatSeoAgent_Primary_Term::taxonomies( $post_type ), true ) ? $catalog : $main;
    }

    /**
     * Every declared catalog: post type => its mapping.
     *
     * The themes and plugins that own a content type declare it on
     * `thatseoagent_init`, which fires the first time anything asks, once
     * `init` has begun and the post types exist. Asked before that, there
     * are none yet.
     *
     * @since 1.16.0
     * @since 3.0.0 Declared in code, no longer read from an option.
     * @return array<string, array<string, string|callable>> Every key of
     *         fields(), '' for a detail not mapped.
     */
    public static function config() {
        if ( ! did_action( 'init' ) ) {
            return array();
        }

        return ThatSeoAgent_Memo::remember(
            'catalogs',
            'site',
            function () {
                return self::collect();
            }
        );
    }

    /**
     * Fire thatseoagent_init and keep what was declared during it.
     * @since 3.0.0
     * @return array<string, array<string, string|callable>>
     */
    private static function collect() {
        self::$declaring = array();

        /**
         * Fires when ThatSeoAgent gathers the product catalogs: the moment
         * to declare one with thatseoagent_register_catalog().
         *
         * Fires once per request, the first time a catalog is asked for,
         * after `init` has begun: register the post type and its
         * taxonomies on `init` before priority 20.
         *
         * @since 3.0.0
         */
        do_action( 'thatseoagent_init' );

        $catalogs        = self::$declaring;
        self::$declaring = null;

        return $catalogs;
    }

    /**
     * The catalogs being declared while thatseoagent_init runs; null the
     * rest of the time.
     *
     * @var array<string, array<string, string|callable>>|null
     */
    private static $declaring = null;

    /**
     * Declare a content type as a product catalog.
     *
     * Called by thatseoagent_register_catalog(). A declaration that cannot
     * be honoured is refused with _doing_it_wrong(), whole; a detail it
     * maps wrongly is left out, with the same notice, and the rest kept.
     *
     * @since 3.0.0
     * @param string $post_type The content type that lists the products.
     * @param array  $args      Mapping: see fields().
     * @return bool Whether the catalog was declared.
     */
    public static function declare_catalog( $post_type, array $args ) {
        $function  = 'thatseoagent_register_catalog';
        $post_type = (string) $post_type;

        if ( null === self::$declaring ) {
            _doing_it_wrong( esc_html( $function ), esc_html__( 'Declare catalogs on the thatseoagent_init action.', 'thatseoagent' ), '3.0.0' );
            return false;
        }

        if ( ! post_type_exists( $post_type ) ) {
            /* translators: %s: post type. */
            _doing_it_wrong( esc_html( $function ), esc_html( sprintf( __( 'The post type "%s" does not exist: register it on init before priority 20.', 'thatseoagent' ), $post_type ) ), '3.0.0' );
            return false;
        }

        if ( in_array( $post_type, ThatSeoAgent_WooCommerce::post_types(), true ) ) {
            /* translators: %s: post type. */
            _doing_it_wrong( esc_html( $function ), esc_html( sprintf( __( 'WooCommerce marks up "%s" itself while it is active, so it cannot be a catalog.', 'thatseoagent' ), $post_type ) ), '3.0.0' );
            return false;
        }

        if ( isset( self::$declaring[ $post_type ] ) ) {
            /* translators: %s: post type. */
            _doing_it_wrong( esc_html( $function ), esc_html( sprintf( __( 'The catalog "%s" is already declared; the first declaration stays.', 'thatseoagent' ), $post_type ) ), '3.0.0' );
            return false;
        }

        $fields = self::fields();

        foreach ( array_diff( array_keys( $args ), array_keys( $fields ) ) as $unknown ) {
            /* translators: 1: argument, 2: the arguments a catalog takes. */
            _doing_it_wrong( esc_html( $function ), esc_html( sprintf( __( 'A catalog takes no "%1$s": it takes %2$s.', 'thatseoagent' ), $unknown, implode( ', ', array_keys( $fields ) ) ) ), '3.0.0' );
        }

        $mapping = array();
        foreach ( $fields as $field => $kind ) {
            $value = isset( $args[ $field ] ) ? $args[ $field ] : '';

            if ( 'taxonomy' === $kind && '' !== $value && ! ( is_string( $value ) && is_object_in_taxonomy( $post_type, $value ) ) ) {
                /* translators: 1: argument, 2: post type. */
                _doing_it_wrong( esc_html( $function ), esc_html( sprintf( __( '%1$s must name a taxonomy of "%2$s"; it is left out.', 'thatseoagent' ), $field, $post_type ) ), '3.0.0' );
                $value = '';
            }

            if ( 'source' === $kind && ! is_string( $value ) && ! is_callable( $value ) ) {
                /* translators: %s: argument. */
                _doing_it_wrong( esc_html( $function ), esc_html( sprintf( __( '%s must be a meta key or a callback; it is left out.', 'thatseoagent' ), $field ) ), '3.0.0' );
                $value = '';
            }

            $mapping[ $field ] = $value;
        }

        self::$declaring[ $post_type ] = $mapping;

        return true;
    }

    /**
     * A fingerprint of the declarations, which changes when a catalog or
     * its mapping does: the caches built from the catalogs are dropped
     * when it changes.
     *
     * @since 3.0.0
     * @return string
     */
    public static function fingerprint() {
        return md5( (string) wp_json_encode( self::describe() ) );
    }

    /**
     * The declarations as data: callbacks named, not run.
     *
     * For the screen and the settings ability, which show how each
     * catalog is read.
     *
     * @since 3.0.0
     * @return array<string, array<string, string>> Post type => field =>
     *         the taxonomy or meta key, 'callback', or ''.
     */
    public static function describe() {
        $described = array();

        foreach ( self::config() as $post_type => $mapping ) {
            foreach ( $mapping as $field => $value ) {
                $described[ $post_type ][ $field ] = is_string( $value ) ? $value : 'callback';
            }
        }

        return $described;
    }

    /**
     * Post types declared as product catalogs.
     * @since 1.16.0
     * @return array<int, string>
     */
    public static function post_types() {
        /**
         * Filter the post types marked up as products.
         * @since 1.16.0
         * @since 3.0.0 Receives the declared catalogs.
         * @param array<int, string> $post_types Declared post types.
         */
        return (array) apply_filters( 'thatseoagent_product_post_types', array_keys( self::config() ) );
    }

    /**
     * Whether a post is a catalog entry.
     * @since 1.16.0
     * @param WP_Post|int|null $post Post object or ID.
     * @return bool
     */
    public static function is_product( $post ) {
        $post = get_post( $post );

        return $post && in_array( $post->post_type, self::post_types(), true );
    }

    /**
     * One mapped detail of a catalog entry: what its meta key holds, or
     * what its callback returns.
     *
     * @since 3.0.0
     * @param WP_Post $post  Catalog entry.
     * @param string  $field A 'source' field of fields().
     * @return mixed Null when the detail is not mapped.
     */
    private static function source( WP_Post $post, $field ) {
        $config = self::config();
        $source = isset( $config[ $post->post_type ][ $field ] ) ? $config[ $post->post_type ][ $field ] : '';

        if ( is_callable( $source ) && ! is_string( $source ) ) {
            return call_user_func( $source, $post );
        }

        return '' !== $source ? get_post_meta( $post->ID, (string) $source, true ) : null;
    }

    /**
     * The Product node for a post, or null when it is not a catalog entry.
     *
     * @since 1.16.0
     * @param WP_Post|int|null $post Post object or ID.
     * @return array|null
     */
    public static function schema( $post ) {
        $post = get_post( $post );
        if ( ! self::is_product( $post ) ) {
            return null;
        }

        $node = self::build( $post )['node'];
        if ( null === $node ) {
            return null;
        }

        /**
         * Filter the Product schema node.
         *
         * @since 1.16.0
         * @param array   $node Product node.
         * @param WP_Post $post The catalog entry.
         */
        $node = apply_filters( 'thatseoagent_product_schema', $node, $post );

        return is_array( $node ) && ! empty( $node ) ? $node : null;
    }

    /**
     * What is wrong with a catalog entry's markup.
     *
     * Severity follows the consequence:
     *
     *     error   — no Product node at all
     *     warning — a field Google recommends is missing or was dropped
     *     info    — worth knowing, not worth fixing urgently
     *
     * @since 1.16.0
     * @param WP_Post|int $post Post object or ID.
     * @return array<int, array{type: string, severity: string, message: string, value: string}>
     */
    public static function validate( $post ) {
        $post = get_post( $post );
        if ( ! self::is_product( $post ) ) {
            return array();
        }

        return self::build( $post )['issues'];
    }

    /**
     * Build the node and record every problem found on the way.
     *
     * @since 1.16.0
     * @param WP_Post $post Catalog entry.
     * @return array{node: array|null, issues: array}
     */
    private static function build( WP_Post $post ) {
        $config = self::config();
        $map    = isset( $config[ $post->post_type ] ) ? $config[ $post->post_type ] : array_fill_keys( array_keys( self::fields() ), '' );
        $issues = array();
        $url    = get_permalink( $post );

        $name = trim( html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
        if ( '' === $name ) {
            $issues[] = self::issue( 'product_name_missing', 'error', __( 'The product has no title, so no Product schema is output.', 'thatseoagent' ) );

            return array(
                'node'   => null,
                'issues' => $issues,
            );
        }

        $node = array(
            '@type'            => 'Product',
            '@id'              => $url . '#product',
            'name'             => $name,
            'url'              => $url,
            'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
        );

        $description = ThatSeoAgent_Description::for_post( $post );
        if ( '' !== $description ) {
            $node['description'] = $description;
        } else {
            $issues[] = self::issue( 'product_description_missing', 'warning', __( 'No description: add an excerpt, content or a meta description.', 'thatseoagent' ) );
        }

        $images = self::images( $post, $issues );
        if ( $images ) {
            $node['image'] = 1 === count( $images ) ? $images[0] : $images;
        }

        if ( '' !== $map['brand_taxonomy'] ) {
            $brand = self::first_term( $post, $map['brand_taxonomy'] );
            if ( $brand ) {
                $node['brand'] = array(
                    '@type' => 'Brand',
                    'name'  => self::clean_text( $brand->name ),
                );
            } else {
                $issues[] = self::issue( 'product_brand_missing', 'warning', __( 'No brand assigned.', 'thatseoagent' ), $map['brand_taxonomy'] );
            }
        }

        if ( '' !== $map['category_taxonomy'] ) {
            // The primary one: the same the breadcrumb and the meta tags name.
            $category = self::clean_text( ThatSeoAgent_Primary_Term::label( $post, $map['category_taxonomy'] ) );
            if ( '' !== $category ) {
                $node['category'] = $category;
            } else {
                $issues[] = self::issue( 'product_category_missing', 'info', __( 'No category assigned.', 'thatseoagent' ), $map['category_taxonomy'] );
            }
        }

        if ( '' !== $map['properties'] ) {
            $parsed = self::parse_properties( self::source( $post, 'properties' ) );

            /**
             * Filter the product's properties before they become PropertyValues.
             *
             * Use it to read specifications from somewhere other than one
             * meta key — a block's attributes, several meta keys, a table.
             *
             * @since 1.16.0
             * @param array<int, array{name: string, value: string}> $properties Parsed properties.
             * @param WP_Post                                        $post       Catalog entry.
             */
            $properties = (array) apply_filters( 'thatseoagent_product_properties', $parsed['properties'], $post );

            if ( $properties ) {
                $node['additionalProperty'] = array();
                foreach ( $properties as $property ) {
                    $node['additionalProperty'][] = array(
                        '@type' => 'PropertyValue',
                        'name'  => (string) $property['name'],
                        'value' => (string) $property['value'],
                    );
                }
            } else {
                $issues[] = self::issue( 'product_properties_missing', 'warning', __( 'No specifications could be read.', 'thatseoagent' ), is_string( $map['properties'] ) ? $map['properties'] : 'callback' );
            }

            if ( $parsed['dropped'] > 0 ) {
                $issues[] = self::issue(
                    'product_properties_dropped',
                    'info',
                    __( 'Some specifications have an empty name or value and were left out.', 'thatseoagent' ),
                    (string) $parsed['dropped']
                );
            }
        }

        foreach ( array( 'sku', 'mpn' ) as $identifier ) {
            if ( '' === $map[ $identifier ] ) {
                continue;
            }

            $value = self::scalar( self::source( $post, $identifier ) );
            if ( '' !== $value ) {
                $node[ $identifier ] = $value;
            }
        }

        if ( '' !== $map['gtin'] ) {
            $gtin = self::scalar( self::source( $post, 'gtin' ) );
            if ( '' !== $gtin ) {
                if ( self::is_valid_gtin( $gtin ) ) {
                    $node['gtin'] = preg_replace( '/\D/', '', $gtin );
                } else {
                    $issues[] = self::issue( 'product_gtin_invalid', 'warning', __( 'The GTIN is not 8, 12, 13 or 14 digits with a valid check digit, so it was left out.', 'thatseoagent' ), $gtin );
                }
            }
        }

        return array(
            'node'   => $node,
            'issues' => $issues,
        );
    }

    /**
     * The product's images as ImageObjects: featured first, then the gallery.
     *
     * @since 1.16.0
     * @param WP_Post $post        Catalog entry.
     * @param string  $gallery_key Meta key of the gallery, or ''.
     * @param array   $issues      Issues, appended to by reference.
     * @return array<int, array>
     */
    private static function images( WP_Post $post, array &$issues ) {
        $featured_id = (int) get_post_thumbnail_id( $post );
        $all         = ThatSeoAgent_Image::all( $post );
        $images      = array_map( array( 'ThatSeoAgent_Image', 'object' ), $all['images'] );
        $invalid     = $all['invalid'];

        if ( ! $featured_id ) {
            $issues[] = self::issue( 'product_image_missing', $images ? 'info' : 'warning', __( 'No featured image. Google recommends an image for every product.', 'thatseoagent' ) );
        }

        if ( $invalid > 0 ) {
            $issues[] = self::issue( 'product_image_invalid', 'warning', __( 'Some images are not image files or have no absolute URL, and were left out.', 'thatseoagent' ), (string) $invalid );
        }

        return $images;
    }

    /**
     * The attachment IDs of a catalog entry's gallery, in order.
     *
     * @since 2.4.0
     * @param WP_Post $post Post.
     * @return array<int, int> Empty when it is no catalog entry or has no gallery.
     */
    public static function gallery_ids( WP_Post $post ) {
        if ( ! self::is_product( $post ) ) {
            return array();
        }

        return self::attachment_ids( self::source( $post, 'gallery' ) );
    }

    /**
     * Attachment IDs from a stored gallery value.
     *
     * Accepts "12,13,14", an array of IDs, or JSON of either.
     *
     * @since 1.16.0
     * @param mixed $raw Stored value.
     * @return array<int, int>
     */
    private static function attachment_ids( $raw ) {
        if ( is_string( $raw ) ) {
            $decoded = json_decode( $raw, true );
            $raw     = is_array( $decoded ) ? $decoded : explode( ',', $raw );
        }

        if ( ! is_array( $raw ) ) {
            return array();
        }

        return array_values( array_filter( array_map( 'absint', $raw ) ) );
    }

    /**
     * The first term of a taxonomy on a post.
     *
     * @since 1.16.0
     * @param WP_Post $post     Post.
     * @param string  $taxonomy Taxonomy.
     * @return WP_Term|null
     */
    private static function first_term( WP_Post $post, $taxonomy ) {
        $terms = get_the_terms( $post, $taxonomy );

        return ( $terms && ! is_wp_error( $terms ) ) ? reset( $terms ) : null;
    }

    /**
     * Read a property list from a stored meta value.
     *
     * Accepts the shapes themes actually store:
     *
     *     [{"name":"Alcance","value":"30 m"}, …]   JSON string or array
     *     [{"label":"Alcance","value":"30 m"}, …]  label / key instead of name
     *     {"Alcance":"30 m", …}                    name => value map
     *
     * Entries with an empty name or value are dropped and counted.
     *
     * @since 1.16.0
     * @param mixed $raw Stored value.
     * @return array{properties: array<int, array{name: string, value: string}>, dropped: int}
     */
    public static function parse_properties( $raw ) {
        $result = array(
            'properties' => array(),
            'dropped'    => 0,
        );

        if ( is_string( $raw ) ) {
            $decoded = json_decode( $raw, true );
            $raw     = is_array( $decoded ) ? $decoded : null;
        }

        if ( ! is_array( $raw ) ) {
            return $result;
        }

        foreach ( $raw as $key => $entry ) {
            if ( is_array( $entry ) ) {
                $name  = self::first_present( $entry, array( 'name', 'label', 'key', 'nombre' ) );
                $value = self::first_present( $entry, array( 'value', 'val', 'valor' ) );
            } elseif ( is_string( $key ) && is_scalar( $entry ) ) {
                $name  = $key;
                $value = (string) $entry;
            } else {
                $result['dropped']++;
                continue;
            }

            $name  = self::clean_text( $name );
            $value = self::clean_text( $value );

            if ( '' === $name || '' === $value ) {
                $result['dropped']++;
                continue;
            }

            $result['properties'][] = array(
                'name'  => $name,
                'value' => $value,
            );
        }

        return $result;
    }

    /**
     * The first non-empty scalar among several array keys.
     *
     * @since 1.16.0
     * @param array              $entry Array.
     * @param array<int, string> $keys  Keys, in order of preference.
     * @return string
     */
    private static function first_present( array $entry, array $keys ) {
        foreach ( $keys as $key ) {
            if ( isset( $entry[ $key ] ) && is_scalar( $entry[ $key ] ) && '' !== trim( (string) $entry[ $key ] ) ) {
                return (string) $entry[ $key ];
            }
        }

        return '';
    }

    /**
     * Plain, single-line text.
     *
     * @since 1.16.0
     * @param string $text Text.
     * @return string
     */
    private static function clean_text( $text ) {
        $text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        return trim( preg_replace( '/\s+/u', ' ', $text ) );
    }

    /**
     * A detail as plain text, or '' when it is not a scalar.
     *
     * @since 1.16.0
     * @since 3.0.0 Of a value, read from a meta key or a callback.
     * @param mixed $value Value.
     * @return string
     */
    private static function scalar( $value ) {
        return is_scalar( $value ) ? self::clean_text( (string) $value ) : '';
    }

    /**
     * Whether a string is a GTIN-8, -12, -13 or -14 with a valid check digit.
     *
     * @since 1.16.0
     * @param string $gtin Candidate, spaces and dashes allowed.
     * @return bool
     */
    public static function is_valid_gtin( $gtin ) {
        $digits = preg_replace( '/[\s-]/', '', (string) $gtin );

        if ( ! preg_match( '/^(\d{8}|\d{12}|\d{13}|\d{14})$/', $digits ) ) {
            return false;
        }

        // GS1 check digit: weights 3 and 1 alternate from the rightmost
        // digit before the check digit.
        $sum    = 0;
        $body   = substr( $digits, 0, -1 );
        $length = strlen( $body );

        for ( $i = 0; $i < $length; $i++ ) {
            $weight = ( ( $length - $i ) % 2 ) ? 3 : 1;
            $sum   += (int) $body[ $i ] * $weight;
        }

        return ( ( 10 - ( $sum % 10 ) ) % 10 ) === (int) substr( $digits, -1 );
    }

    /**
     * One validation issue, in the shape the audit uses.
     *
     * @since 1.16.0
     * @param string $type     Machine-readable type.
     * @param string $severity 'error', 'warning' or 'info'.
     * @param string $message  Human-readable message.
     * @param string $value    The offending value, when there is one.
     * @return array{type: string, severity: string, message: string, value: string}
     */
    private static function issue( $type, $severity, $message, $value = '' ) {
        return array(
            'type'     => $type,
            'severity' => $severity,
            'message'  => $message,
            'value'    => (string) $value,
        );
    }
}
