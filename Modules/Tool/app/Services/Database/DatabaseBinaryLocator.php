<?php

namespace Modules\Tool\Services\Database;

use Symfony\Component\Process\ExecutableFinder;

class DatabaseBinaryLocator implements DatabaseBinaryLocatorInterface
{
    /** {@inheritDoc} */
    public function find(array $candidates): ?string
    {
        $finder = new ExecutableFinder;
        $directories = $this->directories();

        foreach ($candidates as $candidate) {
            if (blank($candidate)) {
                continue;
            }

            $candidate = (string) $candidate;
            if (strpbrk($candidate, '/\\') !== false && is_file($candidate) && is_executable($candidate)) {
                return realpath($candidate) ?: $candidate;
            }

            $binary = $finder->find($candidate, extraDirs: $directories);
            if ($binary !== null) {
                return $binary;
            }
        }

        return null;
    }

    /**
     * Resolve configured directories and versioned installation patterns.
     *
     * @return array<int, string>
     */
    private function directories(): array
    {
        $resolved = [];

        foreach (config('tool.database.binary_directories', []) as $directory) {
            if (! is_string($directory) || $directory === '') {
                continue;
            }

            $matches = strpbrk($directory, '*?[') === false
                ? [$directory]
                : (glob($directory, GLOB_ONLYDIR) ?: []);

            foreach ($matches as $match) {
                if (is_dir($match)) {
                    $resolved[] = realpath($match) ?: $match;
                }
            }
        }

        return array_values(array_unique($resolved));
    }
}
