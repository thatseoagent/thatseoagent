<?php

declare(strict_types=1);

namespace ThatSeoAgent\Dependencies\League\HTMLToMarkdown\Converter;

use ThatSeoAgent\Dependencies\League\HTMLToMarkdown\ElementInterface;
use ThatSeoAgent\Dependencies\League\HTMLToMarkdown\LinkSyntax;

class ImageConverter implements ConverterInterface
{
    public function convert(ElementInterface $element): string
    {
        $src   = LinkSyntax::escapeDestination($element->getAttribute('src'));
        $alt   = LinkSyntax::escapeText($element->getAttribute('alt'));
        $title = LinkSyntax::escapeText($element->getAttribute('title'));

        if ($title !== '') {
            // No newlines added. <img> should be in a block-level element.
            // Without a destination, the title would be taken as one.
            return '![' . $alt . '](' . ($src === '' ? '<>' : $src) . ' "' . $title . '")';
        }

        return '![' . $alt . '](' . $src . ')';
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['img'];
    }
}
