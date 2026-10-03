<?php

namespace ThatSeoAgent\Tests;

use DOMDocument;
use DOMElement;

/**
 * The <head> a page prints, read the way a crawler reads it.
 */
final class Page {

    /** @var list<array<string, string>> Attributes of each <meta>. */
    private array $metas = array();

    /** @var list<array<string, string>> Attributes of each <link>. */
    private array $links = array();

    /** @var list<mixed> Each JSON-LD block, decoded. */
    private array $json_ld = array();

    private string $title = '';

    /**
     * @param string      $head  What wp_head() printed.
     * @param string|null $title The document title, when the head has no
     *                           <title> of its own.
     */
    public function __construct( public readonly string $head, ?string $title = null ) {
        $document = new DOMDocument();
        libxml_use_internal_errors( true );
        $document->loadHTML( '<?xml encoding="UTF-8"><html><head>' . $head . '</head></html>' );
        libxml_clear_errors();

        foreach ( $document->getElementsByTagName( 'meta' ) as $meta ) {
            $this->metas[] = self::attributes( $meta );
        }
        foreach ( $document->getElementsByTagName( 'link' ) as $link ) {
            $this->links[] = self::attributes( $link );
        }
        foreach ( $document->getElementsByTagName( 'script' ) as $script ) {
            if ( 'application/ld+json' === $script->getAttribute( 'type' ) ) {
                $this->json_ld[] = json_decode( $script->textContent, true, 512, JSON_THROW_ON_ERROR );
            }
        }
        $title_fallback = $title;
        $title          = $document->getElementsByTagName( 'title' )->item( 0 );
        $this->title = $title ? $title->textContent : html_entity_decode( (string) $title_fallback, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    }

    /**
     * The content of the first <meta> with this name or property.
     */
    public function meta( string $key ): ?string {
        return $this->metas( $key )[0] ?? null;
    }

    /**
     * The contents of every <meta> with this name or property.
     *
     * @return list<string>
     */
    public function metas( string $key ): array {
        $found = array();
        foreach ( $this->metas as $meta ) {
            if ( ( $meta['name'] ?? $meta['property'] ?? null ) === $key ) {
                $found[] = $meta['content'] ?? '';
            }
        }

        return $found;
    }

    /**
     * Every <meta> name and property, in order.
     *
     * @return list<string>
     */
    public function metaKeys(): array {
        return array_values( array_filter( array_map(
            static fn ( array $meta ): ?string => $meta['name'] ?? $meta['property'] ?? null,
            $this->metas
        ) ) );
    }

    /**
     * The href of each <link> with this rel.
     *
     * @return list<string>
     */
    public function links( string $rel ): array {
        $found = array();
        foreach ( $this->links as $link ) {
            if ( in_array( $rel, explode( ' ', $link['rel'] ?? '' ), true ) ) {
                $found[] = $link['href'] ?? '';
            }
        }

        return $found;
    }

    /**
     * The <link> with this rel, when there is exactly one.
     */
    public function link( string $rel ): ?string {
        $links = $this->links( $rel );

        return 1 === count( $links ) ? $links[0] : null;
    }

    /**
     * The attributes of every <link> with this rel.
     *
     * @return list<array<string, string>>
     */
    public function linkTags( string $rel ): array {
        return array_values( array_filter(
            $this->links,
            static fn ( array $link ): bool => in_array( $rel, explode( ' ', $link['rel'] ?? '' ), true )
        ) );
    }

    public function title(): string {
        return $this->title;
    }

    /**
     * The robots directives, one per item.
     *
     * @return list<string>
     */
    public function robots(): array {
        $robots = $this->meta( 'robots' );

        return null === $robots ? array() : array_map( 'trim', explode( ',', $robots ) );
    }

    /**
     * Every JSON-LD block.
     *
     * @return list<mixed>
     */
    public function jsonLd(): array {
        return $this->json_ld;
    }

    /**
     * The nodes of the plugin's graph.
     *
     * @return list<array<string, mixed>>
     */
    public function graph(): array {
        foreach ( $this->json_ld as $block ) {
            if ( is_array( $block ) && isset( $block['@graph'] ) ) {
                return $block['@graph'];
            }
        }

        return array();
    }

    /**
     * The graph's nodes of one type.
     *
     * @return list<array<string, mixed>>
     */
    public function nodes( string $type ): array {
        return array_values( array_filter(
            $this->graph(),
            static fn ( array $node ): bool => in_array( $type, (array) ( $node['@type'] ?? array() ), true )
        ) );
    }

    /**
     * The graph's node of one type, when there is exactly one.
     *
     * @return array<string, mixed>|null
     */
    public function node( string $type ): ?array {
        $nodes = $this->nodes( $type );

        return 1 === count( $nodes ) ? $nodes[0] : null;
    }

    /**
     * The types of the graph's nodes, in order.
     *
     * @return list<string>
     */
    public function types(): array {
        return array_map( static fn ( array $node ): string => implode( '+', (array) $node['@type'] ), $this->graph() );
    }

    /**
     * @return array<string, string>
     */
    private static function attributes( DOMElement $element ): array {
        $attributes = array();
        foreach ( $element->attributes as $attribute ) {
            $attributes[ strtolower( $attribute->name ) ] = $attribute->value;
        }

        return $attributes;
    }
}
