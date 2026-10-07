<?php

declare(strict_types=1);

namespace ThatSeoAgent\Dependencies\League\HTMLToMarkdown\Converter;

use ThatSeoAgent\Dependencies\League\HTMLToMarkdown\Configuration;
use ThatSeoAgent\Dependencies\League\HTMLToMarkdown\ConfigurationAwareInterface;
use ThatSeoAgent\Dependencies\League\HTMLToMarkdown\ElementInterface;
use ThatSeoAgent\Dependencies\League\HTMLToMarkdown\RawHtml;

class DefaultConverter implements ConverterInterface, ConfigurationAwareInterface
{
    public const DEFAULT_CONVERTER = '_default';

    /** @var Configuration */
    protected $config;

    public function setConfig(Configuration $config): void
    {
        $this->config = $config;
    }

    public function convert(ElementInterface $element): string
    {
        // If strip_tags is false (the default), preserve tags that don't have Markdown equivalents,
        // such as <span> nodes on their own.
        if ($this->config->getOption('strip_tags', false)) {
            return $element->getValue();
        }

        $markdown = RawHtml::fromElement($element);

        // Tables are only handled here if TableConverter is not used
        if ($element->getTagName() === 'table') {
            $markdown .= "\n\n";
        }

        return $markdown;
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return [self::DEFAULT_CONVERTER];
    }
}
