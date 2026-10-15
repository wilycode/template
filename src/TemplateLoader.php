<?php

declare(strict_types=1);

namespace WilyCode\Template;

use RuntimeException;

class TemplateLoader
{
    private FileLocator $locator;

    public function __construct(FileLocator $locator)
    {
        $this->locator = $locator;
    }

    public function find(string $name): string
    {
        return $this->locator->locate($name);
    }

    /**
     * @throws RuntimeException
     */
    public function getSource(string $name): string
    {
        $path = $this->find($name);
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException("Cannot read template '{$name}' at '{$path}'.");
        }

        return $content;
    }

    /**
     * @throws RuntimeException
     */
    public function getLastModified(string $name): int
    {
        $path = $this->find($name);
        $mtime = filemtime($path);

        if ($mtime === false) {
            throw new RuntimeException("Cannot read mtime for template '{$name}' at '{$path}'.");
        }

        return $mtime;
    }
}
