<?php

namespace ThatSeoAgent\Tests;

/**
 * What an Http test writes to the site, undone after it.
 *
 * The site answering those tests reads the database through a connection
 * of its own, which cannot see a transaction that is never committed. So
 * the Http suite writes for real, and this keeps track: the posts and
 * terms created, and each option's value before the test first changed it.
 */
final class Fixtures {

    /** @var list<int> */
    private static array $posts = array();

    /** @var list<int> */
    private static array $users = array();

    /** @var list<array{int, string}> Term ID and taxonomy. */
    private static array $terms = array();

    /** @var array<string, mixed> Option name => value before, or the marker for none. */
    private static array $options = array();

    private const ABSENT = "\0absent";

    /** How long the debug log was when the test started. */
    private static int $log_offset = 0;

    public static function start(): void {
        self::$posts   = array();
        self::$users   = array();
        self::$terms   = array();
        self::$options = array();

        clearstatcache();
        self::$log_offset = is_string( WP_DEBUG_LOG ) && is_file( WP_DEBUG_LOG ) ? (int) filesize( WP_DEBUG_LOG ) : 0;

        add_action( 'wp_insert_post', array( self::class, 'post_created' ), 10, 3 );
        // Attachments are created without wp_insert_post firing.
        add_action( 'add_attachment', array( self::class, 'attachment_created' ) );
        add_action( 'created_term', array( self::class, 'term_created' ), 10, 3 );
        add_action( 'user_register', array( self::class, 'user_created' ) );
        add_action( 'add_option', array( self::class, 'option_added' ) );
        add_action( 'update_option', array( self::class, 'option_updated' ), 10, 2 );
    }

    /**
     * What PHP logged while the test ran, in this process or the others:
     * the web server's answers and WP-CLI's commands log there too.
     *
     * @return list<string>
     */
    public static function logged(): array {
        clearstatcache();
        if ( ! is_string( WP_DEBUG_LOG ) || ! is_file( WP_DEBUG_LOG ) || filesize( WP_DEBUG_LOG ) <= self::$log_offset ) {
            return array();
        }

        $lines = explode( "\n", trim( (string) file_get_contents( WP_DEBUG_LOG, false, null, self::$log_offset ) ) );

        return array_values( array_filter( $lines, static fn ( string $line ): bool => (bool) preg_match( '/PHP (Fatal|Parse|Warning|Notice|Deprecated)|WordPress database error/', $line ) ) );
    }

    public static function clean(): void {
        remove_action( 'wp_insert_post', array( self::class, 'post_created' ), 10 );
        remove_action( 'add_attachment', array( self::class, 'attachment_created' ) );
        remove_action( 'created_term', array( self::class, 'term_created' ), 10 );
        remove_action( 'user_register', array( self::class, 'user_created' ) );
        remove_action( 'add_option', array( self::class, 'option_added' ) );
        remove_action( 'update_option', array( self::class, 'option_updated' ), 10 );

        foreach ( array_reverse( self::$posts ) as $id ) {
            wp_delete_post( $id, true );
        }
        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ( self::$users as $id ) {
            wp_delete_user( $id );
        }
        foreach ( self::$terms as list( $id, $taxonomy ) ) {
            wp_delete_term( $id, $taxonomy );
        }
        foreach ( self::$options as $name => $value ) {
            if ( self::ABSENT === $value ) {
                delete_option( $name );
            } else {
                update_option( $name, $value );
            }
        }

        // The caches the site's web server filled while answering.
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%thatseoagent\\_%'" );

        wp_cache_flush();
        \ThatSeoAgent_Memo::reset();
    }

    public static function post_created( int $id, \WP_Post $post, bool $update ): void {
        if ( ! $update && 'revision' !== $post->post_type ) {
            self::$posts[] = $id;
        }
    }

    public static function attachment_created( int $id ): void {
        self::$posts[] = $id;
    }

    public static function user_created( int $id ): void {
        self::$users[] = $id;
    }

    public static function term_created( int $id, int $tt_id, string $taxonomy ): void {
        self::$terms[] = array( $id, $taxonomy );
    }

    public static function option_added( string $name ): void {
        if ( ! array_key_exists( $name, self::$options ) ) {
            self::$options[ $name ] = self::ABSENT;
        }
    }

    public static function option_updated( string $name, mixed $old ): void {
        if ( ! array_key_exists( $name, self::$options ) ) {
            self::$options[ $name ] = $old;
        }
    }
}
