<?php

namespace Modules\Export\Services\ExportNotification;

use Illuminate\Contracts\Container\BindingResolutionException;
use Modules\Export\Models\Export;
use Modules\Export\Support\ExportSourceResolver;
use Modules\Notification\Services\NotificationDispatcher\NotificationDispatcherServiceInterface;
use Modules\Notification\Support\NotificationVariableValue;

class ExportNotificationService implements ExportNotificationServiceInterface
{
    /**
     * Create a new ExportNotificationService instance.
     */
    public function __construct(
        protected NotificationDispatcherServiceInterface $dispatcher,
        protected ExportSourceResolver $resolver,
    ) {}

    /** {@inheritDoc} */
    public function sendCompleted(Export $export): void
    {
        $export = $this->loadExport($export);
        if (! $export->user) {
            return;
        }

        $this->dispatcher->send('export_completed', $export->user, $this->variables($export));
    }

    /**
     * Load the export graph required for notifications.
     */
    protected function loadExport(Export $export): Export
    {
        $export->loadMissing('user.roles');

        return $export;
    }

    /**
     * Build notification variables for one export.
     *
     * @return array<string, mixed>
     *
     * @throws BindingResolutionException
     */
    protected function variables(Export $export): array
    {
        return [
            'type' => 'export',
            'type_id' => $export->id,
            'export_name' => $this->exportNameTranslations($export),
            'export_type' => $export->format->allTrans(),
            'completed_at' => dateTimeFormat($export->finished_at ?: $export->updated_at ?: now()),
            'error_message' => $export->error_message ?: '-',
            'requested_by' => $export->user?->name ?: '-',
            'app_name' => NotificationVariableValue::appName(),
        ];
    }

    /**
     * Resolve the export display name for all supported locales.
     *
     * @return array<string, string>
     *
     * @throws BindingResolutionException
     */
    protected function exportNameTranslations(Export $export): array
    {
        $source = $this->resolver->resolve($export->resource_key);
        $currentLocale = app()->getLocale();
        $translations = [];

        foreach (supportedLocaleKeys() as $locale) {
            app()->setLocale($locale);

            $translations[$locale] = method_exists($source, 'label')
                ? (string) $source->label()
                : (string) str($export->resource_key)->replace('_', ' ')->title();
        }

        app()->setLocale($currentLocale);

        return $translations;
    }

    /** {@inheritDoc} */
    public function sendFailed(Export $export): void
    {
        $export = $this->loadExport($export);
        if (! $export->user) {
            return;
        }

        $this->dispatcher->send('export_failed', $export->user, $this->variables($export));
    }
}
