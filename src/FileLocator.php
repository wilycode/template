<?php

declare(strict_types=1);

namespace WilyCode\Template;

use RuntimeException;

class FileLocator
{
    /**
     * @var array<string, string>
     */
    private array $paths = [];

    private const string MAIN_NAMESPACE = '__MAIN__';

    /**
     * @param array<string, string> $paths [namespace => directory]
     */
    public function __construct(array $paths = [])
    {
        foreach ($paths as $namespace => $path) {
            $this->addPath($namespace, $path);
        }
    }

    public function addPath(string $namespace, string $path): void
    {
        if ($namespace === '') {
            $namespace = self::MAIN_NAMESPACE;
        }

        $this->paths[$namespace] = rtrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * @throws RuntimeException
     */
    public function locate(string $name): string
    {
        $namespace = self::MAIN_NAMESPACE;
        $view = $name;


        if (strpos($name, '::') !== false) {
            [$ns, $view] = explode('::', $name, 2);
            $namespace = $ns !== '' ? $ns : self::MAIN_NAMESPACE;
        }

        if (!isset($this->paths[$namespace])) {
            throw new RuntimeException("View namespace '{$namespace}' is not registered.");
        }


        $relative = str_replace('.', DIRECTORY_SEPARATOR, $view);
        $fullPath = $this->paths[$namespace] . DIRECTORY_SEPARATOR . $relative . '.tpl';

        if (!is_file($fullPath)) {
            throw new RuntimeException("View '{$name}' not found at '{$fullPath}'.");
        }

        return $fullPath;
    }
}
