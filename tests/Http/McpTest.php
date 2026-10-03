<?php

/*
 * The MCP server over HTTP, as a client reaches it: an application
 * password, the protocol's headers and its JSON-RPC body.
 */

use ThatSeoAgent\Tests\Response;

/**
 * An application password for a new user with a role.
 *
 * @return array{string, string} Login and password.
 */
function mcpCredentials( string $role ): array {
    $user = user( $role );
    list( $password ) = WP_Application_Passwords::create_new_application_password( $user->ID, array( 'name' => 'tests' ) );

    return array( $user->user_login, $password );
}

/**
 * A 2026-07-28 request to the thatseoagent server.
 *
 * @param array{string, string}|null $credentials
 * @param array<string, mixed>       $params
 */
function mcp( ?array $credentials, string $method, array $params = array() ): Response {
    if ( ! class_exists( 'LeanMcp\Registry' ) ) {
        test()->markTestSkipped( 'Needs Lean MCP: see Tests and checks in the README.' );
    }

    $params['_meta'] = array(
        'io.modelcontextprotocol/protocolVersion'    => '2026-07-28',
        'io.modelcontextprotocol/clientCapabilities' => new stdClass(),
    );
    $headers = array(
        'Content-Type'         => 'application/json',
        'Accept'               => 'application/json',
        'MCP-Protocol-Version' => '2026-07-28',
        'Mcp-Method'           => $method,
    );
    if ( isset( $params['name'] ) ) {
        $headers['Mcp-Name'] = $params['name'];
    }
    if ( $credentials ) {
        $headers['Authorization'] = 'Basic ' . base64_encode( implode( ':', $credentials ) );
    }

    return Response::fetch(
        rest_url( 'lean-mcp/thatseoagent' ),
        $headers,
        'POST',
        (string) wp_json_encode( array( 'jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params ) )
    );
}

it( 'lists its tools to an editor', function () {
    $response = mcp( mcpCredentials( 'editor' ), 'tools/list' );
    $body     = json_decode( $response->body, true );

    expect( $response->status )->toBe( 200, $response->body )
        ->and( array_column( $body['result']['tools'], 'name' ) )->toHaveCount( 14 )
        ->and( $body['result']['resultType'] )->toBe( 'complete' );
} );

it( 'runs a tool for an editor', function () {
    $post     = post( array( 'post_title' => 'Over the wire' ) );
    $response = mcp( mcpCredentials( 'editor' ), 'tools/call', array( 'name' => 'thatseoagent-get-post-seo', 'arguments' => array( 'post_id' => $post->ID ) ) );
    $result   = json_decode( $response->body, true )['result'];

    expect( $response->status )->toBe( 200, $response->body )
        ->and( $result['structuredContent']['title'] )->toBe( 'Over the wire | thatseoagent' );
} );

it( 'turns away an author, and anyone without credentials', function () {
    expect( mcp( mcpCredentials( 'author' ), 'tools/list' )->status )->toBe( 403 )
        ->and( mcp( null, 'tools/list' )->status )->toBe( 401 );
} );
