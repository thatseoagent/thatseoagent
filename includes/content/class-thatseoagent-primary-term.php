<?php
/**
 * The category a post belongs to first.
 *
 * A post can sit in several categories; search results, breadcrumbs, the
 * permalink and the product markup can only name one. Before 2.4.0 each
 * took its own — the first category, the first term — so the breadcrumb
 * and the permalink could disagree. This is the single answer:
 *
 *     the term chosen in the SEO meta box, while it is still assigned
 *     else the deepest assigned term ("Cranes > Articulated" over "Cranes"),
 *     leaving out the default category when there is another
 *
 * Each content type has one main taxonomy it is asked about: `category` for
 * posts, a catalog's mapped category taxonomy, else a public hierarchical
 * one whose name says it is a category, else the first.
 *
 * Stored per taxonomy in `_thatseoagent_primary_{taxonomy}`, the term ID.
 *
 * @package ThatSeoAgent
 * @since 2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Primary_Term {

    /**
     * Prefix of the meta key; the taxonomy follows.
     */
    const KEY_PREFIX = '_thatseoagent_primary_';

    /**
     * Register the hooks.
     *
     * @since 2.4.0
     */
    public static function register() {
        add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );

        // %category% in the permalink structure names the primary category.
        add_filter( 'post_link_category', array( __CLASS__, 'filter_post_link_category' ), 10, 3 );
    }

    /**
     * The meta key for a taxonomy.
     *
     * @since 2.4.0
     * @param string $taxonomy Taxonomy.
     * @return string
     */
    public static function key( $taxonomy ) {
        return self::KEY_PREFIX . $taxonomy;
    }

    /**
     * The taxonomies a post type can have a primary term in: its public
     * hierarchical ones.
     *
     * @since 2.4.0
     * @param string $post_type Post type.
     * @return array<int, string>
     */
    public static function taxonomies( $post_type ) {
        $taxonomies = array();

        foreach ( get_object_taxonomies( $post_type, 'objects' ) as $taxonomy ) {
            if ( $taxonomy->hierarchical && $taxonomy->public ) {
                $taxonomies[] = $taxonomy->name;
            }
        }

        return $taxonomies;
    }

    /**
     * The taxonomy a post type is asked about.
     *
     * @since 2.4.0
     * @param string $post_type Post type.
     * @return string '' when the type has no hierarchical taxonomy.
     */
    public static function main_taxonomy( $post_type ) {
        $taxonomies = self::taxonomies( $post_type );
        $main       = '';

        $config  = ThatSeoAgent_Product::config();
        $catalog = isset( $config[ $post_type ]['category_taxonomy'] ) ? (string) $config[ $post_type ]['category_taxonomy'] : '';

        if ( in_array( 'category', $taxonomies, true ) ) {
            $main = 'category';
        } elseif ( in_array( $catalog, $taxonomies, true ) ) {
            $main = $catalog;
        } else {
            // One that says it is a category ("categoria-producto",
            // "Product categories"), rather than a brand that happens to be
            // registered first.
            foreach ( $taxonomies as $taxonomy ) {
                $object = get_taxonomy( $taxonomy );
                if ( preg_match( '/categor/i', $taxonomy . ' ' . ( $object ? $object->label : '' ) ) ) {
                    $main = $taxonomy;
                    break;
                }
            }

            if ( '' === $main && $taxonomies ) {
                $main = $taxonomies[0];
            }
        }

        /**
         * Filter the taxonomy a post type's primary term is taken from.
         *
         * @since 2.4.0
         * @param string $main      Taxonomy name, or ''.
         * @param string $post_type Post type.
         */
        return (string) apply_filters( 'thatseoagent_main_taxonomy', $main, $post_type );
    }

    /**
     * A post's primary term in a taxonomy.
     *
     * @since 2.4.0
     * @param WP_Post|int $post     Post or ID.
     * @param string      $taxonomy Taxonomy. Default the post type's main one.
     * @return WP_Term|null
     */
    public static function get( $post, $taxonomy = '' ) {
        $post = get_post( $post );
        if ( ! $post ) {
            return null;
        }

        $taxonomy = '' !== $taxonomy ? $taxonomy : self::main_taxonomy( $post->post_type );
        if ( '' === $taxonomy ) {
            return null;
        }

        $terms = get_the_terms( $post, $taxonomy );
        if ( ! $terms || is_wp_error( $terms ) ) {
            return null;
        }

        $chosen = (int) get_post_meta( $post->ID, self::key( $taxonomy ), true );
        foreach ( $terms as $term ) {
            if ( $chosen && (int) $term->term_id === $chosen ) {
                return $term;
            }
        }

        return self::deepest( $terms, $taxonomy );
    }

    /**
     * The deepest of a post's terms, the default category last.
     *
     * @since 2.4.0
     * @param array<int, WP_Term> $terms    Assigned terms.
     * @param string              $taxonomy Taxonomy.
     * @return WP_Term|null
     */
    private static function deepest( array $terms, $taxonomy ) {
        $default = 'category' === $taxonomy ? (int) get_option( 'default_category' ) : 0;
        $best    = null;
        $depth   = -1;

        foreach ( $terms as $term ) {
            // The default category ("Uncategorized") only when it is all
            // there is.
            if ( $default && (int) $term->term_id === $default && count( $terms ) > 1 ) {
                continue;
            }

            $level = count( get_ancestors( $term->term_id, $taxonomy, 'taxonomy' ) );
            if ( $level > $depth ) {
                $best  = $term;
                $depth = $level;
            }
        }

        return $best;
    }

    /**
     * The primary term and its ancestors, root first.
     *
     * @since 2.4.0
     * @param WP_Post|int $post     Post or ID.
     * @param string      $taxonomy Taxonomy. Default the post type's main one.
     * @return array<int, WP_Term>
     */
    public static function path( $post, $taxonomy = '' ) {
        $post     = get_post( $post );
        $taxonomy = '' !== $taxonomy ? $taxonomy : ( $post ? self::main_taxonomy( $post->post_type ) : '' );
        $term     = self::get( $post, $taxonomy );

        if ( ! $term ) {
            return array();
        }

        $path = array();
        foreach ( array_reverse( get_ancestors( $term->term_id, $taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
            $ancestor = get_term( $ancestor_id, $taxonomy );
            if ( $ancestor instanceof WP_Term ) {
                $path[] = $ancestor;
            }
        }
        $path[] = $term;

        return $path;
    }

    /**
     * Save the choice for one taxonomy. 0 or a term the post does not have
     * clears it.
     *
     * @since 2.4.0
     * @param WP_Post|int $post     Post or ID.
     * @param string      $taxonomy Taxonomy.
     * @param int         $term_id  Term ID.
     * @return bool Whether a choice is stored afterwards.
     */
    public static function save( $post, $taxonomy, $term_id ) {
        $post    = get_post( $post );
        $term_id = (int) $term_id;

        if ( ! $post || ! in_array( $taxonomy, self::taxonomies( $post->post_type ), true ) ) {
            return false;
        }

        if ( $term_id && has_term( $term_id, $taxonomy, $post ) ) {
            update_post_meta( $post->ID, self::key( $taxonomy ), $term_id );
            return true;
        }

        delete_post_meta( $post->ID, self::key( $taxonomy ) );
        return false;
    }

    /**
     * Register the meta for the REST API, per post type and taxonomy.
     *
     * @since 2.4.0
     */
    public static function register_meta() {
        foreach ( ThatSeoAgent_Post_Seo::post_types() as $post_type ) {
            foreach ( self::taxonomies( $post_type ) as $taxonomy ) {
                register_post_meta(
                    $post_type,
                    self::key( $taxonomy ),
                    array(
                        'type'              => 'integer',
                        'single'            => true,
                        'default'           => 0,
                        'show_in_rest'      => true,
                        'sanitize_callback' => 'absint',
                        'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
                            return current_user_can( 'edit_post', $post_id );
                        },
                    )
                );
            }
        }
    }

    /**
     * The meta box fields: one choice per taxonomy where the post has two
     * or more terms. With one there is nothing to choose.
     *
     * @since 2.4.0
     * @param WP_Post $post Post being edited.
     */
    public static function render_fields( WP_Post $post ) {
        foreach ( self::taxonomies( $post->post_type ) as $taxonomy ) {
            $terms = get_the_terms( $post, $taxonomy );
            if ( ! $terms || is_wp_error( $terms ) || count( $terms ) < 2 ) {
                continue;
            }

            $object    = get_taxonomy( $taxonomy );
            $automatic = self::deepest( $terms, $taxonomy );
            $chosen    = (int) get_post_meta( $post->ID, self::key( $taxonomy ), true );
            $id      = 'thatseoagent_primary_' . $taxonomy;
            ?>
            <div class="thatseoagent-field">
                <label for="<?php echo esc_attr( $id ); ?>">
                    <?php
                    /* translators: %s: taxonomy singular name, e.g. "Category". */
                    echo esc_html( sprintf( __( 'Primary %s', 'thatseoagent' ), $object ? $object->labels->singular_name : $taxonomy ) );
                    ?>
                </label>
                <select id="<?php echo esc_attr( $id ); ?>" name="thatseoagent_primary[<?php echo esc_attr( $taxonomy ); ?>]">
                    <option value="0" <?php selected( 0, $chosen ); ?>>
                        <?php
                        /* translators: %s: the term picked automatically. */
                        echo esc_html( sprintf( __( 'Automatic (%s)', 'thatseoagent' ), $automatic ? $automatic->name : '—' ) );
                        ?>
                    </option>
                    <?php foreach ( $terms as $term ) : ?>
                        <option value="<?php echo (int) $term->term_id; ?>" <?php selected( (int) $term->term_id, $chosen ); ?>><?php echo esc_html( $term->name ); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e( 'The one search results and breadcrumbs name. Choosing one also puts it in the address when the permalinks include the category. The list shows the terms saved with the post.', 'thatseoagent' ); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Save the meta box fields from the request.
     *
     * @since 2.4.0
     * @param int   $post_id Post ID.
     * @param array $raw     Raw `thatseoagent_primary` from $_POST.
     */
    public static function save_from_request( $post_id, $raw ) {
        if ( ! is_array( $raw ) ) {
            return;
        }

        foreach ( $raw as $taxonomy => $term_id ) {
            self::save( $post_id, sanitize_key( $taxonomy ), absint( $term_id ) );
        }
    }

    /**
     * %category% in permalinks names the primary category — only one
     * chosen by hand.
     *
     * The automatic pick is left out on purpose: it differs from the
     * lowest-ID category core has always used, and applying it would move
     * the address of every existing post filed in more than one category.
     * Choosing one is a decision to move that post; nothing moves on its own.
     *
     * @since 2.4.0
     * @param WP_Term $category   Category core picked (the lowest ID).
     * @param array   $categories The post's categories.
     * @param WP_Post $post       Post.
     * @return WP_Term
     */
    public static function filter_post_link_category( $category, $categories, $post ) {
        if ( ! $post instanceof WP_Post ) {
            return $category;
        }

        $chosen = (int) get_post_meta( $post->ID, self::key( 'category' ), true );
        foreach ( (array) $categories as $candidate ) {
            if ( $candidate instanceof WP_Term && $chosen === (int) $candidate->term_id ) {
                return $candidate;
            }
        }

        return $category;
    }
}
