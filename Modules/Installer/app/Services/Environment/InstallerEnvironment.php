<?php

namespace Modules\Installer\Services\Environment;

use Dotenv\Dotenv;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class InstallerEnvironment implements InstallerEnvironmentInterface
{
    /** {@inheritDoc} */
    public function write(array $values): void
    {
        $path = app()->environmentFilePath();
        $target = realpath($path) ?: $path;
        $source = File::exists($path) ? $path : base_path('.env.example');
        if (! is_readable($source) || ! is_writable(dirname($target))) {
            throw new HttpException(503, 'Application settings could not be saved. Ask hosting support to let the web PHP user read .env (or .env.example) and create and rename files in the folder containing .env during installation. Write permission on .env alone is not enough. Correct the permissions, then retry this step.');
        }
        $contents = File::exists($path) ? File::get($path) : File::get(base_path('.env.example'));
        foreach ($values as $key => $value) {
            // Escape interpolation as well as quotes: database passwords are literal values.
            $quoted = '"'.strtr((string) $value, ['\\' => '\\\\', '"' => '\\"', '$' => '\\$']).'"';
            $line = $key.'='.$quoted;
            $pattern = '/^(?:export\s+)?'.preg_quote($key, '/').'\s*=.*$/m';
            $contents = preg_match($pattern, $contents)
                ? preg_replace_callback($pattern, fn () => $line, $contents)
                : rtrim($contents)."\n".$line."\n";
        }
        $parsed = Dotenv::parse($contents);
        foreach ($values as $key => $value) {
            if (($parsed[$key] ?? null) !== (string) $value) {
                throw new RuntimeException('Environment values could not be safely encoded.');
            }
        }
        try {
            File::replace($path, $contents, 0600);
        } catch (Throwable $exception) {
            throw new HttpException(503, 'Saving .env failed. Ask hosting support to check available disk space and permission for the web PHP user to create and rename files in the settings folder. After correcting it, retry this step. Do not share the contents of .env with support.', $exception);
        }
    }
}
