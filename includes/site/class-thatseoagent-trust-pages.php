<?php
/**
 * The pages that say who runs the site: about, contact and privacy.
 *
 * Google's guidance on helpful content asks whether visitors can tell who
 * is behind a site and how to reach them; these are the pages where people
 * look, and where search engines and AI assistants find the same answer. It
 * is not a ranking factor, and the bulletin does not call it one.
 *
 * Found from inside the site, which is more reliable than guessing from a
 * page's links as an outside tool must: the privacy policy WordPress has
 * assigned, a published page whose slug names the page in any of the
 * languages That SEO Agent's MCP server reads (the same lists), or a menu
 * link to such an address — a contact form on another site counts.
 *
 * @package ThatSeoAgent
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Trust_Pages {

    /**
     * Slugs that name each page, in the languages the MCP reads.
     *
     * @since 2.3.0
     * @return array<string, array<int, string>>
     */
    public static function slugs() {
        $slugs = array(
            'about'   => array(
                'about', 'about-us', 'aboutus', 'about-me',
                'acerca', 'acerca-de', 'acerca-de-mi', 'acerca-de-nosotros',
                'quienes-somos', 'quienes', 'nosotros', 'sobre-nosotros', 'sobre-mi',
                'nuestra-empresa', 'nuestro-equipo', 'equipo', 'empresa',
                'company', 'our-story', 'our-team', 'our-company', 'who-we-are', 'meet-the-team', 'team',
                'a-propos', 'apropos', 'qui-sommes-nous', 'notre-equipe',
                'uber-uns', 'ueber-uns', 'impressum', 'unternehmen',
                'chi-siamo', 'azienda',
                'sobre', 'sobre-nos', 'quem-somos', 'equipe',
            ),
            'contact' => array(
                'contact', 'contact-us', 'contactus',
                'contacto', 'contactar', 'contactanos', 'contactenos',
                'contato', 'fale-conosco',
                'nous-contacter', 'contactez-nous',
                'kontakt', 'kontaktieren',
                'contatti', 'contattaci',
            ),
            'privacy' => array(
                'privacy', 'privacy-policy', 'privacypolicy',
                'privacidad', 'politica-de-privacidad', 'politica-privacidad', 'aviso-de-privacidad',
                'privacidade', 'politica-de-privacidade',
                'confidentialite', 'politique-de-confidentialite', 'vie-privee',
                'datenschutz', 'datenschutzerklarung',
                'informativa-privacy', 'privatlivspolitik',
            ),
        );

        /**
         * Filter the slugs that name the about, contact and privacy pages.
         *
         * @since 2.3.0
         * @param array<string, array<int, string>> $slugs Kind => slugs.
         */
        return (array) apply_filters( 'thatseoagent_trust_page_slugs', $slugs );
    }

    /**
     * Where each page is, or '' when it was not found.
     *
     * Memoised per request: the bulletin asks on every screen.
     *
     * @since 2.3.0
     * @return array{about: string, contact: string, privacy: string}
     */
    public static function found() {
        return ThatSeoAgent_Memo::remember(
            'trust_pages',
            'site',
            function () {
                return self::find();
            }
        );
    }

    /**
     * The lookup behind found(), without memoisation.
     *
     * @since 2.3.0
     * @return array{about: string, contact: string, privacy: string}
     */
    private static function find() {
        $slugs = self::slugs();
        $found = array(
            'about'   => '',
            'contact' => '',
            'privacy' => (string) get_privacy_policy_url(),
        );

        $menu_links = null;

        foreach ( $found as $kind => $url ) {
            if ( '' !== $url || empty( $slugs[ $kind ] ) ) {
                continue;
            }

            $pages = get_posts(
                array(
                    'post_type'      => 'page',
                    'post_status'    => 'publish',
                    'post_name__in'  => $slugs[ $kind ],
                    'posts_per_page' => 1,
                    'has_password'   => false,
                    'no_found_rows'  => true,
                    'fields'         => 'ids',
                )
            );

            if ( $pages ) {
                $found[ $kind ] = (string) get_permalink( $pages[0] );
                continue;
            }

            if ( null === $menu_links ) {
                $menu_links = self::menu_links();
            }

            $quoted  = array_map(
                function ( $slug ) {
                    return preg_quote( $slug, '#' );
                },
                $slugs[ $kind ]
            );
            $pattern = '#/(?:' . implode( '|', $quoted ) . ')(?:[/?\#.]|$)#i';
            foreach ( $menu_links as $link ) {
                if ( preg_match( $pattern, (string) wp_parse_url( $link, PHP_URL_PATH ) . '/' ) || ( 'contact' === $kind && 0 === stripos( $link, 'mailto:' ) ) ) {
                    $found[ $kind ] = $link;
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * Every link in the site's menus.
     *
     * @since 2.3.0
     * @return array<int, string>
     */
    private static function menu_links() {
        $links = array();

        foreach ( wp_get_nav_menus() as $menu ) {
            foreach ( (array) wp_get_nav_menu_items( $menu ) as $item ) {
                if ( ! empty( $item->url ) ) {
                    $links[] = (string) $item->url;
                }
            }
        }

        return array_values( array_unique( $links ) );
    }

    /**
     * The name of each page, as the bulletin writes it.
     *
     * @since 2.3.0
     * @return array{about: string, contact: string, privacy: string}
     */
    public static function labels() {
        return array(
            'about'   => __( 'About', 'thatseoagent' ),
            'contact' => __( 'Contact', 'thatseoagent' ),
            'privacy' => __( 'Privacy policy', 'thatseoagent' ),
        );
    }
}
