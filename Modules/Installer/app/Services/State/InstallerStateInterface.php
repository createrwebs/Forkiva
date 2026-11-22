<?php

namespace Modules\Installer\Services\State;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use JsonException;
use RuntimeException;

interface InstallerStateInterface
{
    /**
     * Return the private installation progress file path.
     */
    public function path(): string;

    /**
     * Read saved progress or return the initial installation state.
     *
     * @return array<string, mixed>
     *
     * @throws JsonException
     * @throws FileNotFoundException
     */
    public function read(): array;

    /**
     * Read progress and report whether another installation action holds the lock.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function snapshot(): array;

    /**
     * Atomically save progress in a file readable only by its owner.
     *
     * @param  array<string, mixed>  $state
     *
     * @throws JsonException
     */
    public function save(array $state): void;

    /**
     * Reject access when another browser session owns database preparation.
     *
     * @param  array<string, mixed>  $state
     */
    public function assertOwner(array $state): void;

    /**
     * Return the current session owner identifier without exposing its CSRF token.
     */
    public function owner(): string;

    /**
     * Execute an action under the nonblocking installation filesystem lock.
     *
     * @template T
     *
     * @param  callable(array<string, mixed>): T  $callback
     * @param  (callable(array<string, mixed>): array<string, mixed>)|null  $afterResponse
     * @return T
     *
     * @throws RuntimeException
     */
    public function locked(callable $callback, ?callable $afterResponse = null): mixed;

    /**
     * Remove ownership and internal task details before returning state to the browser.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function publicState(array $state): array;
}
