<?php
/**
 * The image a page is represented by.
 *
 * The single answer to "which image stands for this post?", read by the
 * Open Graph and Twitter tags and by the schema's primary image. Before
 * 2.4.0 it was the featured image or, failing that, the theme's logo — a
 * small, often transparent picture that previews badly — and the images in
 * the post were never looked at. The chain, first found wins:
 *
 *     1. the featured image
 *     2. a catalog entry's first gallery image
 *     3. the first image in the content (galleries included)
 *     4. the default sharing image from the site identity
 *     5. the theme's logo
 *
 * Listings, which have no image of their own, start at 4.
 *
 * For sharing, the largest size that weighs 2 MB or less is chosen:
 * Facebook, WhatsApp and LinkedIn drop heavier images, and the post then
 * previews with none. The schema keeps its own size (full by default).
 *
 * @package ThatSeoAgent
 * @since 2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Image {

    /**
     * Heaviest image offered for sharing, in bytes.
     */
    const SHARE_MAX_BYTES = 2097152;

    /**
     * The image that represents the current view, for sharing.
     *
     * @since 2.4.0
     * @return array{id: int, url: string, width: int, height: int, type: string, alt: string, source: string}|null
     */
    public static function for_request() {
        if ( is_singular() ) {
            $post = get_queried_object();
            if ( $post instanceof WP_Post ) {
                return self::for_post( $post );
            }
        }

        return self::site_image( 'share' );
    }

    /**
     * The image that represents a post.
     *
     * @since 2.4.0
     * @param WP_Post $post    Post.
     * @param string  $purpose 'share' for Open Graph and Twitter, 'schema'
     *                         for structured data.
     * @return array{id: int, url: string, width: int, height: int, type: string, alt: string, source: string}|null
     */
    public static function for_post( WP_Post $post, $purpose = 'share' ) {
        $found = ThatSeoAgent_Memo::remember(
            'image_source',
            $post->ID,
            function () use ( $post ) {
                return self::find( $post );
            }
        );

        if ( $found ) {
            $image = self::describe( $found['id'], $found['url'], $purpose, $found['source'] );
            if ( $image ) {
                return $image;
            }
        }

        return self::site_image( $purpose );
    }

    /**
     * The first image of a post's own, before the site-wide fallbacks.
     *
     * @since 2.4.0
     * @param WP_Post $post Post.
     * @return array{id: int, url: string, source: string}|null
     */
    private static function find( WP_Post $post ) {
        $thumbnail = (int) get_post_thumbnail_id( $post );
        if ( $thumbnail ) {
            return array(
                'id'     => $thumbnail,
                'url'    => '',
                'source' => 'featured',
            );
        }

        $gallery = ThatSeoAgent_Product::gallery_ids( $post );
        if ( $gallery ) {
            return array(
                'id'     => (int) $gallery[0],
                'url'    => '',
                'source' => 'gallery',
            );
        }

        // Behind a password, the content is not the page's to show.
        if ( post_password_required( $post ) ) {
            return null;
        }

        return self::first_content_image( ThatSeoAgent_Content::html( $post ) );
    }

    /**
     * The first image in rendered content.
     *
     * Blocks mark their images with a `wp-image-{id}` class; otherwise the
     * address is looked up in the media library, and an image from another
     * site is used as it is.
     *
     * @since 2.4.0
     * @param string $html Rendered content.
     * @return array{id: int, url: string, source: string}|null
     */
    private static function first_content_image( $html ) {
        $tags = new WP_HTML_Tag_Processor( (string) $html );

        while ( $tags->next_tag( 'img' ) ) {
            $src = $tags->get_attribute( 'src' );
            if ( ! is_string( $src ) || '' === trim( $src ) || 0 === strpos( $src, 'data:' ) ) {
                continue;
            }

            $class = (string) $tags->get_attribute( 'class' );
            $id    = preg_match( '/\bwp-image-(\d+)\b/', $class, $match ) ? (int) $match[1] : 0;

            if ( ! $id ) {
                $id = (int) attachment_url_to_postid( preg_replace( '/-\d+x\d+(?=\.\w+$)/', '', $src ) );
            }

            return array(
                'id'     => $id,
                'url'    => $id ? '' : esc_url_raw( $src ),
                'source' => 'content',
            );
        }

        return null;
    }

    /**
     * The site's own image: the default sharing image, else the theme logo.
     *
     * @since 2.4.0
     * @param string $purpose 'share' or 'schema'.
     * @return array|null
     */
    private static function site_image( $purpose ) {
        $identity = ThatSeoAgent_Identity::get_settings();

        if ( ! empty( $identity['default_og_image_id'] ) ) {
            $image = self::describe( (int) $identity['default_og_image_id'], '', $purpose, 'default' );
            if ( $image ) {
                return $image;
            }
        }

        /**
         * Filter the image used when a page has none of its own.
         *
         * Runs after the default sharing image of the site identity and
         * before the theme logo. Return a URL to use it.
         *
         * @since 1.5.0
         * @param string $url Default ''.
         */
        $url = (string) apply_filters( 'thatseoagent_default_image', '' );
        if ( '' !== $url ) {
            return self::describe( (int) attachment_url_to_postid( $url ), $url, $purpose, 'default' );
        }

        $logo = (int) get_theme_mod( 'custom_logo' );

        return $logo ? self::describe( $logo, '', $purpose, 'logo' ) : null;
    }

    /**
     * Everything the tags say about an image.
     *
     * @since 2.4.0
     * @param int    $id      Attachment ID, or 0 for an image outside the library.
     * @param string $url     URL when there is no attachment.
     * @param string $purpose 'share' or 'schema'.
     * @param string $source  Where the chain found it.
     * @return array{id: int, url: string, width: int, height: int, type: string, alt: string, source: string}|null
     */
    private static function describe( $id, $url, $purpose, $source ) {
        if ( ! $id ) {
            return '' !== $url ? array(
                'id'     => 0,
                'url'    => $url,
                'width'  => 0,
                'height' => 0,
                'type'   => '',
                'alt'    => '',
                'source' => $source,
            ) : null;
        }

        /**
         * Image size used for the Article image and the page's primary image.
         *
         * Google wants at least 1200px wide; the 'large' size caps at 1024 by
         * default, so 'full' is the safer default.
         *
         * @since 1.10.0
         * @param string $size Registered image size. Default 'full'.
         */
        $size = 'schema' === $purpose ? (string) apply_filters( 'thatseoagent_schema_image_size', 'full' ) : self::share_size( $id );

        $src = wp_get_attachment_image_src( $id, $size );
        if ( ! $src ) {
            return null;
        }

        return array(
            'id'     => $id,
            'url'    => (string) $src[0],
            'width'  => (int) $src[1],
            'height' => (int) $src[2],
            'type'   => (string) get_post_mime_type( $id ),
            'alt'    => trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ),
            'source' => $source,
        );
    }

    /**
     * The largest size of an image that is light enough to share.
     *
     * @since 2.4.0
     * @param int $id Attachment ID.
     * @return string Registered size name.
     */
    private static function share_size( $id ) {
        /**
         * Filter the image size used for Open Graph and Twitter.
         *
         * Return a registered size name to use it whatever it weighs, or ''
         * (default) to take the largest one under 2 MB.
         *
         * @since 2.4.0
         * @param string $size Default ''.
         * @param int    $id   Attachment ID.
         */
        $forced = (string) apply_filters( 'thatseoagent_og_image_size', '', $id );
        if ( '' !== $forced ) {
            return $forced;
        }

        $meta = wp_get_attachment_metadata( $id );
        $file = get_attached_file( $id );

        foreach ( array( 'full', 'large', 'medium_large' ) as $size ) {
            $bytes = 0;

            if ( 'full' === $size ) {
                $bytes = ! empty( $meta['filesize'] ) ? (int) $meta['filesize'] : ( $file && file_exists( $file ) ? (int) filesize( $file ) : 0 );
            } elseif ( ! empty( $meta['sizes'][ $size ] ) ) {
                $variant = $meta['sizes'][ $size ];
                $bytes   = ! empty( $variant['filesize'] ) ? (int) $variant['filesize'] : ( $file && file_exists( dirname( $file ) . '/' . $variant['file'] ) ? (int) filesize( dirname( $file ) . '/' . $variant['file'] ) : 0 );
            } else {
                continue;
            }

            // Unknown weight: take it, as before, rather than skip the image.
            if ( ! $bytes || $bytes <= self::SHARE_MAX_BYTES ) {
                return $size;
            }
        }

        return 'medium_large';
    }
}
