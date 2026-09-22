<?php

declare(strict_types=1);

namespace Lean_SEO\Dependencies\League\HTMLToMarkdown;

interface PreConverterInterface
{
    public function preConvert(ElementInterface $element): void;
}
