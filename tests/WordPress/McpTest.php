<?php

/*
 * The plugin's MCP server, through Lean MCP: which tools it offers, and how
 * a call reaches the Abilities.
 */

use LeanMcp\Registry;
use LeanMcp\Server;

/**
 * The thatseoagent server, or a skipped test without Lean MCP.
 */
function mcpServer(): Server {
    if ( ! class_exists( Registry::class ) ) {
        test()->markTestSkipped( 'Needs Lean MCP: see Tests and checks in the README.' );
    }

    $server = Registry::instance()->get( ThatSeoAgent_MCP::SERVER_ID );
    expect( $server )->toBeInstanceOf( Server::class );

    return $server;
}

/**
 * A tools/call, as the server answers it.
 *
 * @param array<string, mixed> $arguments
 * @return array<string, mixed>
 */
function callTool( string $name, array $arguments = array() ): array {
    $result = mcpServer()->tools()->call( array( 'name' => $name, 'arguments' => $arguments ) );
    expect( $result )->toBeArray();

    return $result;
}

afterEach( function () {
    if ( function_exists( 'wp_set_current_user' ) ) {
        wp_set_current_user( 0 );
    }
} );

it( 'offers the fourteen Abilities as tools, by their MCP names', function () {
    $tools = array_map( fn ( $tool ) => $tool->name(), mcpServer()->tools()->all() );

    expect( $tools )->toBe( array_map( fn ( $ability ) => str_replace( '/', '-', $ability ), ThatSeoAgent_MCP::TOOLS ) )
        ->and( mcpServer()->tools()->collisions() )->toBe( array() );
} );

it( 'is for editors and administrators, at its own address', function () {
    $server = mcpServer();

    expect( $server->capability )->toBe( 'edit_others_posts' )
        ->and( $server->url() )->toBe( rest_url( 'lean-mcp/thatseoagent' ) );
} );

it( 'describes each tool with an object schema, a title and its hints', function () {
    foreach ( mcpServer()->tools()->list( array() ) as $definition ) {
        expect( $definition['inputSchema']['type'] )->toBe( 'object', $definition['name'] )
            ->and( $definition['title'] )->not->toBe( '' )
            ->and( $definition['description'] )->not->toBe( '' )
            ->and( $definition['annotations'] )->toHaveKey( 'readOnlyHint' );

        if ( isset( $definition['outputSchema'] ) ) {
            expect( $definition['outputSchema']['type'] )->toBe( 'object', $definition['name'] );
        }
    }
} );

it( 'runs a tool and answers its result as structured content', function () {
    actingAs( 'administrator' );
    $post = post( array( 'post_title' => 'Through MCP' ) );

    $result = callTool( 'thatseoagent-get-post-seo', array( 'post_id' => $post->ID ) );

    expect( $result )->not->toHaveKey( 'isError' )
        ->and( (array) $result['structuredContent'] )->toMatchArray( array( 'post_id' => $post->ID, 'title' => 'Through MCP | thatseoagent' ) )
        ->and( json_decode( $result['content'][0]['text'], true )['post_id'] )->toBe( $post->ID );
} );

it( 'wraps a list result, as its output schema says', function () {
    actingAs( 'administrator' );

    $result = callTool( 'thatseoagent-get-sitemap-urls' );

    expect( (array) $result['structuredContent'] )->toHaveKey( 'result' )
        ->and( ( (array) $result['structuredContent'] )['result'][0] )->toBe( home_url( '/sitemap.xml' ) );
} );

it( 'answers an Ability’s error as a result the model can read', function () {
    actingAs( 'administrator' );

    $result = callTool( 'thatseoagent-get-post-seo', array( 'post_id' => 999999 ) );

    expect( $result['isError'] )->toBeTrue()
        ->and( $result['content'][0]['text'] )->toBe( 'No post exists with that ID.' );
} );

it( 'explains arguments the tool does not take', function () {
    actingAs( 'administrator' );

    $result = callTool( 'thatseoagent-update-post-seo', array( 'post_id' => post()->ID, 'slug' => 'x' ) );

    expect( $result['isError'] )->toBeTrue()
        ->and( $result['content'][0]['text'] )->toContain( 'slug' );
} );

it( 'writes through a tool', function () {
    actingAs( 'administrator' );
    $post = post();

    callTool( 'thatseoagent-update-post-seo', array( 'post_id' => $post->ID, 'description' => 'Written by an agent.' ) );

    expect( ThatSeoAgent_Post_Seo::get( $post, 'description' ) )->toBe( 'Written by an agent.' );
} );
