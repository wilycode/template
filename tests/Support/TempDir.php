<?php

declare(strict_types=1);

namespace WilyCode\Template\Tests\Support;

use RuntimeException;

final class TempDir
{
    public static function create(string $prefix = 'wilycode_template_'): string
    {
        $base = sys_get_temp_dir();
        $dir = $base . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(8));

        if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create temp dir: {$dir}");
        }

        return $dir;
    }

    public static function remove(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($dir);
    }
}
