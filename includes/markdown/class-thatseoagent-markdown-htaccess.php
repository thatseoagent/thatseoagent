<?php
/**
 * The .htaccess rule that keeps page caches away from Markdown requests.
 *
 * Page cache plugins on Apache — WP Rocket, WP Super Cache in expert mode,
 * W3 Total Cache's disk cache — serve their stored HTML from .htaccess,
 * before WordPress starts. Keyed on the URL alone, they would hand an agent
 * that asked for Markdown the HTML page a browser caused to be cached, and
 * the content negotiation in ThatSeoAgent_Markdown_Endpoint never runs.
 *
 * The rule sends every request whose Accept header asks for Markdown
 * straight to WordPress, with [END] so no later rule rewrites it to a cached
 * file. It does not rewrite to the `.md` URL: WordPress decides, and a URL
 * with no Markdown version — a category, the search page — still gets its
 * HTML.
 *
 * It only works above the cache plugin's rules, which run top to bottom;
 * status() says when another plugin has since written its block above.
 * The rule is written when someone asks for it, never on activation, and
 * only inside its own markers.
 *
 * @package ThatSeoAgent
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Markdown_Htaccess {

    /**
     * Marker around the block, as insert_with_markers() writes them.
     */
    const MARKER = 'ThatSeoAgent Markdown';

    /**
     * Page cache plugins that serve from .htaccess, by the opening line of
     * their block.
     *
     * @since 2.3.0
     * @return array<string, string> Opening line => plugin name.
     */
    public static function cache_blocks() {
        return array(
            '# BEGIN WP Rocket'             => 'WP Rocket',
            '# BEGIN WPSuperCache'          => 'WP Super Cache',
            '# BEGIN W3TC Page Cache core'  => 'W3 Total Cache',
            '# BEGIN WpFastestCache'        => 'WP Fastest Cache',
            '# BEGIN LSCACHE'               => 'LiteSpeed Cache',
        );
    }

    /**
     * Whether the site runs on a server that reads .htaccess rewrites.
     *
     * @since 2.3.0
     * @return bool
     */
    public static function applies() {
        global $is_apache;

        if ( ! $is_apache || ! get_option( 'permalink_structure' ) ) {
            return false;
        }

        require_once ABSPATH . 'wp-admin/includes/misc.php';

        return got_mod_rewrite();
    }

    /**
     * The site's .htaccess.
     *
     * @since 2.3.0
     * @return string
     */
    public static function path() {
        require_once ABSPATH . 'wp-admin/includes/file.php';

        return get_home_path() . '.htaccess';
    }

    /**
     * The block, marker lines included.
     *
     * Two rules, because the homepage is a directory: the second one's
     * "not a file, not a directory" conditions would skip it, and a page
     * cache serves the homepage too.
     *
     * @since 2.3.0
     * @return array<int, string>
     */
    public static function lines() {
        $base   = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
        $target = trailingslashit( '' !== $base ? $base : '/' ) . 'index.php';
        $asks   = array(
            'RewriteCond %{HTTP:Accept} text/(x-)?markdown [NC]',
            'RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$',
        );

        return array_merge(
            array(
                '# BEGIN ' . self::MARKER,
                '# Requests asking for Markdown go to WordPress, never to a cached copy of the HTML page.',
                '<IfModule mod_rewrite.c>',
                'RewriteEngine On',
            ),
            $asks,
            array( 'RewriteRule ^$ ' . $target . ' [END]' ),
            $asks,
            array(
                'RewriteCond %{REQUEST_FILENAME} !-f',
                'RewriteCond %{REQUEST_FILENAME} !-d',
                'RewriteRule ^ ' . $target . ' [END]',
                '</IfModule>',
                '# END ' . self::MARKER,
            )
        );
    }

    /**
     * Where the rule stands.
     *
     * @since 2.3.0
     * @param string $path File to read. Default the site's .htaccess.
     * @return array{applies: bool, present: bool, below: string, writable: bool, lines: string}
     *         `below` names the cache plugin whose block runs first, or ''.
     */
    public static function status( $path = '' ) {
        $path     = '' !== $path ? $path : self::path();
        $contents = self::read( $path );
        $position = strpos( $contents, '# BEGIN ' . self::MARKER );
        $below    = '';

        if ( false !== $position ) {
            foreach ( self::cache_blocks() as $opening => $plugin ) {
                $cache = strpos( $contents, $opening );
                if ( false !== $cache && $cache < $position ) {
                    $below = $plugin;
                    break;
                }
            }
        }

        return array(
            'applies'  => self::applies(),
            'present'  => false !== $position,
            'below'    => $below,
            'writable' => file_exists( $path ) ? wp_is_writable( $path ) : wp_is_writable( dirname( $path ) ),
            'lines'    => implode( "\n", self::lines() ),
        );
    }

    /**
     * Write the block at the top of .htaccess, replacing any earlier copy.
     *
     * At the top because rewrite rules run in order: below a cache plugin's
     * block, the cached file is served first.
     *
     * @since 2.3.0
     * @param string $path File to write. Default the site's .htaccess.
     * @return true|WP_Error
     */
    public static function install( $path = '' ) {
        $path     = '' !== $path ? $path : self::path();
        $contents = self::without_block( self::read( $path ) );
        $contents = implode( "\n", self::lines() ) . "\n\n" . ltrim( $contents );

        return self::write( $path, $contents );
    }

    /**
     * Take the block out of .htaccess, leaving everything else as it was.
     *
     * @since 2.3.0
     * @param string $path File to write. Default the site's .htaccess.
     * @return true|WP_Error
     */
    public static function remove( $path = '' ) {
        $path     = '' !== $path ? $path : self::path();
        $contents = self::read( $path );

        if ( false === strpos( $contents, '# BEGIN ' . self::MARKER ) ) {
            return true;
        }

        return self::write( $path, ltrim( self::without_block( $contents ) ) );
    }

    /**
     * Contents with the block cut out.
     *
     * @since 2.3.0
     * @param string $contents File contents.
     * @return string
     */
    private static function without_block( $contents ) {
        $marker = preg_quote( self::MARKER, '/' );

        return (string) preg_replace( '/# BEGIN ' . $marker . '.*?# END ' . $marker . '\R*/s', '', $contents );
    }

    /**
     * The file's contents, '' when there is none.
     *
     * @since 2.3.0
     * @param string $path File.
     * @return string
     */
    private static function read( $path ) {
        $filesystem = self::filesystem();

        if ( ! $filesystem || ! $filesystem->exists( $path ) ) {
            return '';
        }

        return (string) $filesystem->get_contents( $path );
    }

    /**
     * Write the file.
     *
     * @since 2.3.0
     * @param string $path     File.
     * @param string $contents Contents.
     * @return true|WP_Error
     */
    private static function write( $path, $contents ) {
        $filesystem = self::filesystem();

        if ( ! $filesystem || ! wp_is_writable( file_exists( $path ) ? $path : dirname( $path ) ) ) {
            return new WP_Error(
                'thatseoagent_htaccess_not_writable',
                __( 'WordPress cannot write to .htaccess. Paste the lines into the file by hand, at the very top.', 'thatseoagent' ),
                array( 'status' => 500 )
            );
        }

        if ( ! $filesystem->put_contents( $path, $contents, FS_CHMOD_FILE ) ) {
            return new WP_Error(
                'thatseoagent_htaccess_write_failed',
                __( '.htaccess could not be saved. Nothing was changed.', 'thatseoagent' ),
                array( 'status' => 500 )
            );
        }

        return true;
    }

    /**
     * Direct filesystem access, or null.
     *
     * Only the direct method: the others need credentials the REST request
     * does not carry, and core writes .htaccess the same way.
     *
     * @since 2.3.0
     * @return WP_Filesystem_Base|null
     */
    private static function filesystem() {
        global $wp_filesystem;

        require_once ABSPATH . 'wp-admin/includes/file.php';

        if ( 'direct' !== get_filesystem_method( array(), dirname( self::path() ) ) || ! WP_Filesystem() ) {
            return null;
        }

        return $wp_filesystem;
    }
}
