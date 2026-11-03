<?php

namespace QOpenPanel\Community\Services;

use RuntimeException;

class PrivateFile
{
    /** Exclusively creates a local file in an existing private directory. Never accepts streams. */
    public static function write(string $path, string $content): void
    {
        $parent = realpath(dirname($path));
        if (str_contains($path, '://') || str_contains($path, "\0") || ! $parent || file_exists($path) || is_link($path) || (PHP_OS_FAMILY !== 'Windows' && (fileperms($parent) & 0077) !== 0)) {
            throw new RuntimeException('private_local_file_required');
        }
        $old = umask(0077);
        try {
            $file = @fopen($path, 'x');
        } finally {
            umask($old);
        }
        if (! $file) {
            throw new RuntimeException('exclusive_file_required');
        }
        try {
            if (fwrite($file, $content) !== strlen($content)) {
                throw new RuntimeException('write_failed');
            }
        } finally {
            fclose($file);
        }
        if (PHP_OS_FAMILY !== 'Windows' && ! chmod($path, 0600)) {
            throw new RuntimeException('private_permissions_required');
        }
    }
}
