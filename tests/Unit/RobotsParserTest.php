<?php

/*
 * ThatSeoAgent_Robots_Parser reads a robots.txt the way a crawler does
 * (RFC 9309): which group a crawler obeys, and which rule of it decides.
 */

/**
 * The parser's answer for one crawler and one path.
 *
 * @return array{allowed: bool, group: string}
 */
function robots( string $robots, string $token = 'GPTBot', string $path = '/' ): array {
    return ThatSeoAgent_Robots_Parser::check( $robots, $token, $path );
}

describe( 'the group a crawler obeys', function () {
    it( 'is the one that names its token', function () {
        $robots = "User-agent: *\nDisallow: /\n\nUser-agent: GPTBot\nAllow: /";

        expect( robots( $robots ) )->toBe( array( 'allowed' => true, 'group' => 'gptbot' ) );
    } );

    it( 'matches the token case-insensitively', function () {
        $robots = "user-agent: gptbot\nDisallow: /";

        expect( robots( $robots, 'GPTBot' ) )->toBe( array( 'allowed' => false, 'group' => 'gptbot' ) );
    } );

    it( 'falls back to the * group when none names it', function () {
        $robots = "User-agent: ClaudeBot\nAllow: /\n\nUser-agent: *\nDisallow: /";

        expect( robots( $robots ) )->toBe( array( 'allowed' => false, 'group' => '*' ) );
    } );

    it( 'is none when no group applies, and then everything is allowed', function () {
        $robots = "User-agent: ClaudeBot\nDisallow: /";

        expect( robots( $robots ) )->toBe( array( 'allowed' => true, 'group' => '' ) );
    } );

    it( 'allows everything in an empty robots.txt', function ( string $robots ) {
        expect( robots( $robots ) )->toBe( array( 'allowed' => true, 'group' => '' ) );
    } )->with( array(
        'empty'         => '',
        'only comments' => "# Nothing to see\n# here",
        'only blanks'   => "\n\n  \n",
    ) );

    it( 'merges the groups that name the same token', function () {
        $robots = "User-agent: GPTBot\nDisallow: /private\n\nUser-agent: *\nDisallow: /\n\nUser-agent: GPTBot\nDisallow: /drafts";

        expect( robots( $robots, 'GPTBot', '/private/a' )['allowed'] )->toBeFalse()
            ->and( robots( $robots, 'GPTBot', '/drafts/b' )['allowed'] )->toBeFalse()
            ->and( robots( $robots, 'GPTBot', '/blog' ) )->toBe( array( 'allowed' => true, 'group' => 'gptbot' ) );
    } );

    it( 'shares the rules among User-agent lines in a row', function () {
        $robots = "User-agent: GPTBot\nUser-agent: ClaudeBot\nDisallow: /";

        expect( robots( $robots, 'GPTBot' )['allowed'] )->toBeFalse()
            ->and( robots( $robots, 'ClaudeBot' )['allowed'] )->toBeFalse();
    } );

    it( 'starts a new group at a User-agent line after rules', function () {
        $robots = "User-agent: GPTBot\nDisallow: /\nUser-agent: ClaudeBot\nAllow: /";

        expect( robots( $robots, 'GPTBot' )['allowed'] )->toBeFalse()
            ->and( robots( $robots, 'ClaudeBot' )['allowed'] )->toBeTrue();
    } );

    it( 'obeys a group that names it even with no rules, rather than *', function () {
        $robots = "User-agent: *\nDisallow: /\n\nUser-agent: GPTBot";

        expect( robots( $robots ) )->toBe( array( 'allowed' => true, 'group' => 'gptbot' ) );
    } );

    it( 'does not end a group at a line that is not a rule', function () {
        $robots = "User-agent: GPTBot\nSitemap: https://example.com/sitemap.xml\nUser-agent: ClaudeBot\nCrawl-delay: 5\nDisallow: /";

        expect( robots( $robots, 'GPTBot' )['allowed'] )->toBeFalse()
            ->and( robots( $robots, 'ClaudeBot' )['allowed'] )->toBeFalse();
    } );

    it( 'ignores rules before any User-agent line', function () {
        $robots = "Disallow: /\n\nUser-agent: *\nAllow: /";

        expect( robots( $robots )['allowed'] )->toBeTrue();
    } );
} );

