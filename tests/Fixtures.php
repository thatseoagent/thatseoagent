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

    /** @var list<array{int, string}> Term ID and taxonomy. */
    private static array $terms = array();

    /** @var array<string, mixed> Option name => value before, or the marker for none. */
    private static array $options = array();

    private const ABSENT = "\0absent";

    public static function start(): void {
        self::$posts   = array();
        self::$terms   = array();
        self::$options = array();

        add_action( 'wp_insert_post', array( self::class, 'post_created' ), 10, 3 );
        // Attachments are created without wp_insert_post firing.
        add_action( 'add_attachment', array( self::class, 'attachment_created' ) );
        add_action( 'created_term', array( self::class, 'term_created' ), 10, 3 );
        add_action( 'add_option', array( self::class, 'option_added' ) );
        add_action( 'update_option', array( self::class, 'option_updated' ), 10, 2 );
    }

    public static function clean(): void {
        remove_action( 'wp_insert_post', array( self::class, 'post_created' ), 10 );
        remove_action( 'add_attachment', array( self::class, 'attachment_created' ) );
        remove_action( 'created_term', array( self::class, 'term_created' ), 10 );
        remove_action( 'add_option', array( self::class, 'option_added' ) );
        remove_action( 'update_option', array( self::class, 'option_updated' ), 10 );

        foreach ( array_reverse( self::$posts ) as $id ) {
            wp_delete_post( $id, true );
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
