<?php

namespace Modules\Installer\Services\Dependencies;

use Composer\InstalledVersions;
use Composer\Semver\Constraint\MultiConstraint;
use Composer\Semver\Intervals;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Illuminate\Support\Facades\File;

class InstallerDependencies implements InstallerDependenciesInterface
{
    /** {@inheritDoc} */
    public function run(): array
    {
        $read = fn (string $path) => json_decode(
            File::get($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $root = $read(base_path('composer.json'));
        $lock = $read(base_path('composer.lock'));

        $manifests = [$root];

        foreach ($root['extra']['merge-plugin']['include'] ?? [] as $pattern) {
            foreach (File::glob(base_path($pattern)) as $path) {
                $manifests[] = $read($path);
            }
        }

        $extensions = [];

        foreach (get_loaded_extensions() as $extension) {
            $extensions[strtolower($extension)] = phpversion($extension) ?: '*';
        }

        $results = $this->platform(
            [...$manifests, ...$lock['packages']],
            PHP_VERSION,
            PHP_INT_SIZE,
            $extensions
        );

        $mismatched = [];
        $parser = new VersionParser;

        foreach ($lock['packages'] as $package) {
            if (
                ! InstalledVersions::isInstalled($package['name'])
                || InstalledVersions::getVersion($package['name']) !== $parser->normalize($package['version'])
            ) {
                $mismatched[] = $package['name'].' '.$package['version'];
            }
        }

        foreach ($manifests as $manifest) {
            foreach ($manifest['require'] ?? [] as $name => $constraint) {
                if (! str_contains($name, '/')) {
                    continue;
                }

                if (
                    ! InstalledVersions::isInstalled($name)
                    || ! InstalledVersions::satisfies($parser, $name, $constraint)
                ) {
                    $mismatched[] = $name.' '.$constraint.' required by '.(
                        $manifest['name'] ?? 'Application'
                    );
                }
            }
        }

        $results['dependencies'] = [
            'passed' => $mismatched === [],
            'detail' => $mismatched === [] ? count($lock['packages']).' required application packages are installed.' : 'Some application packages are missing or do not match this release.',
            'remedy' => 'In the hosting terminal, open the folder containing composer.json and run composer install --no-dev --prefer-dist --optimize-autoloader with the website PHP version. Without terminal access, ask your developer or hosting support to install the dependencies for this release. Do not run composer update. '
                .'Missing or mismatched: '
                .implode(', ', array_unique($mismatched))
                .'.',
        ];

        return $results;
    }

    /** {@inheritDoc} */
    public function platform(
        array $packages,
        string $phpVersion,
        int $integerSize,
        array $extensions
    ): array {
        // Laravel's deployment requirements and the selected driver
        // supplement package-level requirements.
        $packages[] = [
            'name' => 'Forkiva runtime',
            'require' => [
                'ext-pdo' => '*',
                'ext-curl' => '*',
            ],
        ];

        $constraints = [];
        $phpFailures = [];
        $extensionFailures = [];
        $extensionNames = [];
        if (! isset($extensions['pdo_mysql']) && ! isset($extensions['pdo_pgsql'])) {
            $extensionFailures[] = 'Forkiva requires pdo_mysql or pdo_pgsql';
        }
        $requires64bit = false;

        foreach ($packages as $package) {
            foreach ($package['require'] ?? [] as $name => $constraint) {
                $owner = $package['name'] ?? 'Application';

                if (in_array($name, ['php', 'php-64bit'], true)) {
                    $constraints[] = $constraint;
                    $requires64bit = $requires64bit || $name === 'php-64bit';

                    if (
                        ! Semver::satisfies($phpVersion, $constraint)
                        || ($name === 'php-64bit' && $integerSize !== 8)
                    ) {
                        $phpFailures[] = $owner.' requires '.$name.' '.$constraint;
                    }
                } elseif (str_starts_with($name, 'ext-')) {
                    $extension = strtolower(substr($name, 4));
                    $extensionNames[] = $extension;

                    if (
                        ! isset($extensions[$extension])
                        || (
                            $constraint !== '*'
                            && (
                                $extensions[$extension] === '*'
                                || ! Semver::satisfies(
                                    $extensions[$extension],
                                    $constraint
                                )
                            )
                        )
                    ) {
                        $extensionFailures[] = $owner.' requires '.$name.' '.$constraint;
                    }
                }
            }
        }

        $parser = new VersionParser;

        $combined = Intervals::compactConstraint(
            MultiConstraint::create(
                array_map(
                    $parser->parseConstraints(...),
                    array_unique($constraints)
                ),
                true
            )
        );

        $required = trim(
            preg_replace(
                '/(\d+\.\d+\.\d+)\.0(?:-dev)?/',
                '$1',
                (string) $combined
            ),
            '[]'
        );

        return [
            'php' => [
                'passed' => $phpFailures === [],
                'detail' => 'PHP '
                    .$phpVersion
                    .' ('
                    .($integerSize * 8)
                    .'-bit). Locked dependencies require '
                    .$required
                    .($requires64bit ? ', 64-bit.' : '.'),
                'remedy' => 'In your hosting control panel, select PHP '.$required.($requires64bit ? ' (64-bit)' : '').' for this website, then restart PHP or ask hosting support to apply it. The website PHP version may differ from the terminal version; workers must also use a compatible version. Details: '
                    .implode('; ', array_unique($phpFailures))
                    .'.',
            ],
            'extensions' => [
                'passed' => $extensionFailures === [],
                'detail' => $extensionFailures === [] ? count(array_unique($extensionNames)).' required PHP extensions are available.' : 'The website PHP runtime is missing required extensions or has incompatible versions.',
                'remedy' => 'Open the PHP extensions selector for this website in your hosting control panel and enable the extensions below, then restart PHP and rerun checks. If they are not available, send this list to hosting support. Enabling an extension only for terminal PHP will not fix the website. Details: '
                    .implode('; ', array_unique($extensionFailures))
                    .'.',
            ],
        ];
    }
}
