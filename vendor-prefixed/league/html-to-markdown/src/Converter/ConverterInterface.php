<?php

declare(strict_types=1);

namespace Lean_SEO\Dependencies\League\HTMLToMarkdown\Converter;

use Lean_SEO\Dependencies\League\HTMLToMarkdown\ElementInterface;

interface ConverterInterface
{
    public function convert(ElementInterface $element): string;

    /**
     * @return string[]
     */
    public function getSupportedTags(): array;
}
