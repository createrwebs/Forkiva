<?php

namespace Modules\Installer\Services\Checks;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\File;
use Modules\Installer\Services\Dependencies\InstallerDependenciesInterface;
use Throwable;

class InstallerChecks implements InstallerChecksInterface
{
    /**
     * Resolve release requirements independently of filesystem readiness checks.
     */
    public function __construct(private InstallerDependenciesInterface $dependencies) {}

    /** {@inheritDoc} */
    public function run(): array
    {
        $dependencies = $this->dependencies->run();
        $paths = [
            'storage/app' => storage_path('app'),
            'storage/app/public' => storage_path('app/public'),
            'storage/framework' => storage_path('framework'),
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];
        $link = public_path('storage');
        if (! is_link($link)) {
            $paths['public directory'] = public_path();
        }
        $blocked = [];
        $storageLinkReady = (! file_exists($link) && ! is_link($link))
            || (is_link($link) && realpath($link) === realpath(storage_path('app/public')));
        foreach ($paths as $label => $path) {
            if (! $this->writableDirectory($path)) {
                $blocked[] = $label;
            }
        }
        $env = realpath(app()->environmentFilePath()) ?: app()->environmentFilePath();
        $environmentDirectory = dirname($env);
        $environmentReadable = is_readable(File::exists($env) ? $env : base_path('.env.example'));
        $environmentWritable = $this->writableDirectory($environmentDirectory);
        $checks = [
            $this->check('php', 'PHP runtime', ...$dependencies['php']),
            $this->check('extensions', 'Required extensions', ...$dependencies['extensions']),
            $this->check('dependencies', 'Composer dependencies', ...$dependencies['dependencies']),
            $this->check('permissions', 'Runtime folder access', $blocked === [], $blocked === [] ? 'The web server can create, read and remove runtime files.' : 'The web server cannot write to: '.implode(', ', $blocked).'.', 'In your hosting file manager, create any missing folders listed above. Ask hosting support to give the PHP process owner or group write access to those folders. Do not make the entire application writable or use 777 permissions. If public/ is blocked, support can create public/storage pointing to storage/app/public instead, then rerun checks.'),
            $this->check('environment', 'Saving application settings (.env)', $environmentReadable && $environmentWritable, 'Settings folder: '.$environmentDirectory.'. '.($environmentReadable ? 'The settings template can be read.' : 'The existing .env or .env.example cannot be read.'), 'The installer saves .env using a temporary file in this folder and an atomic rename, so file-only write permission is not enough. Ask hosting support to allow the web PHP user to create and rename files in this specific folder during setup, and to read the existing .env (or .env.example when .env is absent). Restore the previous folder permissions after installation. No recursive permission change is needed.'),
            $this->check('storage_link', 'Public storage link', $storageLinkReady, $storageLinkReady ? 'The public storage link is correct or can be created during setup.' : 'public/storage already exists as a folder or points to the wrong location.', 'Ask hosting support to preserve any existing files and create public/storage as a symbolic link to storage/app/public. Do not delete an existing storage folder without checking its contents. Rerun checks after correcting the link.'),
            $this->check('configuration', 'Configuration cache', ! app()->configurationIsCached(), app()->configurationIsCached() ? 'A cached configuration prevents new installation settings from taking effect.' : 'Installation settings can be reloaded between steps.', 'Open the hosting terminal in the folder containing artisan and run php artisan config:clear using the same PHP version as the website. Without terminal access, ask hosting support to run it. Then click Run checks again.'),
            $this->check('assets', 'Browser assets', $this->assetsReady(), 'Checking the installer files in public/build/.', 'Build this release locally using yarn install --frozen-lockfile and yarn build. Upload the entire public/build folder, including manifest.json and assets, to the matching server release. Node.js is not needed on the hosting server when uploading built assets. Remove a development-only public/hot file before deploying.'),
        ];

        return ['checks' => $checks, 'passed' => collect($checks)->every(fn ($check) => $check['passed'])];
    }

    /**
     * Verify directory write, read, and delete access using a temporary probe.
     */
    private function writableDirectory(string $path): bool
    {
        if (! is_dir($path) || ! is_writable($path)) {
            return false;
        }
        $probe = $path.'/.installer-probe-'.bin2hex(random_bytes(8));
        try {
            return File::put($probe, 'probe') === 5 && File::get($probe) === 'probe' && File::delete($probe);
        } catch (Throwable) {
            return false;
        } finally {
            if (is_file($probe)) {
                @unlink($probe);
            }
        }
    }

    /**
     * Describe a readiness result with a remedy only when it fails.
     *
     * @return array{id: string, label: string, passed: bool, detail: string, remedy: string|null}
     */
    private function check(string $id, string $label, bool $passed, string $detail, string $remedy): array
    {
        return compact('id', 'label', 'passed', 'detail') + ['remedy' => $passed ? null : $remedy];
    }

    /**
     * Accept Vite development mode or verify the built installer and its imports.
     */
    private function assetsReady(): bool
    {
        if (app(Vite::class)->isRunningHot()) {
            return app()->environment('local');
        }
        try {
            $manifest = json_decode(File::get(public_path('build/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
            $visited = [];
            $verify = function (string $source) use (&$verify, &$visited, $manifest): bool {
                if (isset($visited[$source])) {
                    return true;
                }
                $visited[$source] = true;
                $entry = $manifest[$source] ?? null;
                if (! $entry || ! isset($entry['file'])) {
                    return false;
                }
                foreach ([$entry['file'], ...($entry['css'] ?? [])] as $file) {
                    if (! is_file(public_path('build/'.$file))) {
                        return false;
                    }
                }
                foreach ($entry['imports'] ?? [] as $import) {
                    if (! $verify($import)) {
                        return false;
                    }
                }

                return true;
            };

            return $verify('Modules/Installer/resources/assets/js/main.ts');
        } catch (Throwable) {
            return false;
        }
    }
}
