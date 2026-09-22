<?php

declare(strict_types=1);

namespace Lean_SEO\Dependencies\League\HTMLToMarkdown\Converter;

use Lean_SEO\Dependencies\League\HTMLToMarkdown\Configuration;
use Lean_SEO\Dependencies\League\HTMLToMarkdown\ConfigurationAwareInterface;
use Lean_SEO\Dependencies\League\HTMLToMarkdown\ElementInterface;

class DivConverter implements ConverterInterface, ConfigurationAwareInterface
{
    /** @var Configuration */
    protected $config;

    public function setConfig(Configuration $config): void
    {
        $this->config = $config;
    }

    public function convert(ElementInterface $element): string
    {
        if ($this->config->getOption('strip_tags', false)) {
            return $element->getValue() . "\n\n";
        }

        return \html_entity_decode($element->getChildrenAsString());
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['div'];
    }
}
