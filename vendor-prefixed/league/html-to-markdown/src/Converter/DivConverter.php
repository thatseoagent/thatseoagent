<?php

declare(strict_types=1);

namespace ThatSeoAgent\Dependencies\League\HTMLToMarkdown\Converter;

use ThatSeoAgent\Dependencies\League\HTMLToMarkdown\Configuration;
use ThatSeoAgent\Dependencies\League\HTMLToMarkdown\ConfigurationAwareInterface;
use ThatSeoAgent\Dependencies\League\HTMLToMarkdown\ElementInterface;
use ThatSeoAgent\Dependencies\League\HTMLToMarkdown\RawHtml;

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

        return RawHtml::fromElement($element);
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['div'];
    }
}
