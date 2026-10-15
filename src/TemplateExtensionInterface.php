<?php

declare(strict_types=1);

namespace WilyCode\Template;

interface TemplateExtensionInterface
{
    public function register(TemplateEngine $engine): void;
}
