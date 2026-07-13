<?php

namespace Chr0mX\ValheimModManager\Support;

use RuntimeException;

/**
 * Guards against path traversal and invalid filenames whenever this plugin
 * builds a path that will be sent to the daemon. The daemon itself jails
 * file operations to the server's data directory, but every path we
 * construct is validated here as well before it ever leaves this plugin.
 */
final class SafePath
{
    /**
     * Validate a single path segment (a file or directory name, no slashes).
     *
     * @throws RuntimeException
     */
    public static function assertSafeSegment(string $segment): string
    {
        if (
            $segment === ''
            || $segment === '.'
            || $segment === '..'
            || str_contains($segment, "\0")
            || str_contains($segment, '/')
            || str_contains($segment, '\\')
        ) {
            throw new RuntimeException(trans('valheim-mod-manager::strings.errors.path_traversal'));
        }

        return $segment;
    }

    /**
     * Validate a relative path made up of one or more segments.
     *
     * @throws RuntimeException
     */
    public static function assertSafeRelativePath(string $path): string
    {
        $path = trim($path, '/');

        if ($path === '' || str_contains($path, "\0") || str_contains($path, '..')) {
            throw new RuntimeException(trans('valheim-mod-manager::strings.errors.path_traversal'));
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException(trans('valheim-mod-manager::strings.errors.path_traversal'));
            }
        }

        return $path;
    }

    public static function join(string ...$segments): string
    {
        $segments = array_map(static fn (string $segment): string => trim($segment, '/'), array_filter($segments, static fn (string $segment): bool => $segment !== ''));

        return implode('/', $segments);
    }

    public static function isSafeSegment(string $segment): bool
    {
        try {
            self::assertSafeSegment($segment);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }
}
