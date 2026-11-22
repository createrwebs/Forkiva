<?php

namespace Modules\Installer\Services\Installation;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Validation\ValidationException;
use JsonException;
use Random\RandomException;
use Throwable;

interface InstallationServiceInterface
{
    /**
     * Refresh readiness results and persist them with the current progress.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function check(array $state): array;

    /**
     * Verify an empty database and migration privileges before saving credentials.
     *
     * @param  array<string, mixed>  $state
     * @param  array{db_host: string, db_port: int|string, db_name: string, db_user: string, db_pass?: string|null, with_demo: bool|int|string}  $data  Validated database input.
     * @return array<string, mixed>
     *
     * @throws ValidationException
     * @throws FileNotFoundException
     * @throws JsonException
     */
    public function database(array $state, array $data): array;

    /**
     * Save validated application defaults before database preparation begins.
     *
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws FileNotFoundException
     * @throws RandomException
     * @throws JsonException
     */
    public function configuration(array $state, array $data): array;

    /**
     * Run at most one pending task and persist its completion or recoverable failure.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function process(array $state): array;

    /**
     * Validate and mark the next task before acknowledging asynchronous execution.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function prepareProcess(array $state): array;

    /**
     * Create or replace the initial administrator once all preparation tasks complete.
     *
     * @param  array<string, mixed>  $state
     * @param  array{name: string, username: string, email: string, password: string}  $data  Validated administrator input.
     * @return array<string, mixed>
     *
     * @throws ValidationException
     * @throws JsonException
     * @throws Throwable
     */
    public function admin(array $state, array $data): array;

    /**
     * Recheck readiness, enable production settings, and persist the installation lockout.
     *
     * @param  array<string, mixed>  $state
     * @param  array{app_url: string}  $data  Validated public application URL.
     * @return array<string, mixed>
     *
     * @throws ValidationException
     * @throws JsonException
     * @throws FileNotFoundException
     */
    public function finish(array $state, array $data): array;
}
