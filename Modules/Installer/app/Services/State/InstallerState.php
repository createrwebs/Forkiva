<?php

namespace Modules\Installer\Services\State;

use App\Forkiva;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class InstallerState implements InstallerStateInterface
{
    /** {@inheritDoc} */
    public function snapshot(): array
    {
        $handle = fopen(
            storage_path('framework/installer.lock'),
            'c'
        );

        if ($handle === false) {
            throw new RuntimeException(
                'The installation lock could not be opened.'
            );
        }

        $idle = flock($handle, LOCK_EX | LOCK_NB);

        try {
            return array_merge(
                $this->read(),
                ['processing' => ! $idle]
            );
        } finally {
            if ($idle) {
                flock($handle, LOCK_UN);
            }

            fclose($handle);
        }
    }

    /** {@inheritDoc} */
    public function read(): array
    {
        if (! File::exists($this->path())) {
            return [
                'checks_passed' => false,
                'database' => false,
                'tasks' => [],
                'admin' => false,
                'finished' => false,
            ];
        }

        return json_decode(
            File::get($this->path()),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    /** {@inheritDoc} */
    public function path(): string
    {
        return storage_path('framework/installer-state.json');
    }

    /** {@inheritDoc} */
    public function locked(
        callable $callback,
        ?callable $afterResponse = null
    ): mixed {
        $handle = fopen(
            storage_path('framework/installer.lock'),
            'c'
        );

        if ($handle === false) {
            throw new RuntimeException(
                'The installation lock could not be created.'
            );
        }

        $deferred = false;

        try {
            abort_unless(
                flock($handle, LOCK_EX | LOCK_NB),
                409,
                'Another installation action is running. '
                .'Wait, then refresh the status.'
            );

            abort_if(
                Forkiva::installed(),
                403,
                'Installation is already complete.'
            );

            $state = $this->read();

            $this->assertOwner($state);

            $result = $callback($state);

            if (
                $afterResponse !== null
                && ($result['processing'] ?? false)
            ) {
                // Laravel finishes the FastCGI response before termination
                // callbacks run. Keep this same lock open across both phases
                // so there is no dispatch race.
                app()->terminating(
                    function () use (
                        $handle,
                        $afterResponse,
                        $result
                    ) {
                        if (! is_resource($handle)) {
                            return;
                        }

                        $state = $result;

                        unset($state['processing']);

                        try {
                            $this->save(
                                $afterResponse($state)
                            );
                        } catch (Throwable $exception) {
                            Log::error(
                                'Installer deferred task failed',
                                [
                                    'exception' => $exception::class,
                                    'code' => (string) $exception->getCode(),
                                ]
                            );

                            $state = $this->read();

                            foreach ($state['tasks'] as &$task) {
                                if ($task['status'] === 'running') {
                                    $task['status'] = 'failed';
                                }
                            }

                            unset($task);

                            $state['error'] =
                                'Preparation stopped before the task completed. '
                                .'Your completed tasks are saved. '
                                .'Retry preparation to continue.';

                            $this->save($state);
                        } finally {
                            flock($handle, LOCK_UN);
                            fclose($handle);
                        }
                    }
                );

                $deferred = true;
            }

            return $result;
        } finally {
            if (! $deferred) {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        }
    }

    /** {@inheritDoc} */
    public function assertOwner(array $state): void
    {
        abort_if(
            isset($state['owner'])
            && ! hash_equals($state['owner'], $this->owner()),
            409,
            'Setup is open in another browser session. '
            .'Continue in the browser that started installation.'
        );
    }

    /** {@inheritDoc} */
    public function owner(): string
    {
        return hash('sha256', session()->token());
    }

    /** {@inheritDoc} */
    public function save(array $state): void
    {
        File::replace(
            $this->path(),
            json_encode($state, JSON_THROW_ON_ERROR),
            0600
        );
    }

    /** {@inheritDoc} */
    public function publicState(array $state): array
    {
        unset(
            $state['owner'],
            $state['connection_fingerprint']
        );

        $state['tasks'] = array_map(
            fn ($task) => array_intersect_key(
                $task,
                array_flip([
                    'id',
                    'label',
                    'status',
                ])
            ),
            $state['tasks']
        );

        return $state;
    }
}
