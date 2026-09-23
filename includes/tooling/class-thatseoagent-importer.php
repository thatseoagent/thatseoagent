<?php
/**
 * Import SEO data from another SEO plugin.
 *
 * Reads what Yoast SEO, Rank Math or All in One SEO stored — per-post titles
 * and descriptions, and the site's identity — and writes it into ThatSeoAgent's
 * own fields. Read-only towards the source: nothing of the other plugin's data
 * is changed or deleted, so switching back loses nothing.
 *
 * The other plugins store templates, not text: "%%title%% %%sep%% %%sitename%%".
 * Copying a template verbatim would print the placeholders on the page, so
 * each one is resolved against the post. A template still holding a variable
 * that cannot be resolved here (a focus keyword, a custom field) is skipped
 * rather than imported half-resolved.
 *
 * A title that resolves to what ThatSeoAgent would output anyway — the post title,
 * or post title plus site name — is not a custom title and is not imported:
 * saving it would freeze today's site name into every post.
 *
 * @package ThatSeoAgent
 * @since 1.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Importer {

    /**
     * Supported sources and where they keep per-post fields.
     *
     * @since 1.16.0
     * @return array<string, array{label: string, title: string, description: string}>
     */
    public static function sources() {
        return array(
            'yoast'    => array(
                'label'       => 'Yoast SEO',
                'title'       => '_yoast_wpseo_title',
                'description' => '_yoast_wpseo_metadesc',
            ),
            'rankmath' => array(
                'label'       => 'Rank Math',
                'title'       => 'rank_math_title',
                'description' => 'rank_math_description',
            ),
            'aioseo'   => array(
                'label'       => 'All in One SEO',
                'title'       => '_aioseo_title',
                'description' => '_aioseo_description',
            ),
        );
    }

    /**
     * IDs of posts holding a title or description from a source.
     *
     * AIOSEO 4 moved its per-post data to its own table and only older
     * versions left it in post meta, so both are read.
     *
     * @since 1.16.0
     * @param string             $source     Source key.
     * @param array<int, string> $post_types Post types to include.
     * @return array<int, int>
     */
    public static function post_ids( $source, array $post_types ) {
        global $wpdb;

        $fields = self::sources()[ $source ];
        $types  = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // A one-off migration run from WP-CLI; the placeholders for the post
        // type list are built above, one per type.
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                 WHERE pm.meta_key IN (%s, %s) AND pm.meta_value <> ''
                   AND p.post_type IN ($types)
                   AND p.post_status NOT IN ('auto-draft', 'trash', 'inherit')",
                array_merge( array( $fields['title'], $fields['description'] ), $post_types )
            )
        );

        if ( 'aioseo' === $source && self::aioseo_table() ) {
            $table = self::aioseo_table();
            $ids   = array_merge(
                $ids,
                $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
                         INNER JOIN {$table} a ON a.post_id = p.ID
                         WHERE ( a.title <> '' OR a.description <> '' )
                           AND p.post_type IN ($types)
                           AND p.post_status NOT IN ('auto-draft', 'trash', 'inherit')",
                        $post_types
                    )
                )
            );
        }
        // phpcs:enable

        $ids = array_map( 'intval', array_unique( $ids ) );
        sort( $ids );

        return $ids;
    }

    /**
     * Import one post.
     *
     * @since 1.16.0
     * @param string $source    Source key.
     * @param int    $post_id   Post ID.
     * @param bool   $overwrite Replace values ThatSeoAgent already has.
     * @param bool   $dry_run   Report without writing.
     * @return array<string, array{status: string, value: string}> Per field:
     *         'imported', 'exists', 'default', 'unresolved' or 'empty'.
     */
    public static function import_post( $source, $post_id, $overwrite = false, $dry_run = false ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return array();
        }

        $raw     = self::raw_values( $source, $post );
        $results = array();
        $values  = array();

        foreach ( array( 'title', 'description' ) as $field ) {
            $template = trim( (string) $raw[ $field ] );

            if ( '' === $template ) {
                $results[ $field ] = array( 'status' => 'empty', 'value' => '' );
                continue;
            }

            $resolved = self::resolve( $source, $template, $post );

            if ( null === $resolved ) {
                $results[ $field ] = array( 'status' => 'unresolved', 'value' => $template );
                continue;
            }

            if ( 'title' === $field && self::is_default_title( $resolved, $post ) ) {
                $results[ $field ] = array( 'status' => 'default', 'value' => $resolved );
                continue;
            }

            if ( '' === $resolved ) {
                $results[ $field ] = array( 'status' => 'empty', 'value' => '' );
                continue;
            }

            if ( ! $overwrite && '' !== ThatSeoAgent_Post_Seo::get( $post, $field ) ) {
                $results[ $field ] = array( 'status' => 'exists', 'value' => $resolved );
                continue;
            }

            $values[ $field ]  = $resolved;
            $results[ $field ] = array( 'status' => 'imported', 'value' => $resolved );
        }

        if ( $values && ! $dry_run ) {
            ThatSeoAgent_Post_Seo::save( $post, $values );
        }

        return $results;
    }

    /**
     * The stored title and description templates of a post.
     *
     * @since 1.16.0
     * @param string  $source Source key.
     * @param WP_Post $post   Post.
     * @return array{title: string, description: string}
     */
    private static function raw_values( $source, WP_Post $post ) {
        $fields = self::sources()[ $source ];
        $values = array(
            'title'       => (string) get_post_meta( $post->ID, $fields['title'], true ),
            'description' => (string) get_post_meta( $post->ID, $fields['description'], true ),
        );

        if ( 'aioseo' === $source && self::aioseo_table() ) {
            global $wpdb;
            $table = self::aioseo_table();

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $row = $wpdb->get_row(
                $wpdb->prepare( "SELECT title, description FROM {$table} WHERE post_id = %d", $post->ID ),
                ARRAY_A
            );
            // phpcs:enable

            if ( $row ) {
                foreach ( array( 'title', 'description' ) as $field ) {
                    if ( ! empty( $row[ $field ] ) ) {
                        $values[ $field ] = (string) $row[ $field ];
                    }
                }
            }
        }

        return $values;
    }

    /**
     * AIOSEO's per-post table, when it exists.
     *
     * @since 1.16.0
     * @return string Table name, or ''.
     */
    private static function aioseo_table() {
        global $wpdb;

        static $table = null;

        if ( null === $table ) {
            $candidate = $wpdb->prefix . 'aioseo_posts';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $table = $candidate === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $candidate ) ) ? $candidate : '';
        }

        return $table;
    }

    /**
     * Resolve a template's variables against a post.
     *
     * @since 1.16.0
     * @param string  $source   Source key.
     * @param string  $template Stored template.
     * @param WP_Post $post     Post.
     * @return string|null Plain text, or null when a variable is unknown.
     */
    public static function resolve( $source, $template, WP_Post $post ) {
        $values = self::variable_values( $source, $post );

        if ( 'aioseo' === $source ) {
            // AIOSEO tags: #post_title, #separator_sa, …
            $pattern = '/#([a-z_]+)/';
        } elseif ( 'rankmath' === $source ) {
            $pattern = '/%([a-z_]+)%/';
        } else {
            $pattern = '/%%([a-z_]+)%%/';
        }

        $unknown  = false;
        $resolved = preg_replace_callback(
            $pattern,
            function ( $match ) use ( $values, &$unknown ) {
                if ( array_key_exists( $match[1], $values ) ) {
                    return $values[ $match[1] ];
                }

                $unknown = true;
                return $match[0];
            },
            $template
        );

        if ( $unknown ) {
            return null;
        }

        $resolved = html_entity_decode( wp_strip_all_tags( (string) $resolved ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        // Empty variables ("%%page%%" on page one) leave doubled spaces and
        // dangling separators behind.
        $separator = preg_quote( $values['sep'], '/' );
        $resolved  = preg_replace( '/\s+/u', ' ', $resolved );
        $resolved  = preg_replace( '/(' . $separator . '\s*){2,}/u', $values['sep'] . ' ', $resolved );
        $resolved  = preg_replace( '/^\s*' . $separator . '\s*|\s*' . $separator . '\s*$/u', '', $resolved );

        return trim( $resolved );
    }

    /**
     * Values of the variables each source supports, for one post.
     *
     * Only variables with a well-defined meaning on a single post. Anything
     * else leaves the template unresolved.
     *
     * @since 1.16.0
     * @param string  $source Source key.
     * @param WP_Post $post   Post.
     * @return array<string, string>
     */
    private static function variable_values( $source, WP_Post $post ) {
        $title      = html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $site_name  = get_bloginfo( 'name' );
        $tagline    = get_bloginfo( 'description' );
        $separator  = self::separator( $source );
        $excerpt    = ThatSeoAgent_Description::generate( $post );
        $categories = get_the_category( $post->ID );
        $category   = $categories ? $categories[0]->name : '';
        $author     = get_the_author_meta( 'display_name', (int) $post->post_author );
        $date       = get_the_date( '', $post );
        $modified   = get_the_modified_date( '', $post );
        $type       = get_post_type_object( $post->post_type );
        $singular   = $type ? $type->labels->singular_name : '';
        $plural     = $type ? $type->labels->name : '';
        $year       = wp_date( 'Y' );

        if ( 'aioseo' === $source ) {
            return array(
                'post_title'     => $title,
                'site_title'     => $site_name,
                'tagline'        => $tagline,
                'separator_sa'   => $separator,
                'post_excerpt'   => $excerpt,
                'post_date'      => $date,
                'author_name'    => $author,
                'categories'     => $category,
                'current_year'   => $year,
                'sep'            => $separator,
            );
        }

        $values = array(
            'title'            => $title,
            'sitename'         => $site_name,
            'sitedesc'         => $tagline,
            'sep'              => $separator,
            'excerpt'          => $excerpt,
            'excerpt_only'     => $excerpt,
            'category'         => $category,
            'primary_category' => $category,
            'date'             => $date,
            'modified'         => $modified,
            'name'             => $author,
            'currentyear'      => $year,
            'pt_single'        => $singular,
            'pt_plural'        => $plural,
            'page'             => '',
            'pagenumber'       => '',
            'pagetotal'        => '',
        );

        if ( 'rankmath' === $source ) {
            $values['org_name'] = $site_name;
        }

        return $values;
    }

    /**
     * The title separator the source was configured with.
     *
     * @since 1.16.0
     * @param string $source Source key.
     * @return string
     */
    private static function separator( $source ) {
        $separator = '';

        if ( 'yoast' === $source ) {
            $titles = get_option( 'wpseo_titles', array() );
            $map    = array(
                'sc-dash'   => '-',
                'sc-ndash'  => '–',
                'sc-mdash'  => '—',
                'sc-colon'  => ':',
                'sc-middot' => '·',
                'sc-bull'   => '•',
                'sc-star'   => '*',
                'sc-smstar' => '⋆',
                'sc-pipe'   => '|',
                'sc-tilde'  => '~',
                'sc-laquo'  => '«',
                'sc-raquo'  => '»',
                'sc-lt'     => '<',
                'sc-gt'     => '>',
            );
            $key       = isset( $titles['separator'] ) ? $titles['separator'] : 'sc-dash';
            $separator = isset( $map[ $key ] ) ? $map[ $key ] : '-';
        } elseif ( 'rankmath' === $source ) {
            $titles    = get_option( 'rank-math-options-titles', array() );
            $separator = isset( $titles['title_separator'] ) ? (string) $titles['title_separator'] : '-';
        } elseif ( 'aioseo' === $source ) {
            $options   = json_decode( (string) get_option( 'aioseo_options', '' ), true );
            $separator = isset( $options['searchAppearance']['global']['separator'] ) ? (string) $options['searchAppearance']['global']['separator'] : '-';
        }

        $separator = trim( html_entity_decode( $separator, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

        /** This filter is documented in includes/class-thatseoagent.php */
        return '' !== $separator ? $separator : (string) apply_filters( 'thatseoagent_title_separator', '|' );
    }

    /**
     * Whether a title is what the post gets without a custom one.
     *
     * @since 1.16.0
     * @param string  $title Resolved title.
     * @param WP_Post $post  Post.
     * @return bool
     */
    private static function is_default_title( $title, WP_Post $post ) {
        $normalize = function ( $text ) {
            return mb_strtolower( trim( preg_replace( '/[\s\p{P}\p{S}]+/u', ' ', $text ) ) );
        };

        $post_title = html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $candidates = array(
            $post_title,
            $post_title . ' ' . get_bloginfo( 'name' ),
        );

        foreach ( $candidates as $candidate ) {
            if ( $normalize( $candidate ) === $normalize( $title ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * The site identity a source was configured with.
     *
     * @since 1.16.0
     * @param string $source Source key.
     * @return array Identity settings in ThatSeoAgent_Identity's shape; only the
     *               keys the source actually had.
     */
    public static function identity( $source ) {
        $identity = array();
        $profiles = array();

        if ( 'yoast' === $source ) {
            $titles = (array) get_option( 'wpseo_titles', array() );
            $social = (array) get_option( 'wpseo_social', array() );

            if ( ! empty( $titles['company_or_person'] ) ) {
                $identity['type'] = 'person' === $titles['company_or_person'] ? 'person' : 'organization';
            }

            if ( isset( $identity['type'] ) && 'person' === $identity['type'] && ! empty( $titles['company_or_person_user_id'] ) ) {
                $identity['name'] = (string) get_the_author_meta( 'display_name', (int) $titles['company_or_person_user_id'] );
            } elseif ( ! empty( $titles['company_name'] ) ) {
                $identity['name'] = (string) $titles['company_name'];
            }

            if ( ! empty( $titles['company_logo_id'] ) ) {
                $identity['logo_id'] = (int) $titles['company_logo_id'];
            }

            if ( ! empty( $social['twitter_site'] ) ) {
                $identity['twitter_handle'] = (string) $social['twitter_site'];
            }

            $profiles = array_merge(
                array( isset( $social['facebook_site'] ) ? $social['facebook_site'] : '' ),
                isset( $social['other_social_urls'] ) ? (array) $social['other_social_urls'] : array()
            );
        } elseif ( 'rankmath' === $source ) {
            $titles = (array) get_option( 'rank-math-options-titles', array() );

            if ( ! empty( $titles['knowledgegraph_type'] ) ) {
                $identity['type'] = 'person' === $titles['knowledgegraph_type'] ? 'person' : 'organization';
            }
            if ( ! empty( $titles['knowledgegraph_name'] ) ) {
                $identity['name'] = (string) $titles['knowledgegraph_name'];
            }
            if ( ! empty( $titles['knowledgegraph_logo_id'] ) ) {
                $identity['logo_id'] = (int) $titles['knowledgegraph_logo_id'];
            }
            if ( ! empty( $titles['twitter_author_names'] ) ) {
                $identity['twitter_handle'] = (string) $titles['twitter_author_names'];
            }

            $profiles = array_merge(
                array( isset( $titles['social_url_facebook'] ) ? $titles['social_url_facebook'] : '' ),
                preg_split( '/\s+/', isset( $titles['social_additional_profiles'] ) ? (string) $titles['social_additional_profiles'] : '' )
            );
        } elseif ( 'aioseo' === $source ) {
            $options = json_decode( (string) get_option( 'aioseo_options', '' ), true );
            $graph   = isset( $options['searchAppearance']['global']['schema'] ) ? $options['searchAppearance']['global']['schema'] : array();
            $urls    = isset( $options['social']['profiles']['urls'] ) ? (array) $options['social']['profiles']['urls'] : array();

            if ( ! empty( $graph['siteRepresents'] ) ) {
                $identity['type'] = 'person' === $graph['siteRepresents'] ? 'person' : 'organization';
            }
            if ( ! empty( $graph['organizationName'] ) ) {
                $identity['name'] = (string) $graph['organizationName'];
            }

            $profiles = array_values( $urls );
        }

        $social = self::social_by_network( $profiles );
        if ( $social ) {
            $identity['social'] = $social;
        }

        return $identity;
    }

    /**
     * Sort profile URLs into ThatSeoAgent's social networks by host.
     *
     * URLs on networks ThatSeoAgent has no field for are dropped.
     *
     * @since 1.16.0
     * @param array<int, string> $urls Profile URLs.
     * @return array<string, string>
     */
    private static function social_by_network( array $urls ) {
        $hosts = array(
            'twitter'   => '/(^|\.)(twitter|x)\.com$/',
            'facebook'  => '/(^|\.)facebook\.com$/',
            'linkedin'  => '/(^|\.)linkedin\.com$/',
            'instagram' => '/(^|\.)instagram\.com$/',
            'youtube'   => '/(^|\.)(youtube\.com|youtu\.be)$/',
            'github'    => '/(^|\.)github\.com$/',
        );

        $social = array();

        foreach ( $urls as $url ) {
            $url  = esc_url_raw( trim( (string) $url ) );
            $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

            if ( '' === $host ) {
                continue;
            }

            foreach ( $hosts as $network => $pattern ) {
                if ( ! isset( $social[ $network ] ) && preg_match( $pattern, $host ) ) {
                    $social[ $network ] = $url;
                    continue 2;
                }
            }

            // Mastodon runs on many hosts; its profile paths start with "@".
            if ( ! isset( $social['mastodon'] ) && 0 === strpos( (string) wp_parse_url( $url, PHP_URL_PATH ), '/@' ) ) {
                $social['mastodon'] = $url;
            }
        }

        return $social;
    }

    /**
     * Merge imported identity into ThatSeoAgent's settings.
     *
     * @since 1.16.0
     * @param array $imported  Output of identity().
     * @param bool  $overwrite Replace values already set.
     * @param bool  $dry_run   Report without writing.
     * @return array<int, string> Keys that were (or would be) written.
     */
    public static function apply_identity( array $imported, $overwrite = false, $dry_run = false ) {
        $current = ThatSeoAgent_Identity::is_configured() ? ThatSeoAgent_Identity::get_settings() : array();
        $merged  = ThatSeoAgent_Identity::get_settings();
        $written = array();

        // An unconfigured site's stored default type is 'person' but it is an
        // Organization; importing only a name must not change that.
        $merged['type'] = ThatSeoAgent_Identity::primary_entity();

        foreach ( $imported as $key => $value ) {
            if ( 'social' === $key ) {
                foreach ( $value as $network => $url ) {
                    if ( $overwrite || empty( $current['social'][ $network ] ) ) {
                        $merged['social'][ $network ] = $url;
                        $written[]                    = 'social.' . $network;
                    }
                }
                continue;
            }

            if ( $overwrite || empty( $current[ $key ] ) ) {
                $merged[ $key ] = $value;
                $written[]      = $key;
            }
        }

        if ( $written && ! $dry_run ) {
            update_option( ThatSeoAgent_Identity::OPTION_KEY, ThatSeoAgent_Identity::sanitize( $merged ) );
        }

        return $written;
    }
}
