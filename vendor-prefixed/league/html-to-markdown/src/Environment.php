<?php

declare(strict_types=1);

namespace Lean_SEO\Dependencies\League\HTMLToMarkdown;

use Lean_SEO\Dependencies\League\HTMLToMarkdown\Converter\BlockquoteConverter;
use Lean_SEO\Dependencies\League\HTMLToMarkdown\Converter\CodeConverter;
use Lean_SEO\Dependencies\League\HTMLToMarkdown\Converter\CommentConverter;
use Lean_SEO\Dependencies\League\HTMLToMarkdown\Converter\ConverterInterface;
use Lean_SEO\Dependencies\League\HTMLToMarkdown\Converter\DefaultConverter;
use Lean_SEO\Dependencies\League\HTMLToMarkdown\Converter\DivConverter;
use Lean_SEO\Dependencies\League\HTMLToMarkdown\Converter\EmphasisConverter;
use Lean_SEO\Dependencies\League\HTMLToMarkdown\Converter\HardBreakConverter;
use Lean_SEO\Dependencies\League\HTMLToMarkdown\Converter\HeaderConverter;
use Lean_SEO\Dependencies\League\HTMLToMarkdown\Converter\HorizontalRuleConverter;
use Lean_SEO\Dependencies\League\HTMLToMarkdown\Converter\ImageConverter;
use Lean_SEO\Dependencies\League\HTMLToMarkdown\Converter\LinkConverter;
use Lean_SEO\Dependencies\League\HTMLToMarkdown\Converter\ListBlockConverter;
use Lean_SEO\Dependencies\League\HTMLToMarkdown\Converter\ListItemConverter;
use Lean_SEO\Dependencies\League\HTMLToMarkdown\Converter\ParagraphConverter;
use Lean_SEO\Dependencies\League\HTMLToMarkdown\Converter\PreformattedConverter;
use Lean_SEO\Dependencies\League\HTMLToMarkdown\Converter\TextConverter;

final class Environment
{
    /** @var Configuration */
    protected $config;

    /** @var ConverterInterface[] */
    protected $converters = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = new Configuration($config);
        $this->addConverter(new DefaultConverter());
    }

    public function getConfig(): Configuration
    {
        return $this->config;
    }

    public function addConverter(ConverterInterface $converter): void
    {
        if ($converter instanceof ConfigurationAwareInterface) {
            $converter->setConfig($this->config);
        }

        foreach ($converter->getSupportedTags() as $tag) {
            $this->converters[$tag] = $converter;
        }
    }

    public function getConverterByTag(string $tag): ConverterInterface
    {
        if (isset($this->converters[$tag])) {
            return $this->converters[$tag];
        }

        return $this->converters[DefaultConverter::DEFAULT_CONVERTER];
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function createDefaultEnvironment(array $config = []): Environment
    {
        $environment = new static($config);

        $environment->addConverter(new BlockquoteConverter());
        $environment->addConverter(new CodeConverter());
        $environment->addConverter(new CommentConverter());
        $environment->addConverter(new DivConverter());
        $environment->addConverter(new EmphasisConverter());
        $environment->addConverter(new HardBreakConverter());
        $environment->addConverter(new HeaderConverter());
        $environment->addConverter(new HorizontalRuleConverter());
        $environment->addConverter(new ImageConverter());
        $environment->addConverter(new LinkConverter());
        $environment->addConverter(new ListBlockConverter());
        $environment->addConverter(new ListItemConverter());
        $environment->addConverter(new ParagraphConverter());
        $environment->addConverter(new PreformattedConverter());
        $environment->addConverter(new TextConverter());

        return $environment;
    }
}
