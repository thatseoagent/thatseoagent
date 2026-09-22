<?php

declare(strict_types=1);

namespace Lean_SEO\Dependencies\League\HTMLToMarkdown;

interface ConfigurationAwareInterface
{
    public function setConfig(Configuration $config): void;
}
