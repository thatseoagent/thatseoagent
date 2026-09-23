<?php
/**
 * Reads a robots.txt the way a crawler does (RFC 9309).
 *
 * A crawler obeys the group whose User-agent line names its product token,
 * case-insensitively; groups naming the same token are merged; with none,
 * it falls back to the `*` group. Within the group the longest matching
 * rule wins, and Allow wins a tie. `*` in a path matches any run of
 * characters and a trailing `$` anchors the end. An empty Disallow is no
 * rule at all.
 *
 * Pure: it takes the text and answers, so it can be checked without a site.
 *
 * @package ThatSeoAgent
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Robots_Parser {

    /**
     * Whether a crawler may fetch a path.
     *
     * @since 2.1.0
     * @param string $robots Contents of robots.txt.
     * @param string $token  The crawler's product token, e.g. 'GPTBot'.
     * @param string $path   Path to check, starting with '/'.
     * @return array{allowed: bool, group: string} `group` is the User-agent
     *         the answer came from: the token itself, '*', or '' when no
     *         group applies.
     */
    public static function check( $robots, $token, $path = '/' ) {
        $groups = self::groups( $robots );
        $token  = strtolower( $token );

        if ( isset( $groups[ $token ] ) ) {
            $group = $token;
        } elseif ( isset( $groups['*'] ) ) {
            $group = '*';
        } else {
            return array(
                'allowed' => true,
                'group'   => '',
            );
        }

        return array(
            'allowed' => self::allows( $groups[ $group ], $path ),
            'group'   => $group,
        );
    }

    /**
     * Rules by lowercased user agent.
     *
     * @since 2.1.0
     * @param string $robots Contents of robots.txt.
     * @return array<string, array<int, array{allow: bool, path: string}>>
     */
    private static function groups( $robots ) {
        $groups  = array();
        $agents  = array();
        $in_rules = false;

        foreach ( preg_split( '/\r\n|\r|\n/', (string) $robots ) as $line ) {
            $line = trim( preg_replace( '/#.*$/', '', $line ) );
            if ( ! preg_match( '/^([a-z-]+)\s*:\s*(.*)$/i', $line, $m ) ) {
                continue;
            }

            $field = strtolower( $m[1] );
            $value = trim( $m[2] );

            if ( 'user-agent' === $field ) {
                // A User-agent line after rules starts a new group; several
                // in a row share the rules that follow.
                if ( $in_rules ) {
                    $agents   = array();
                    $in_rules = false;
                }
                $agents[] = strtolower( $value );
                if ( ! isset( $groups[ strtolower( $value ) ] ) ) {
                    $groups[ strtolower( $value ) ] = array();
                }
                continue;
            }

            if ( 'allow' !== $field && 'disallow' !== $field ) {
                continue;
            }

            $in_rules = true;
            if ( '' === $value ) {
                continue;
            }

            foreach ( $agents as $agent ) {
                $groups[ $agent ][] = array(
                    'allow' => 'allow' === $field,
                    'path'  => $value,
                );
            }
        }

        return $groups;
    }

    /**
     * Whether a group's rules let a path through.
     *
     * @since 2.1.0
     * @param array  $rules Rules of the group.
     * @param string $path  Path.
     * @return bool
     */
    private static function allows( array $rules, $path ) {
        $best_length = -1;
        $allowed     = true;

        foreach ( $rules as $rule ) {
            if ( ! self::matches( $rule['path'], $path ) ) {
                continue;
            }

            $length = strlen( $rule['path'] );
            if ( $length > $best_length || ( $length === $best_length && $rule['allow'] ) ) {
                $best_length = $length;
                $allowed     = $rule['allow'];
            }
        }

        return $allowed;
    }

    /**
     * Whether a rule's path pattern matches a path.
     *
     * @since 2.1.0
     * @param string $pattern Rule path, with `*` and `$`.
     * @param string $path    Path.
     * @return bool
     */
    private static function matches( $pattern, $path ) {
        $anchored = '$' === substr( $pattern, -1 );
        if ( $anchored ) {
            $pattern = substr( $pattern, 0, -1 );
        }

        $regex = str_replace( '\*', '.*', preg_quote( $pattern, '#' ) );

        return (bool) preg_match( '#^' . $regex . ( $anchored ? '$' : '' ) . '#', $path );
    }
}
