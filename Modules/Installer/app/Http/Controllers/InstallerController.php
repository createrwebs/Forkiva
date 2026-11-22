<?php

namespace Modules\Installer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use JsonException;
use Modules\Core\Http\Controllers\Controller;
use Modules\Installer\Http\Requests\AdminRequest;
use Modules\Installer\Http\Requests\ConfigurationRequest;
use Modules\Installer\Http\Requests\DatabaseRequest;
use Modules\Installer\Http\Requests\FinishRequest;
use Modules\Installer\Services\Configuration\InstallerConfigurationInterface;
use Modules\Installer\Services\Installation\InstallationServiceInterface;
use Modules\Installer\Services\State\InstallerStateInterface;
use Modules\Installer\Support\InstallerFailure;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class InstallerController extends Controller
{
    /**
     * Create the installer controller with its state and installation services.
     */
    public function __construct(
        private readonly InstallerStateInterface $state,
        private readonly InstallationServiceInterface $installation,
        private readonly InstallerConfigurationInterface $configuration,
    ) {}

    /**
     * Return installation progress accessible to the current browser session.
     *
     * @throws JsonException
     */
    public function status(): JsonResponse
    {
        try {
            $state = $this->state->snapshot();
            $this->state->assertOwner($state);

            return response()->json(['body' => $this->state->publicState($state) + ['options' => $this->configuration->options()]]);
        } catch (Throwable $exception) {
            return $this->failureResponse($exception);
        }
    }

    /**
     * Refresh and persist the server readiness checks.
     */
    public function checks(): JsonResponse
    {
        return $this->mutate(fn ($state) => $this->installation->check($state));
    }

    /**
     * Serialize an installation action and return browser-safe state or errors.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $action
     *
     * @throws ValidationException
     */
    private function mutate(callable $action, ?callable $afterResponse = null): JsonResponse
    {
        try {
            $state = $this->state->locked($action, $afterResponse);

            $response = response()->json(
                ['body' => $this->state->publicState($state) + ['options' => $this->configuration->options()]],
                ! empty($state['processing']) ? 202 : 200
            );
            $response->headers->set('Content-Length', (string) strlen($response->getContent()));

            return $response;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->failureResponse($exception);
        }
    }

    /**
     * Return actionable errors without Laravel debug traces, SQL, or connection details.
     */
    private function failureResponse(Throwable $exception): JsonResponse
    {
        if ($exception instanceof HttpExceptionInterface) {
            return response()->json(['message' => $exception->getMessage()], $exception->getStatusCode());
        }

        $reference = InstallerFailure::record($exception, 'installation request');

        return response()->json(['message' => 'The server could not complete this installation step. Ask your hosting support or developer to check storage/logs or the hosting PHP error log for support reference '.$reference.'. Keep this setup session open and do not reset the database. After the cause is corrected, refresh the status before retrying.'], 500);
    }

    /**
     * Save validated application defaults before database preparation.
     */
    public function configuration(ConfigurationRequest $request): JsonResponse
    {
        return $this->mutate(fn ($state) => $this->installation->configuration($state, $request->validated()));
    }

    /**
     * Verify and save the validated database configuration.
     */
    public function database(DatabaseRequest $request): JsonResponse
    {
        return $this->mutate(fn ($state) => $this->installation->database($state, $request->validated()));
    }

    /**
     * Process the next pending installation task and return its persisted status.
     */
    public function process(): JsonResponse
    {
        return $this->mutate(
            fn ($state) => $this->installation->prepareProcess($state),
            fn ($state) => $this->installation->process($state)
        );
    }

    /**
     * Save the validated administrator after database preparation completes.
     */
    public function admin(AdminRequest $request): JsonResponse
    {
        return $this->mutate(fn ($state) => $this->installation->admin($state, $request->validated()));
    }

    /**
     * Verify launch prerequisites and permanently close installation.
     */
    public function finish(FinishRequest $request): JsonResponse
    {
        return $this->mutate(fn ($state) => $this->installation->finish($state, $request->validated()));
    }
}