describe( 'the rule that decides', function () {
    it( 'is the longest one that matches', function ( string $path, bool $allowed ) {
        $robots = "User-agent: *\nDisallow: /shop\nAllow: /shop/catalog\nDisallow: /shop/catalog/private";

        expect( robots( $robots, 'GPTBot', $path )['allowed'] )->toBe( $allowed );
    } )->with( array(
        'outside every rule'  => array( '/blog', true ),
        'under the short one' => array( '/shop/cart', false ),
        'under a longer one'  => array( '/shop/catalog/shirts', true ),
        'under the longest'   => array( '/shop/catalog/private/1', false ),
    ) );

    it( 'is Allow when an Allow and a Disallow are as long', function () {
        $robots = "User-agent: *\nDisallow: /page\nAllow: /page";

        expect( robots( $robots, 'GPTBot', '/page' )['allowed'] )->toBeTrue();
    } );

    it( 'does not depend on the order of the rules', function () {
        $first = "User-agent: *\nAllow: /shop/catalog\nDisallow: /shop";
        $last  = "User-agent: *\nDisallow: /shop\nAllow: /shop/catalog";

        expect( robots( $first, 'GPTBot', '/shop/catalog/a' ) )->toBe( robots( $last, 'GPTBot', '/shop/catalog/a' ) )
            ->and( robots( $first, 'GPTBot', '/shop/catalog/a' )['allowed'] )->toBeTrue();
    } );

    it( 'treats an empty Disallow as no rule at all', function () {
        $robots = "User-agent: *\nDisallow:";

        expect( robots( $robots, 'GPTBot', '/anything' ) )->toBe( array( 'allowed' => true, 'group' => '*' ) );
    } );

    it( 'disallows everything with Disallow: /', function ( string $path ) {
        expect( robots( "User-agent: *\nDisallow: /", 'GPTBot', $path )['allowed'] )->toBeFalse();
    } )->with( array( '/', '/blog', '/a/b/c?x=1' ) );
} );

describe( 'a rule path', function () {
    it( 'matches by prefix', function ( string $path, bool $matches ) {
        expect( robots( "User-agent: *\nDisallow: /fish", 'GPTBot', $path )['allowed'] )->toBe( ! $matches );
    } )->with( array(
        array( '/fish', true ),
        array( '/fish.html', true ),
        array( '/fish/salmon', true ),
        array( '/fishheads', true ),
        array( '/Fish', false ),
        array( '/catfish', false ),
        array( '/', false ),
    ) );

    it( 'matches any run of characters at *', function ( string $path, bool $matches ) {
        expect( robots( "User-agent: *\nDisallow: /*.php", 'GPTBot', $path )['allowed'] )->toBe( ! $matches );
    } )->with( array(
        array( '/index.php', true ),
        array( '/folder/filename.php', true ),
        array( '/folder/filename.php?parameters', true ),
        array( '/filename.php/', true ),
        array( '/', false ),
        array( '/windows.PHP', false ),
    ) );

    it( 'anchors the end at a trailing $', function ( string $path, bool $matches ) {
        expect( robots( "User-agent: *\nDisallow: /*.php$", 'GPTBot', $path )['allowed'] )->toBe( ! $matches );
    } )->with( array(
        array( '/filename.php', true ),
        array( '/folder/filename.php', true ),
        array( '/filename.php?parameters', false ),
        array( '/filename.php/', false ),
        array( '/filename.php5', false ),
    ) );

    it( 'takes every other character literally', function () {
        $robots = "User-agent: *\nDisallow: /a.b(c)+[d]?";

        expect( robots( $robots, 'GPTBot', '/a.b(c)+[d]?' )['allowed'] )->toBeFalse()
            ->and( robots( $robots, 'GPTBot', '/aXb(c)+[d]?' )['allowed'] )->toBeTrue();
    } );
} );

describe( 'the file', function () {
    it( 'is read whatever its line endings', function ( string $newline ) {
        $robots = implode( $newline, array( 'User-agent: *', 'Disallow: /private', 'Allow: /' ) );

        expect( robots( $robots, 'GPTBot', '/private' )['allowed'] )->toBeFalse();
    } )->with( array(
        'LF'   => "\n",
        'CRLF' => "\r\n",
        'CR'   => "\r",
    ) );

    it( 'ignores comments, whole-line and trailing', function () {
        $robots = "# Rules for everyone\nUser-agent: * # all of them\nDisallow: /private # keep out\n#Disallow: /";

        expect( robots( $robots, 'GPTBot', '/private' )['allowed'] )->toBeFalse()
            ->and( robots( $robots, 'GPTBot', '/blog' )['allowed'] )->toBeTrue();
    } );

    it( 'reads field names in any case, with or without spaces around the colon', function () {
        $robots = "USER-AGENT :*\nDISALLOW:/private\n  allow :  /private/open  ";

        expect( robots( $robots, 'GPTBot', '/private' )['allowed'] )->toBeFalse()
            ->and( robots( $robots, 'GPTBot', '/private/open' )['allowed'] )->toBeTrue();
    } );

    it( 'skips lines that are not fields', function () {
        $robots = "<html>Not found</html>\nUser-agent: *\nthis is not a rule\nDisallow: /private";

        expect( robots( $robots, 'GPTBot', '/private' )['allowed'] )->toBeFalse();
    } );

    it( 'is read when it starts with a byte order mark', function () {
        $robots = "\u{FEFF}User-agent: *\nDisallow: /";

        expect( robots( $robots ) )->toBe( array( 'allowed' => false, 'group' => '*' ) );
    } );
} );
