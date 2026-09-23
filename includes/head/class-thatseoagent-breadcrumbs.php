<?php
/**
 * The breadcrumb trail of the current view, for the schema and for people.
 *
 * One trail per page, read by the BreadcrumbList in the JSON-LD and by the
 * breadcrumbs a theme prints, so what a reader sees and what search engines
 * are told cannot differ. The trail, by view:
 *
 *     post                 Home › Primary category (with its parents) › Post
 *     page                 Home › Parent › Page
 *     custom post type     Home › Archive › Primary category › Entry
 *     post type archive    Home › Archive
 *     term archive         Home › [Archive ›] Parent term › Term
 *     author archive       Home › Author
 *     posts page           Home › Blog
 *
 * The visible breadcrumbs come three ways, all printing the same markup —
 * a nav landmark with an ordered list, the current page marked with
 * aria-current, no styles of its own:
 *
 *     thatseoagent_breadcrumbs()        in a template
 *     [thatseoagent_breadcrumbs]        in content or a widget
 *     the "Breadcrumbs" block           in the editor
 *
 * The plugin prints them nowhere by itself: where they go is the theme's
 * decision.
 *
 * @package ThatSeoAgent
 * @since 2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Breadcrumbs {

    /**
     * Block name.
     */
    const BLOCK = 'thatseoagent/breadcrumbs';

    /**
     * Register the shortcode and the block.
     *
     * Registered even while another SEO plugin is active: a theme calling
     * the function or a post holding the block must not break.
     *
     * @since 2.4.0
     */
    public static function register() {
        add_shortcode( 'thatseoagent_breadcrumbs', array( __CLASS__, 'shortcode' ) );
        add_action( 'init', array( __CLASS__, 'register_block' ) );
    }

    /**
     * The trail of the current view.
     *
     * Every crumb but the last carries the URL it links to; the last is the
     * page itself. Memoised per request: the schema and the visible
     * breadcrumbs both ask.
     *
     * @since 2.4.0 Extracted from ThatSeoAgent_Schema::get_breadcrumb_schema().
     * @return array<int, array{name: string, url: string}> Empty when the
     *         view has no trail beyond the homepage.
     */
    public static function trail() {
        return ThatSeoAgent_Memo::remember(
            'breadcrumbs',
            'request',
            function () {
                return self::build();
            }
        );
    }

    /**
     * The trail behind trail(), without memoisation.
     *
     * @since 2.4.0
     * @return array<int, array{name: string, url: string}>
     */
    private static function build() {
        $crumbs = array(
            array(
                'name' => self::home_label(),
                'url'  => home_url( '/' ),
            ),
        );

        if ( is_singular() ) {
            $post = get_queried_object();
            if ( ! $post instanceof WP_Post ) {
                return array();
            }

            $archive = self::post_type_archive_crumb( $post->post_type );
            if ( $archive ) {
                $crumbs[] = $archive;
            }

            if ( ! is_post_type_hierarchical( $post->post_type ) ) {
                // The primary category and the ones above it.
                foreach ( ThatSeoAgent_Primary_Term::path( $post ) as $term ) {
                    $link = get_term_link( $term );
                    if ( ! is_wp_error( $link ) ) {
                        $crumbs[] = array(
                            'name' => $term->name,
                            'url'  => $link,
                        );
                    }
                }
            } else {
                foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor_id ) {
                    $crumbs[] = array(
                        'name' => get_the_title( $ancestor_id ),
                        'url'  => (string) get_permalink( $ancestor_id ),
                    );
                }
            }

            if ( ! is_front_page() ) {
                $crumbs[] = array(
                    'name' => get_the_title( $post ),
                    'url'  => '',
                );
            }
        } elseif ( is_post_type_archive() ) {
            $crumbs[] = array(
                'name' => post_type_archive_title( '', false ),
                'url'  => '',
            );
        } elseif ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();

            if ( $term instanceof WP_Term ) {
                $taxonomy = get_taxonomy( $term->taxonomy );

                // A taxonomy that belongs to a single post type with an
                // archive — a product category — sits under that archive.
                if ( $taxonomy && 1 === count( $taxonomy->object_type ) ) {
                    $archive = self::post_type_archive_crumb( $taxonomy->object_type[0] );
                    if ( $archive ) {
                        $crumbs[] = $archive;
                    }
                }

                foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
                    $ancestor = get_term( $ancestor_id, $term->taxonomy );
                    $link     = $ancestor instanceof WP_Term ? get_term_link( $ancestor ) : '';
                    if ( $ancestor instanceof WP_Term && ! is_wp_error( $link ) ) {
                        $crumbs[] = array(
                            'name' => $ancestor->name,
                            'url'  => $link,
                        );
                    }
                }

                $crumbs[] = array(
                    'name' => $term->name,
                    'url'  => '',
                );
            }
        } elseif ( is_author() ) {
            $author = get_queried_object();
            if ( $author instanceof WP_User ) {
                $crumbs[] = array(
                    'name' => $author->display_name,
                    'url'  => '',
                );
            }
        } elseif ( is_home() && ! is_front_page() ) {
            $crumbs[] = array(
                'name' => get_the_title( (int) get_option( 'page_for_posts' ) ),
                'url'  => '',
            );
        }

        foreach ( $crumbs as $index => $crumb ) {
            $crumbs[ $index ]['name'] = trim( html_entity_decode( wp_strip_all_tags( (string) $crumb['name'] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
        }

        /**
         * Filter the breadcrumb trail, for the schema and the visible
         * breadcrumbs alike.
         *
         * @since 2.4.0
         * @param array<int, array{name: string, url: string}> $crumbs Trail, homepage first.
         */
        $crumbs = (array) apply_filters( 'thatseoagent_breadcrumb_trail', $crumbs );

        // A lone "Home" is not a trail.
        return count( $crumbs ) < 2 ? array() : array_values( $crumbs );
    }

    /**
     * Whether every crumb can be published: each has a name, and each but
     * the last has a link. A trail with a gap misleads more than no trail.
     *
     * @since 2.4.0
     * @param array $crumbs Trail.
     * @return bool
     */
    public static function is_complete( array $crumbs ) {
        $last = count( $crumbs ) - 1;

        foreach ( array_values( $crumbs ) as $index => $crumb ) {
            if ( empty( $crumb['name'] ) || ( $index < $last && empty( $crumb['url'] ) ) ) {
                return false;
            }
        }

        return $last >= 1;
    }

    /**
     * The name of the first crumb.
     *
     * @since 2.4.0
     * @return string
     */
    private static function home_label() {
        /**
         * Filter the name of the homepage crumb.
         *
         * @since 2.4.0
         * @param string $label Default "Home", translated.
         */
        return (string) apply_filters( 'thatseoagent_breadcrumb_home', __( 'Home', 'thatseoagent' ) );
    }

    /**
     * The crumb for a custom post type's archive, when it has one.
     *
     * Posts and pages have no archive of their own in the trail: the blog
     * index is not a parent of each post.
     *
     * @since 1.16.0 In ThatSeoAgent_Schema.
     * @param string $post_type Post type.
     * @return array{name: string, url: string}|null
     */
    private static function post_type_archive_crumb( $post_type ) {
        if ( in_array( $post_type, array( 'post', 'page' ), true ) ) {
            return null;
        }

        $object = get_post_type_object( $post_type );
        $link   = get_post_type_archive_link( $post_type );

        if ( ! $object || ! $object->has_archive || ! $link ) {
            return null;
        }

        return array(
            'name' => $object->labels->name,
            'url'  => $link,
        );
    }

    /**
     * The visible breadcrumbs, as HTML.
     *
     * @since 2.4.0
     * @param array $args {
     *     @type string $separator Between crumbs, hidden from screen readers. Default "›".
     *     @type string $label     The nav landmark's name. Default "Breadcrumb", translated.
     *     @type string $class     Class of the nav. Default "thatseoagent-breadcrumbs".
     * }
     * @return string '' when the view has no trail.
     */
    public static function render( array $args = array() ) {
        $crumbs = self::trail();

        if ( ! self::is_complete( $crumbs ) ) {
            return '';
        }

        $args = wp_parse_args(
            $args,
            array(
                'separator' => '›',
                'label'     => __( 'Breadcrumb', 'thatseoagent' ),
                'class'     => 'thatseoagent-breadcrumbs',
            )
        );

        $last  = count( $crumbs ) - 1;
        $items = '';

        foreach ( $crumbs as $index => $crumb ) {
            $separator = $index < $last && '' !== $args['separator']
                ? ' <span class="thatseoagent-breadcrumbs__separator" aria-hidden="true">' . esc_html( $args['separator'] ) . '</span> '
                : '';

            if ( $index === $last ) {
                $items .= '<li class="thatseoagent-breadcrumbs__item"><span aria-current="page">' . esc_html( $crumb['name'] ) . '</span></li>';
            } else {
                $items .= '<li class="thatseoagent-breadcrumbs__item"><a href="' . esc_url( $crumb['url'] ) . '">' . esc_html( $crumb['name'] ) . '</a>' . $separator . '</li>';
            }
        }

        return '<nav class="' . esc_attr( $args['class'] ) . '" aria-label="' . esc_attr( $args['label'] ) . '"><ol class="thatseoagent-breadcrumbs__list">' . $items . '</ol></nav>';
    }

    /**
     * [thatseoagent_breadcrumbs separator="/" label="…" class="…"]
     *
     * @since 2.4.0
     * @param array|string $atts Shortcode attributes.
     * @return string
     */
    public static function shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
                'separator' => '›',
                'label'     => __( 'Breadcrumb', 'thatseoagent' ),
                'class'     => 'thatseoagent-breadcrumbs',
            ),
            $atts,
            'thatseoagent_breadcrumbs'
        );

        return self::render( array_map( 'sanitize_text_field', $atts ) );
    }

    /**
     * Register the block, rendered on the server.
     *
     * @since 2.4.0
     */
    public static function register_block() {
        wp_register_script(
            'thatseoagent-breadcrumbs-editor',
            THATSEOAGENT_PLUGIN_URL . 'assets/blocks/breadcrumbs.js',
            array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-block-editor', 'wp-components' ),
            THATSEOAGENT_VERSION,
            true
        );
        wp_set_script_translations( 'thatseoagent-breadcrumbs-editor', 'thatseoagent', THATSEOAGENT_PLUGIN_DIR . 'languages' );

        register_block_type(
            self::BLOCK,
            array(
                'api_version'     => 3,
                'title'           => __( 'Breadcrumbs', 'thatseoagent' ),
                'description'     => __( 'Where the current page sits in the site, as the search results show it.', 'thatseoagent' ),
                'category'        => 'theme',
                'icon'            => 'arrow-right-alt2',
                'keywords'        => array( 'breadcrumb', 'migas', 'navigation' ),
                'editor_script'   => 'thatseoagent-breadcrumbs-editor',
                'attributes'      => array(
                    'separator' => array(
                        'type'    => 'string',
                        'default' => '›',
                    ),
                ),
                'supports'        => array(
                    'html'       => false,
                    'align'      => array( 'wide', 'full' ),
                    'typography' => array( 'fontSize' => true ),
                    'spacing'    => array( 'margin' => true, 'padding' => true ),
                    'color'      => array( 'text' => true, 'link' => true ),
                ),
                'render_callback' => array( __CLASS__, 'render_block' ),
            )
        );
    }

    /**
     * The block's HTML: the breadcrumbs, with the block's own classes and
     * styles from the editor.
     *
     * @since 2.4.0
     * @param array $attributes Block attributes.
     * @return string
     */
    public static function render_block( $attributes ) {
        $html = self::render(
            array(
                'separator' => isset( $attributes['separator'] ) ? sanitize_text_field( $attributes['separator'] ) : '›',
            )
        );

        if ( '' === $html ) {
            return '';
        }

        $wrapper = get_block_wrapper_attributes( array( 'class' => 'thatseoagent-breadcrumbs' ) );

        return preg_replace( '/^<nav class="thatseoagent-breadcrumbs"/', '<nav ' . $wrapper, $html, 1 );
    }
}
