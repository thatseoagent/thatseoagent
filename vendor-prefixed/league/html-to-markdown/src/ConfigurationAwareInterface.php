<?php

declare(strict_types=1);

namespace ThatSeoAgent\Dependencies\League\HTMLToMarkdown;

interface ConfigurationAwareInterface
{
    public function setConfig(Configuration $config): void;
}
