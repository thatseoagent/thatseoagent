<?php
/**
 * Whether content negotiation reaches the site's visitors: the on-demand
 * Markdown check.
 *
 * Negotiation happens inside WordPress, and anything that answers before
 * WordPress — a page cache plugin serving from .htaccess, a CDN, a server
 * cache — can undo it without WordPress ever knowing. So the check asks
 * from outside, the way an agent and a browser would: one post, requested
 * first for Markdown and then for HTML. The order matters: a cache that
 * stored the Markdown answer would hand it to the browser that comes next.
 *
 * When it fails, the response headers usually say which layer answered, and
 * the check says what to change there. It only runs when someone asks.
 *
 * @package ThatSeoAgent
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Markdown_Check {

    /**
     * What a browser sends.
     */
    const BROWSER_ACCEPT = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';

    /**
     * Run the check.
     *
     * @since 2.3.0
     * @return array{checked: int, url: string, verdict: string, headline: string, detail: string, agent: array, browser: array, layer: string, advice: string, htaccess: array}
     */
    public static function run() {
        $post = self::sample_post();

        if ( ! $post ) {
            return self::result( 'no_post', '', array(), array(), '' );
        }

        $url     = get_permalink( $post );
        $agent   = self::request( $url, 'text/markdown' );
        $browser = self::request( $url, self::BROWSER_ACCEPT );
        $layer   = self::layer( $agent, $browser );

        if ( ! $agent['status'] || ! $browser['status'] ) {
            $verdict = 'no_answer';
        } elseif ( $browser['markdown'] ) {
            $verdict = 'markdown_to_browsers';
        } elseif ( ! $agent['markdown'] ) {
            $verdict = 'html_to_agents';
        } elseif ( self::converted_by_cloudflare( $agent ) ) {
            $verdict = 'cloudflare_markdown';
        } else {
            $verdict = 'works';
        }

        $result = self::result( $verdict, $url, $agent, $browser, $layer );

        ThatSeoAgent_Checks::record( 'markdown', $result );

        return $result;
    }

    /**
     * The last check as the screen shows it: the result, when it ran, and
     * what changed since the one before.
     *
     * @since 2.7.0
     * @return array|null Null before the first check.
     */
    public static function for_screen() {
        $last = ThatSeoAgent_Checks::last( 'markdown' );

        return $last ? $last + array(
            'when'    => ThatSeoAgent_Checks::when( $last ),
            'changes' => self::changes(),
        ) : null;
    }

    /**
     * What changed between the last check and the one before it.
     *
     * @since 2.7.0
     * @return array<int, string> One line per change, empty when nothing
     *                            changed or there is nothing to compare.
     */
    public static function changes() {
        $last     = ThatSeoAgent_Checks::last( 'markdown' );
        $previous = ThatSeoAgent_Checks::previous( 'markdown' );

        if ( ! $last || ! $previous || $last['verdict'] === $previous['verdict'] ) {
            return array();
        }

        /* translators: %s: the previous check's headline. */
        return array( sprintf( __( 'The check before said: %s', 'thatseoagent' ), $previous['headline'] ) );
    }

    /**
     * This module's part of the bulletin: what the last check found, while
     * it is recent. A check that got no answer is not a failure.
     *
     * @since 2.7.0
     * @return array{observations: array, warnings: array}
     */
    public static function bulletin() {
        $check    = ThatSeoAgent_Checks::fresh( 'markdown' );
        $warnings = array();

        if ( $check && 'markdown_to_browsers' === $check['verdict'] ) {
            $warnings[] = ThatSeoAgent_Bulletin::warning(
                'red',
                __( 'A cache hands the Markdown version to browsers', 'thatseoagent' ),
                __( 'A cache stored the answer given to an agent and served it to the browser that came next: people may see plain text instead of the page. Clear the cache, and keep requests asking for Markdown out of it.', 'thatseoagent' ) . ' ' . ThatSeoAgent_Checks::when( $check ),
                __( 'See the Markdown check', 'thatseoagent' ),
                'markdown'
            );
        } elseif ( $check && 'html_to_agents' === $check['verdict'] ) {
            $warnings[] = ThatSeoAgent_Bulletin::warning(
                'yellow',
                __( 'Agents that ask for Markdown get the HTML page', 'thatseoagent' ),
                __( 'Something answers before WordPress can, most often a page cache: agents get the page a browser caused to be stored. Nothing breaks for people.', 'thatseoagent' ) . ' ' . ThatSeoAgent_Checks::when( $check ),
                __( 'See the Markdown check', 'thatseoagent' ),
                'markdown'
            );
        }

        return array(
            'observations' => array(),
            'warnings'     => $warnings,
        );
    }

    /**
     * The post to ask for: the latest one with a Markdown version.
     *
     * @since 2.3.0
     * @return WP_Post|null
     */
    private static function sample_post() {
        $posts = get_posts(
            array(
                'post_type'      => ThatSeoAgent_Markdown_Endpoint::post_types(),
                'post_status'    => 'publish',
                'has_password'   => false,
                'posts_per_page' => 5,
                'orderby'        => 'modified',
                'order'          => 'DESC',
                'no_found_rows'  => true,
            )
        );

        foreach ( $posts as $post ) {
            if ( '' !== ThatSeoAgent_Markdown_Endpoint::url_for( $post ) ) {
                return $post;
            }
        }

        return null;
    }

    /**
     * One request, reduced to what the check reads.
     *
     * @since 2.3.0
     * @param string $url    URL.
     * @param string $accept Accept header.
     * @return array{status: int, type: string, markdown: bool, vary: bool, headers: array<string, string>, body: string, error: string}
     */
    private static function request( $url, $accept ) {
        $response = ThatSeoAgent_Loopback::get(
            $url,
            array(
                'headers'    => array( 'Accept' => $accept ),
                'user-agent' => 'ThatSeoAgent/' . THATSEOAGENT_VERSION . ' (markdown check; +' . home_url( '/' ) . ')',
            )
        );

        $headers = $response['headers'];
        $type    = isset( $headers['content-type'] ) ? $headers['content-type'] : '';
        $vary    = isset( $headers['vary'] ) ? $headers['vary'] : '';

        return array(
            'status'   => $response['status'],
            'type'     => $type,
            'markdown' => (bool) preg_match( '#text/(x-)?markdown#i', $type ),
            'vary'     => (bool) preg_match( '/(^|,)\s*(accept|\*)\s*(,|$)/i', $vary ),
            'headers'  => $headers,
            // Enough to spot a page cache plugin's signature comment.
            'body'     => substr( $response['body'], -2000 ),
            'error'    => $response['error'],
        );
    }

    /**
     * Whether the Markdown is Cloudflare's conversion of the HTML page,
     * not ThatSeoAgent's.
     *
     * Markdown for Agents stamps each answer it converts with its token
     * counts; ThatSeoAgent's Markdown carries neither header.
     *
     * @since 2.6.0
     * @param array $response Request result.
     * @return bool
     */
    private static function converted_by_cloudflare( array $response ) {
        return isset( $response['headers']['x-markdown-tokens'] ) || isset( $response['headers']['x-original-tokens'] );
    }

    /**
     * Which layer answered, from the traces caches leave.
     *
     * @since 2.3.0
     * @param array $agent   Agent request.
     * @param array $browser Browser request.
     * @return string 'cloudflare', 'litespeed', 'varnish', 'nginx', a page
     *                cache plugin's name, 'cache' for an unnamed one, or ''.
     */
    private static function layer( array $agent, array $browser ) {
        foreach ( array( $agent, $browser ) as $response ) {
            $headers = $response['headers'];
            $server  = isset( $headers['server'] ) ? strtolower( $headers['server'] ) : '';

            // Cloudflare stamps cf-ray on everything it proxies; only a copy
            // it served from its own cache makes it the layer that answered.
            $cloudflare = isset( $headers['cf-ray'] ) || false !== strpos( $server, 'cloudflare' );
            if ( $cloudflare && isset( $headers['cf-cache-status'] ) && in_array( strtoupper( $headers['cf-cache-status'] ), array( 'HIT', 'STALE', 'UPDATING', 'REVALIDATED' ), true ) ) {
                return 'cloudflare';
            }
            if ( isset( $headers['x-litespeed-cache'] ) ) {
                return 'litespeed';
            }
            if ( isset( $headers['x-varnish'] ) || ( isset( $headers['via'] ) && false !== stripos( $headers['via'], 'varnish' ) ) ) {
                return 'varnish';
            }
            if ( isset( $headers['x-fastcgi-cache'] ) || isset( $headers['x-proxy-cache'] ) || isset( $headers['x-nginx-cache'] ) ) {
                return 'nginx';
            }
        }

        // Page cache plugins sign the pages they store.
        $signatures = array(
            'This website is like a Rocket' => 'WP Rocket',
            'WP-Super-Cache'                => 'WP Super Cache',
            'W3 Total Cache'                => 'W3 Total Cache',
            'WP Fastest Cache'              => 'WP Fastest Cache',
        );
        foreach ( array( $agent, $browser ) as $response ) {
            foreach ( $signatures as $signature => $plugin ) {
                if ( false !== strpos( $response['body'], $signature ) ) {
                    return $plugin;
                }
            }
        }

        foreach ( array( $agent, $browser ) as $response ) {
            if ( isset( $response['headers']['x-cache'] ) || ( isset( $response['headers']['age'] ) && (int) $response['headers']['age'] > 0 ) ) {
                return 'cache';
            }
        }

        return '';
    }

    /**
     * The result, in the words the screen shows.
     *
     * @since 2.3.0
     * @param string $verdict 'works', 'cloudflare_markdown', 'html_to_agents', 'markdown_to_browsers', 'no_answer' or 'no_post'.
     * @param string $url     URL asked for.
     * @param array  $agent   Agent request.
     * @param array  $browser Browser request.
     * @param string $layer   See layer().
     * @return array
     */
    private static function result( $verdict, $url, array $agent, array $browser, $layer ) {
        $htaccess = ThatSeoAgent_Markdown_Htaccess::status();

        switch ( $verdict ) {
            case 'works':
                $headline = __( 'Agents that ask for Markdown get it', 'thatseoagent' );
                $detail   = __( 'The page answered the agent with Markdown and the browser with HTML.', 'thatseoagent' );
                if ( ! $agent['vary'] || ! $browser['vary'] ) {
                    $detail .= ' ' . __( 'But something between WordPress and the visitor removed Vary: Accept from the answer, so a cache added later could mix the two up.', 'thatseoagent' );
                }
                break;
            case 'cloudflare_markdown':
                $headline = __( 'Agents get Cloudflare’s Markdown, not ThatSeoAgent’s', 'thatseoagent' );
                $detail   = __( 'Markdown for Agents is on in Cloudflare: it converts the HTML page and answers in place of WordPress. Agents get Markdown, but made from the theme’s page, and without what ThatSeoAgent’s adds: the author, the dates, the content type and the SEO title.', 'thatseoagent' );
                break;
            case 'html_to_agents':
                $headline = __( 'Agents that ask for Markdown get the HTML page', 'thatseoagent' );
                $detail   = '' !== $layer
                    ? __( 'A cache answered before WordPress could: agents get the page a browser caused to be stored. Nothing breaks for people.', 'thatseoagent' )
                    : __( 'Something answered with HTML before WordPress could, or negotiation is switched off with the thatseoagent_markdown_negotiation filter. Nothing breaks for people.', 'thatseoagent' );
                break;
            case 'markdown_to_browsers':
                $headline = __( 'A browser got the Markdown version', 'thatseoagent' );
                $detail   = __( 'A cache stored the answer given to an agent and handed it to the browser that came next. People may see plain text instead of the page until the cache is cleared.', 'thatseoagent' );
                break;
            case 'no_answer':
                $headline = __( 'The site did not answer the check', 'thatseoagent' );
                $detail   = $agent['error'] ? $agent['error'] : ( $browser['error'] ? $browser['error'] : __( 'The server did not respond to a request from itself. Some hosts block those.', 'thatseoagent' ) );
                break;
            default:
                $headline = __( 'Nothing to check yet', 'thatseoagent' );
                $detail   = __( 'No published post has a Markdown version.', 'thatseoagent' );
        }

        return array(
            'checked'  => time(),
            'url'      => $url,
            'verdict'  => $verdict,
            'headline' => $headline,
            'detail'   => $detail,
            'agent'    => self::describe( $agent ),
            'browser'  => self::describe( $browser ),
            'layer'    => $layer,
            'advice'   => in_array( $verdict, array( 'cloudflare_markdown', 'html_to_agents', 'markdown_to_browsers' ), true ) ? self::advice( 'cloudflare_markdown' === $verdict ? 'cloudflare_markdown' : $layer, $htaccess ) : '',
            'htaccess' => $htaccess,
        );
    }

    /**
     * One response, as the screen lists it.
     *
     * @since 2.3.0
     * @param array $response Request result.
     * @return array{status: int, type: string, markdown: bool, vary: bool}
     */
    private static function describe( array $response ) {
        return array(
            'status'   => isset( $response['status'] ) ? (int) $response['status'] : 0,
            'type'     => isset( $response['type'] ) ? $response['type'] : '',
            'markdown' => ! empty( $response['markdown'] ),
            'vary'     => ! empty( $response['vary'] ),
        );
    }

    /**
     * What to change in the layer that answered.
     *
     * @since 2.3.0
     * @since 2.6.0 'cloudflare_markdown': Cloudflare's conversion answered.
     * @param string $layer    See layer(), or 'cloudflare_markdown'.
     * @param array  $htaccess ThatSeoAgent_Markdown_Htaccess::status().
     * @return string
     */
    private static function advice( $layer, array $htaccess ) {
        $plugins = array_values( ThatSeoAgent_Markdown_Htaccess::cache_blocks() );

        if ( 'cloudflare_markdown' === $layer ) {
            return __( 'In Cloudflare, turn Markdown for Agents off under AI Crawl Control, or keep it for the rest of the zone and turn it off for this site with a Configuration Rule. Then run this check again.', 'thatseoagent' );
        }

        if ( 'cloudflare' === $layer ) {
            return __( 'Cloudflare stores the page by its URL. Settings → Caches and CDN gives the two ways to fix it in its Cache Rules, with links to Cloudflare’s documentation. Then run this check again: it tells you whether the change took.', 'thatseoagent' );
        }

        if ( in_array( $layer, $plugins, true ) || ( $htaccess['applies'] && in_array( $layer, array( '', 'cache' ), true ) ) ) {
            if ( ! $htaccess['applies'] ) {
                /* translators: %s: page cache plugin name. */
                return sprintf( __( '%s serves its stored pages before WordPress. Exclude requests whose Accept header contains text/markdown in its settings, if it offers that.', 'thatseoagent' ), $layer );
            }
            if ( ! $htaccess['present'] ) {
                return __( 'Add the .htaccess rule in Settings → Caches and CDN: it sends requests asking for Markdown to WordPress before any page cache can answer them.', 'thatseoagent' );
            }
            if ( '' !== $htaccess['below'] ) {
                /* translators: %s: page cache plugin name. */
                return sprintf( __( 'The .htaccess rule is below the rules of %s, which run first. Use “Write it again at the top” in Settings → Caches and CDN.', 'thatseoagent' ), $htaccess['below'] );
            }
        }

        if ( 'nginx' === $layer ) {
            return __( 'The server caches pages by URL. Add the Accept header to the cache key, for example with a map that sets a variable to "md" when Accept contains text/markdown and appending it to fastcgi_cache_key.', 'thatseoagent' );
        }

        if ( 'varnish' === $layer ) {
            return __( 'Varnish stores the page by URL. In vcl_recv, pass requests whose Accept header contains text/markdown, or add a normalized Accept to the hash.', 'thatseoagent' );
        }

        if ( 'litespeed' === $layer ) {
            return __( 'LiteSpeed stores the page by URL. Exclude requests whose Accept header contains text/markdown from its cache, or have it vary on that header.', 'thatseoagent' );
        }

        return __( 'Whatever stores the pages — the hosting, a CDN, a plugin — should not cache requests whose Accept header contains text/markdown, or should keep a separate copy for them.', 'thatseoagent' );
    }
}
