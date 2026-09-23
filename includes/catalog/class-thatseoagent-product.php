<?php
/**
 * Product catalogs built on custom post types.
 *
 * Not every catalog is a shop. A site can list products — machinery, parts,
 * a range of models — as a custom post type with its own taxonomies and meta,
 * no WooCommerce, no prices. This module lets the site owner say which post
 * type is the catalog and where its data lives, and then marks each entry up
 * as a schema.org Product.
 *
 * The mapping, per post type:
 *
 *     brand_taxonomy       → Product.brand (a Brand node)
 *     category_taxonomy    → Product.category ("Parent > Child")
 *     properties_meta_key  → Product.additionalProperty (PropertyValue list)
 *     gallery_meta_key     → more Product.image entries, after the featured one
 *     sku / mpn / gtin     → Product.sku, .mpn, .gtin
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
     * Option holding the catalog configuration, keyed by post type.
     */
    const OPTION_KEY = 'thatseoagent_products';

    /**
     * The option as ThatSeoAgent_Settings registers it: type, sanitizer,
     * default and REST schema.
     *
     * @since 1.20.0 Moved from ThatSeoAgent_Settings::definitions().
     * @return array{type: string, sanitize: callable, default: mixed, schema: array}
     */
    public static function setting() {
        return array(
            'type'     => 'object',
            'sanitize' => array( __CLASS__, 'sanitize' ),
            'default'  => array(),
            'schema'   => self::rest_schema(),
        );
    }

    /**
     * The mapped fields and what feeds them.
     *
     * @since 1.16.0
     * @return array<string, string> Field => 'taxonomy' | 'meta'.
     */
    public static function fields() {
        return array(
            'brand_taxonomy'      => 'taxonomy',
            'category_taxonomy'   => 'taxonomy',
            'properties_meta_key' => 'meta',
            'gallery_meta_key'    => 'meta',
            'sku_meta_key'        => 'meta',
            'mpn_meta_key'        => 'meta',
            'gtin_meta_key'       => 'meta',
        );
    }

    /**
     * The saved configuration, limited to post types that still exist.
     *
     * A theme switch can unregister the catalog's post type; its settings are
     * kept, so switching back restores them, but they do nothing meanwhile.
     *
     * @since 1.16.0
     * @return array<string, array<string, string>>
     */
    public static function config() {
        $saved = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $saved ) ) {
            return array();
        }

        $config = array();

        foreach ( $saved as $post_type => $mapping ) {
            if ( ! post_type_exists( $post_type ) || ! is_array( $mapping ) ) {
                continue;
            }

            $config[ $post_type ] = wp_parse_args(
                $mapping,
                array_fill_keys( array_keys( self::fields() ), '' )
            );
        }

        return $config;
    }

    /**
     * Post types configured as product catalogs.
     *
     * @since 1.16.0
     * @return array<int, string>
     */
    public static function post_types() {
        /**
         * Filter the post types marked up as products.
         *
         * @since 1.16.0
         * @param array<int, string> $post_types Post types from the settings.
         */
        return (array) apply_filters( 'thatseoagent_product_post_types', array_keys( self::config() ) );
    }

    /**
     * Whether a post is a catalog entry.
     *
     * @since 1.16.0
     * @param WP_Post|int|null $post Post object or ID.
     * @return bool
     */
    public static function is_product( $post ) {
        $post = get_post( $post );

        return $post && in_array( $post->post_type, self::post_types(), true );
    }

    /**
     * Post types that can be selected as a catalog.
     *
     * Every public post type with an editing screen, whoever registered it —
     * a theme, a plugin or code in functions.php. Attachments are not
     * content.
     *
     * @since 1.16.0
     * @return array<string, WP_Post_Type>
     */
    public static function candidate_post_types() {
        $post_types = get_post_types(
            array(
                'public'  => true,
                'show_ui' => true,
            ),
            'objects'
        );

        unset( $post_types['attachment'] );

        return $post_types;
    }

    /**
     * Public taxonomies attached to a post type.
     *
     * @since 1.16.0
     * @param string $post_type Post type.
     * @return array<string, WP_Taxonomy>
     */
    public static function candidate_taxonomies( $post_type ) {
        return array_filter(
            get_object_taxonomies( $post_type, 'objects' ),
            function ( $taxonomy ) {
                return $taxonomy->public;
            }
        );
    }

    /**
     * Meta keys in use on a post type.
     *
     * Read from the data rather than from register_post_meta(): themes rarely
     * register their meta, and the key the site owner needs to pick is the one
     * actually stored. Protected keys (leading underscore) are included — a
     * theme's own fields usually are — except the ones WordPress and SEO
     * plugins keep for themselves.
     *
     * @since 1.16.0
     * @param string $post_type Post type.
     * @return array<int, string>
     */
    public static function detect_meta_keys( $post_type ) {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        // Listing distinct meta keys has no API. It runs only when the
        // settings page is rendered, so it is not cached: a key the theme
        // just started writing should show up on the next page load.
        $keys = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT pm.meta_key
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE p.post_type = %s
                 ORDER BY pm.meta_key
                 LIMIT 200",
                $post_type
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        $reserved = '/^(_edit_|_wp_|_thumbnail_id$|_encloseme$|_pingme$|_menu_item_|_thatseoagent_|_yoast_|rank_math_|_aioseo_|_oembed_)/';

        return array_values(
            array_filter(
                (array) $keys,
                function ( $key ) use ( $reserved ) {
                    return ! preg_match( $reserved, $key );
                }
            )
        );
    }

    /**
     * A best guess at the mapping for a post type.
     *
     * Used to pre-fill the settings form the first time a post type is
     * marked as a catalog. Taxonomies are matched by name in English and
     * Spanish; the properties key is the first one whose stored value
     * actually parses as a property list.
     *
     * @since 1.16.0
     * @param string $post_type Post type.
     * @return array<string, string>
     */
    public static function suggest( $post_type ) {
        $suggestion = array_fill_keys( array_keys( self::fields() ), '' );

        foreach ( self::candidate_taxonomies( $post_type ) as $name => $taxonomy ) {
            $haystack = strtolower( $name . ' ' . $taxonomy->label );

            if ( '' === $suggestion['brand_taxonomy'] && preg_match( '/brand|marca|fabricante|manufacturer/', $haystack ) ) {
                $suggestion['brand_taxonomy'] = $name;
            } elseif ( '' === $suggestion['category_taxonomy'] && preg_match( '/categor/', $haystack ) ) {
                $suggestion['category_taxonomy'] = $name;
            }
        }

        foreach ( self::detect_meta_keys( $post_type ) as $key ) {
            if ( '' === $suggestion['properties_meta_key'] && preg_match( '/spec|propert|caracter|atribut|attribut|feature/i', $key ) ) {
                $sample = self::sample_meta_value( $post_type, $key );
                if ( ! empty( self::parse_properties( $sample )['properties'] ) ) {
                    $suggestion['properties_meta_key'] = $key;
                }
            } elseif ( '' === $suggestion['gallery_meta_key'] && preg_match( '/galer|gallery|fotos|photos|images/i', $key ) ) {
                $suggestion['gallery_meta_key'] = $key;
            } elseif ( '' === $suggestion['sku_meta_key'] && preg_match( '/(^|_)sku$/i', $key ) ) {
                $suggestion['sku_meta_key'] = $key;
            } elseif ( '' === $suggestion['mpn_meta_key'] && preg_match( '/(^|_)(mpn|model|modelo)$/i', $key ) ) {
                $suggestion['mpn_meta_key'] = $key;
            } elseif ( '' === $suggestion['gtin_meta_key'] && preg_match( '/(^|_)(gtin\d*|ean|upc|isbn)$/i', $key ) ) {
                $suggestion['gtin_meta_key'] = $key;
            }
        }

        return $suggestion;
    }

    /**
     * One stored value of a meta key, from the most recent post of a type.
     *
     * @since 1.16.0
     * @param string $post_type Post type.
     * @param string $key       Meta key.
     * @return mixed
     */
    private static function sample_meta_value( $post_type, $key ) {
        $ids = get_posts(
            array(
                'post_type'      => $post_type,
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One row, settings page only.
                'meta_key'       => $key,
            )
        );

        return $ids ? get_post_meta( $ids[0], $key, true ) : null;
    }

    /**
     * Sanitize the catalog settings on save.
     *
     * Input is keyed by post type. An entry is kept only when `enabled` is
     * set — the settings form sends every post type's fields, checked or not.
     * A taxonomy must exist and be attached to the post type; anything else
     * is dropped.
     *
     * @since 1.16.0
     * @param mixed $input Raw input.
     * @return array<string, array<string, string>>
     */
    public static function sanitize( $input ) {
        $clean = array();

        if ( ! is_array( $input ) ) {
            return $clean;
        }

        foreach ( $input as $post_type => $mapping ) {
            $post_type = sanitize_key( $post_type );

            if ( ! is_array( $mapping ) || empty( $mapping['enabled'] ) || ! post_type_exists( $post_type ) ) {
                continue;
            }

            $taxonomies = get_object_taxonomies( $post_type );
            $entry      = array( 'enabled' => true );

            foreach ( self::fields() as $field => $source ) {
                $value = isset( $mapping[ $field ] ) ? trim( sanitize_text_field( (string) $mapping[ $field ] ) ) : '';

                if ( 'taxonomy' === $source && '' !== $value && ! in_array( $value, $taxonomies, true ) ) {
                    $value = '';
                }

                $entry[ $field ] = $value;
            }

            $clean[ $post_type ] = $entry;
        }

        return $clean;
    }

    /**
     * JSON schema of the option, for the REST API.
     *
     * @since 1.16.0
     * @return array
     */
    public static function rest_schema() {
        $properties = array(
            'enabled' => array( 'type' => 'boolean' ),
        );

        foreach ( array_keys( self::fields() ) as $field ) {
            $properties[ $field ] = array( 'type' => 'string' );
        }

        return array(
            'type'                 => 'object',
            'additionalProperties' => array(
                'type'                 => 'object',
                'additionalProperties' => false,
                'properties'           => $properties,
            ),
        );
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

        $images = self::images( $post, $map['gallery_meta_key'], $issues );
        if ( $images ) {
            $node['image'] = 1 === count( $images ) ? $images[0] : $images;
        }

        if ( '' !== $map['brand_taxonomy'] ) {
            $brand = self::first_term( $post, $map['brand_taxonomy'] );
            if ( $brand ) {
                $node['brand'] = array(
                    '@type' => 'Brand',
                    'name'  => $brand->name,
                );
            } else {
                $issues[] = self::issue( 'product_brand_missing', 'warning', __( 'No brand assigned.', 'thatseoagent' ), $map['brand_taxonomy'] );
            }
        }

        if ( '' !== $map['category_taxonomy'] ) {
            $category = self::category_path( $post, $map['category_taxonomy'] );
            if ( '' !== $category ) {
                $node['category'] = $category;
            } else {
                $issues[] = self::issue( 'product_category_missing', 'info', __( 'No category assigned.', 'thatseoagent' ), $map['category_taxonomy'] );
            }
        }

        if ( '' !== $map['properties_meta_key'] ) {
            $parsed = self::parse_properties( get_post_meta( $post->ID, $map['properties_meta_key'], true ) );

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
                $issues[] = self::issue( 'product_properties_missing', 'warning', __( 'No specifications could be read.', 'thatseoagent' ), $map['properties_meta_key'] );
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
            $key = $map[ $identifier . '_meta_key' ];
            if ( '' === $key ) {
                continue;
            }

            $value = self::scalar_meta( $post->ID, $key );
            if ( '' !== $value ) {
                $node[ $identifier ] = $value;
            }
        }

        if ( '' !== $map['gtin_meta_key'] ) {
            $gtin = self::scalar_meta( $post->ID, $map['gtin_meta_key'] );
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
    private static function images( WP_Post $post, $gallery_key, array &$issues ) {
        $featured_id = (int) get_post_thumbnail_id( $post );
        $gallery_ids = '' !== $gallery_key ? self::attachment_ids( get_post_meta( $post->ID, $gallery_key, true ) ) : array();

        $images  = array();
        $invalid = 0;

        foreach ( array_unique( array_filter( array_merge( array( $featured_id ), $gallery_ids ) ) ) as $image_id ) {
            $image = self::image_object( $image_id );
            if ( $image ) {
                $images[] = $image;
            } else {
                $invalid++;
            }
        }

        if ( ! $featured_id ) {
            $issues[] = self::issue( 'product_image_missing', $images ? 'info' : 'warning', __( 'No featured image. Google recommends an image for every product.', 'thatseoagent' ) );
        }

        if ( $invalid > 0 ) {
            $issues[] = self::issue( 'product_image_invalid', 'warning', __( 'Some images are not image files or have no absolute URL, and were left out.', 'thatseoagent' ), (string) $invalid );
        }

        return $images;
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
     * One attachment as an ImageObject, or null when it is not a usable image.
     *
     * @since 1.16.0
     * @param int $image_id Attachment ID.
     * @return array|null
     */
    private static function image_object( $image_id ) {
        if ( ! wp_attachment_is_image( $image_id ) ) {
            return null;
        }

        /** This filter is documented in includes/class-thatseoagent-schema.php */
        $size = apply_filters( 'thatseoagent_schema_image_size', 'full' );
        $src  = wp_get_attachment_image_src( $image_id, $size );
        $url  = $src ? (string) $src[0] : '';

        if ( ! preg_match( '#^https?://#i', $url ) ) {
            return null;
        }

        $image = array(
            '@type' => 'ImageObject',
            'url'   => $url,
        );

        if ( ! empty( $src[1] ) && ! empty( $src[2] ) ) {
            $image['width']  = (int) $src[1];
            $image['height'] = (int) $src[2];
        }

        return $image;
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
     * The category as a path, "Grúas > Articuladas".
     *
     * schema.org's `category` is text, and Google reads a ">"-separated path
     * as a hierarchy.
     *
     * @since 1.16.0
     * @param WP_Post $post     Post.
     * @param string  $taxonomy Taxonomy.
     * @return string
     */
    private static function category_path( WP_Post $post, $taxonomy ) {
        $term = self::first_term( $post, $taxonomy );
        if ( ! $term ) {
            return '';
        }

        $names = array();
        foreach ( array_reverse( get_ancestors( $term->term_id, $taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
            $ancestor = get_term( $ancestor_id, $taxonomy );
            if ( $ancestor && ! is_wp_error( $ancestor ) ) {
                $names[] = $ancestor->name;
            }
        }
        $names[] = $term->name;

        return implode( ' > ', $names );
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
     * A meta value as plain text, or '' when it is not a scalar.
     *
     * @since 1.16.0
     * @param int    $post_id Post ID.
     * @param string $key     Meta key.
     * @return string
     */
    private static function scalar_meta( $post_id, $key ) {
        $value = get_post_meta( $post_id, $key, true );

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
