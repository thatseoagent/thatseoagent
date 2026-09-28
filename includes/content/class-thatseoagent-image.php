<?php
/**
 * The image a page is represented by.
 *
 * The single answer to "which image stands for this post?", read by the
 * Open Graph, Twitter and Pinterest tags, the schema's primary image and the
 * Markdown version; and, since 2.7.0, to "which images does this post
 * have?" (the Product markup's) and "how is an image said in the schema?"
 * (every ImageObject, logos included). Before
 * 2.4.0 it was the featured image or, failing that, the theme's logo — a
 * small, often transparent picture that previews badly — and the images in
 * the post were never looked at. The chain for sharing, first found wins:
 *
 *     1. the post's own sharing image, chosen in the SEO meta box
 *     2. the default sharing image from the site identity
 *     3. the post's featured image
 *     4. the theme's logo
 *
 * Listings, which have no image of their own, skip 1 and 3.
 *
 * The default sharing image stands before the featured image: a catalog's
 * featured images are often too small for a preview, and the site owner
 * who set a default chose it for sharing. Since 2.10.0 a post without
 * either is shared with its featured image rather than the logo — a
 * product with its photo. Gallery and content images are never shared:
 * a theme's decorative images in the content would win. The schema's
 * primary image still looks for the post's own, in order: featured image,
 * a catalog entry's first gallery image, the first image in the content
 * (galleries included).
 *
 * Only images count: an attachment that is not an image file, or whose
 * address is not absolute, is left out wherever it would be used.
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
        if ( 'share' !== $purpose ) {
            $image = self::own( $post, $purpose );

            return $image ? $image : self::site_image( $purpose );
        }

        // For sharing: the image chosen for it, the site's default, the
        // featured image, the logo.
        $chosen   = (int) ThatSeoAgent_Post_Seo::get( $post, 'share_image' );
        $featured = (int) get_post_thumbnail_id( $post );

        $image = $chosen ? self::describe( $chosen, '', $purpose, 'chosen' ) : null;
        if ( ! $image ) {
            $image = self::default_image( $purpose );
        }
        if ( ! $image && $featured ) {
            $image = self::describe( $featured, '', $purpose, 'featured' );
        }

        return $image ? $image : self::logo( $purpose );
    }

    /**
     * The image of a post's own — featured, gallery or in the content —
     * without the site's image or logo to fall back on: the one the
     * schema's primary image, Pinterest and the Markdown version show.
     *
     * @since 2.7.0
     * @param WP_Post $post    Post.
     * @param string  $purpose 'share' or 'schema'.
     * @return array{id: int, url: string, width: int, height: int, type: string, alt: string, source: string}|null
     */
    public static function own( WP_Post $post, $purpose = 'schema' ) {
        $found = ThatSeoAgent_Memo::remember(
            'image_source',
            $post->ID,
            function () use ( $post ) {
                return self::find( $post );
            }
        );

        return $found ? self::describe( $found['id'], $found['url'], $purpose, $found['source'] ) : null;
    }

    /**
     * Every image a post has for its structured data: the featured image
     * and a catalog entry's gallery, in that order, each once.
     *
     * @since 2.7.0 Moved from ThatSeoAgent_Product.
     * @param WP_Post $post Post.
     * @return array{images: array<int, array>, invalid: int} `invalid`
     *         counts the attachments left out: not images, or with no
     *         absolute address.
     */
    public static function all( WP_Post $post ) {
        $featured = (int) get_post_thumbnail_id( $post );
        $ids      = array_unique( array_filter( array_merge( array( $featured ), ThatSeoAgent_Product::gallery_ids( $post ) ) ) );

        $images  = array();
        $invalid = 0;

        foreach ( $ids as $id ) {
            $image = self::describe( (int) $id, '', 'schema', $featured === (int) $id ? 'featured' : 'gallery' );
            if ( $image ) {
                $images[] = $image;
            } else {
                $invalid++;
            }
        }

        return array(
            'images'  => $images,
            'invalid' => $invalid,
        );
    }

    /**
     * One attachment, described for a purpose.
     *
     * @since 2.7.0
     * @param int    $id      Attachment ID.
     * @param string $purpose 'share' or 'schema'.
     * @return array{id: int, url: string, width: int, height: int, type: string, alt: string, source: string}|null
     */
    public static function of( $id, $purpose = 'schema' ) {
        return (int) $id ? self::describe( (int) $id, '', $purpose, 'attachment' ) : null;
    }

    /**
     * An image as a schema.org ImageObject: its address, its size when
     * known, and its alt text as the caption.
     *
     * @since 2.7.0 Replaces the five that built their own.
     * @param array  $image As for_post(), own(), all() or of() describe it.
     * @param string $id    The node's @id, or ''.
     * @return array
     */
    public static function object( array $image, $id = '' ) {
        $node = array( '@type' => 'ImageObject' );

        if ( '' !== $id ) {
            $node['@id'] = $id;
        }

        $node['url'] = $image['url'];

        if ( $image['width'] && $image['height'] ) {
            $node['width']  = $image['width'];
            $node['height'] = $image['height'];
        }

        if ( '' !== $image['alt'] ) {
            $node['caption'] = $image['alt'];
        }

        return $node;
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
        $image = self::default_image( $purpose );

        return $image ? $image : self::logo( $purpose );
    }

    /**
     * The default sharing image: the site identity's, or the filter's.
     *
     * @since 2.10.0 Split from site_image().
     * @param string $purpose 'share' or 'schema'.
     * @return array|null
     */
    private static function default_image( $purpose ) {
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
         * Runs after the default sharing image of the site identity, and
         * before a post's featured image and the theme logo. Return a URL
         * to use it.
         *
         * @since 1.5.0
         * @since 2.10.0 Before the featured image.
         * @param string $url Default ''.
         */
        $url = (string) apply_filters( 'thatseoagent_default_image', '' );

        return '' !== $url ? self::describe( (int) attachment_url_to_postid( $url ), $url, $purpose, 'default' ) : null;
    }

    /**
     * The theme's logo.
     *
     * @since 2.10.0 Split from site_image().
     * @param string $purpose 'share' or 'schema'.
     * @return array|null
     */
    private static function logo( $purpose ) {
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
            return preg_match( '#^https?://#i', $url ) ? array(
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

        // An image file with an absolute address, or nothing: a PDF set as
        // the featured image, or a relative URL, is no image to anyone.
        $type = (string) get_post_mime_type( $id );
        $src  = 0 === strpos( $type, 'image/' ) ? wp_get_attachment_image_src( $id, $size ) : false;
        if ( ! $src || ! preg_match( '#^https?://#i', (string) $src[0] ) ) {
            return null;
        }

        return array(
            'id'     => $id,
            'url'    => (string) $src[0],
            'width'  => (int) $src[1],
            'height' => (int) $src[2],
            'type'   => $type,
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
