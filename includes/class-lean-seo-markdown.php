<?php
/**
 * A post as Markdown, for AI agents.
 *
 * An agent fetching a post as HTML pays for the navigation, the sidebar, the
 * footer and the scripts; the content is often under 5% of the bytes. This
 * module turns a post into Markdown with a YAML frontmatter carrying its
 * metadata. Lean_SEO_Markdown_Endpoint serves it at the post's URL plus `.md`.
 *
 * The content comes from Lean_SEO_Content and the description from
 * Lean_SEO_Description, so the Markdown says what the page and its meta tags
 * say — not a third interpretation of the post.
 *
 * @package Lean_SEO
 * @since 1.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Lean_SEO\Dependencies\League\HTMLToMarkdown\HtmlConverter;

class Lean_SEO_Markdown {

    /**
     * Convert a post to Markdown with YAML frontmatter.
     *
     * @since 1.14.0
     * @param WP_Post $post Post object.
     * @return string
     */
    public static function convert( WP_Post $post ) {
        // Make the post current so blocks and shortcodes that rely on
        // get_the_ID() / global $post render this post, not whatever the
        // request left behind.
        $previous_post   = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
        $GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        setup_postdata( $post );

        $frontmatter = self::frontmatter( $post );
        $content     = self::content( $post );

        $GLOBALS['post'] = $previous_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        if ( $previous_post instanceof WP_Post ) {
            setup_postdata( $previous_post );
        }

        return $frontmatter . "\n" . $content;
    }

    /**
     * Build the YAML frontmatter for a post.
     *
     * @since 1.14.0
     * @param WP_Post $post Post object.
     * @return string
     */
    private static function frontmatter( WP_Post $post ) {
        /**
         * Filter the frontmatter fields of a post's Markdown.
         *
         * Scalars become `key: "value"`, lists become YAML sequences and
         * associative arrays become one level of nested mapping.
         *
         * @since 1.14.0
         * @param array   $fields Frontmatter fields.
         * @param WP_Post $post   Post object.
         */
        $fields = apply_filters( 'lean_seo_markdown_frontmatter', self::fields( $post ), $post );

        $yaml = "---\n";

        foreach ( (array) $fields as $key => $value ) {
            if ( null === $value || ( is_string( $value ) && '' === trim( $value ) ) ) {
                continue;
            }

            $key = self::yaml_key( (string) $key );

            if ( is_array( $value ) ) {
                $yaml .= $key . ":\n";

                if ( self::is_assoc( $value ) ) {
                    foreach ( $value as $subkey => $subvalue ) {
                        // One level of nesting is all the fields need.
                        if ( is_array( $subvalue ) ) {
                            continue;
                        }
                        $yaml .= '  ' . self::yaml_key( (string) $subkey ) . ': "' . self::yaml_escape( $subvalue ) . "\"\n";
                    }
                } else {
                    foreach ( $value as $item ) {
                        $yaml .= '  - "' . self::yaml_escape( $item ) . "\"\n";
                    }
                }
            } elseif ( is_bool( $value ) ) {
                $yaml .= $key . ': ' . ( $value ? 'true' : 'false' ) . "\n";
            } elseif ( is_int( $value ) || is_float( $value ) ) {
                $yaml .= $key . ': ' . $value . "\n";
            } else {
                $yaml .= $key . ': "' . self::yaml_escape( $value ) . "\"\n";
            }
        }

        return $yaml . "---\n";
    }

    /**
     * The default frontmatter fields for a post.
     *
     * @since 1.14.0
     * @param WP_Post $post Post object.
     * @return array
     */
    private static function fields( WP_Post $post ) {
        $fields = array(
            'title'     => Lean_SEO_Content::to_text( $post->post_title ),
            'seo_title' => Lean_SEO_Post_Seo::get( $post, 'title' ),
            'date'      => get_the_date( 'c', $post ),
            'modified'  => get_the_modified_date( 'c', $post ),
            'author'    => get_the_author_meta( 'display_name', $post->post_author ),
            'permalink' => get_permalink( $post ),
            'type'      => $post->post_type,
        );

        if ( ! empty( $post->post_excerpt ) ) {
            $fields['excerpt'] = Lean_SEO_Content::to_text( $post->post_excerpt );
        }

        // The same description the page's meta tags carry.
        $fields['description'] = Lean_SEO_Description::for_post( $post );

        if ( 'post' === $post->post_type ) {
            $categories = get_the_category( $post->ID );
            if ( ! empty( $categories ) ) {
                $fields['categories'] = wp_list_pluck( $categories, 'name' );
            }

            $tags = get_the_tags( $post->ID );
            if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
                $fields['tags'] = wp_list_pluck( $tags, 'name' );
            }
        }

        if ( has_post_thumbnail( $post->ID ) ) {
            $thumbnail_id = get_post_thumbnail_id( $post->ID );
            $image_url    = wp_get_attachment_url( $thumbnail_id );

            if ( $image_url ) {
                $fields['featured_image'] = $image_url;

                $alt_text = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true );
                if ( ! empty( $alt_text ) ) {
                    $fields['featured_image_alt'] = $alt_text;
                }
            }
        }

        /**
         * Filter whether custom fields go into the frontmatter.
         *
         * Off by default: post meta that is not registered with show_in_rest
         * is not public, and plugins routinely store emails, IDs and tokens
         * in keys without a leading underscore.
         *
         * @since 1.14.0
         * @param bool    $include Default false.
         * @param WP_Post $post    Post object.
         */
        if ( apply_filters( 'lean_seo_markdown_include_custom_fields', false, $post ) ) {
            $custom_fields = self::custom_fields( $post->ID );
            if ( ! empty( $custom_fields ) ) {
                $fields['custom_fields'] = $custom_fields;
            }
        }

        return $fields;
    }

    /**
     * A post's public custom fields.
     *
     * @since 1.14.0
     * @param int $post_id Post ID.
     * @return array
     */
    private static function custom_fields( $post_id ) {
        $custom_fields = array();

        foreach ( get_post_meta( $post_id ) as $key => $values ) {
            if ( is_protected_meta( $key, 'post' ) ) {
                continue;
            }

            // Serialized values are PHP internals, not readable content.
            $values = array_values(
                array_filter(
                    $values,
                    function ( $value ) {
                        return ! is_serialized( $value );
                    }
                )
            );

            if ( empty( $values ) ) {
                continue;
            }

            $custom_fields[ $key ] = 1 === count( $values ) ? $values[0] : $values;
        }

        /**
         * Filter the custom fields included in the frontmatter.
         *
         * Only runs when lean_seo_markdown_include_custom_fields is true.
         * Use it to keep an allowlist of keys.
         *
         * @since 1.14.0
         * @param array $custom_fields Meta key => value(s).
         * @param int   $post_id       Post ID.
         */
        return (array) apply_filters( 'lean_seo_markdown_custom_fields', $custom_fields, $post_id );
    }

    /**
     * Convert a post's rendered content to Markdown.
     *
     * @since 1.14.0
     * @param WP_Post $post Post object.
     * @return string
     */
    private static function content( WP_Post $post ) {
        /**
         * Filter the HTML converted to Markdown.
         *
         * Page builders that keep their layout outside post_content
         * (Elementor, Divi, …) can return their rendered HTML here.
         *
         * @since 1.14.0
         * @param string  $html Rendered content, from Lean_SEO_Content::html().
         * @param WP_Post $post Post object.
         */
        $html = (string) apply_filters( 'lean_seo_markdown_html', Lean_SEO_Content::html( $post ), $post );

        $markdown = self::converter()->convert( $html );
        $markdown = preg_replace( '/\n{3,}/', "\n\n", $markdown );

        return trim( $markdown );
    }

    /**
     * The shared HTML-to-Markdown converter.
     *
     * @since 1.14.0
     * @return HtmlConverter
     */
    private static function converter() {
        static $converter = null;

        if ( null === $converter ) {
            $converter = new HtmlConverter(
                array(
                    'strip_tags'        => true,
                    'remove_nodes'      => 'script style',
                    'header_style'      => 'atx',
                    'bold_style'        => '**',
                    'italic_style'      => '_',
                    'hard_break'        => true,
                    'preserve_comments' => false,
                )
            );
        }

        return $converter;
    }

    /**
     * Whether an array is associative.
     *
     * @since 1.14.0
     * @param array $value Array to check.
     * @return bool
     */
    private static function is_assoc( array $value ) {
        return array() !== $value && array_keys( $value ) !== range( 0, count( $value ) - 1 );
    }

    /**
     * A YAML mapping key, quoted when it holds anything but a plain name.
     *
     * Custom field keys are arbitrary strings; an unquoted `a: b` or a
     * newline would break the frontmatter or add fields to it.
     *
     * @since 1.14.0
     * @param string $key Key.
     * @return string
     */
    private static function yaml_key( $key ) {
        if ( preg_match( '/^[A-Za-z_][A-Za-z0-9_-]*$/', $key ) ) {
            return $key;
        }

        return '"' . self::yaml_escape( $key ) . '"';
    }

    /**
     * Escape a scalar for a double-quoted YAML string.
     *
     * @since 1.14.0
     * @param mixed $value Value.
     * @return string
     */
    private static function yaml_escape( $value ) {
        if ( is_bool( $value ) ) {
            $value = $value ? 'true' : 'false';
        }

        return str_replace(
            array( '\\', '"', "\n", "\r", "\t" ),
            array( '\\\\', '\\"', '\\n', '\\r', '\\t' ),
            (string) $value
        );
    }
}
