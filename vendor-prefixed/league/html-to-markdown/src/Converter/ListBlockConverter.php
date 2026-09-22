<?php

declare(strict_types=1);

namespace Lean_SEO\Dependencies\League\HTMLToMarkdown\Converter;

use Lean_SEO\Dependencies\League\HTMLToMarkdown\ElementInterface;

class ListBlockConverter implements ConverterInterface
{
    public function convert(ElementInterface $element): string
    {
        return $element->getValue() . "\n";
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['ol', 'ul'];
    }
}
