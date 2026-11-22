<?php

namespace Modules\Export\tests\Feature;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Export\Contracts\GeneratesExportFilesInterface;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\Export\Enums\ExportFormat;
use Modules\Export\Enums\ExportStatus;
use Modules\Export\Events\ExportCompleted;
use Modules\Export\Jobs\GenerateExportJob;
use Modules\Export\Models\Export;
use Modules\Export\Support\ExportSourceResolver;
use Modules\Export\Support\ExportValueNormalizer;
use Modules\Export\Support\GeneratedExportFile;
use Modules\Notification\Database\Seeders\NotificationDatabaseSeeder;
use Modules\Notification\Notifications\RenderedTemplateNotification;
use Modules\Support\Eloquent\Model;
use Modules\User\Enums\DefaultRole;
use RuntimeException;
use Tests\NotificationWorkflowTestCase;
use Throwable;

class ExportNotificationWorkflowTest extends NotificationWorkflowTestCase
{
    public function test_custom_json_export_uses_the_source_owned_generator(): void
    {
        Event::fake([ExportCompleted::class]);
        config()->set('export.resources.custom_exports', FakeCustomExportSource::class);

        $user = $this->createUserWithRole(DefaultRole::Manager->value, [
            'is_active' => true,
        ]);
        $export = Export::query()->create([
            'user_id' => $user->id,
            'resource_key' => 'custom_exports',
            'context_key' => '',
            'format' => ExportFormat::Json,
            'status' => ExportStatus::Pending,
            'filters' => [],
            'sorts' => [],
            'context' => [],
        ]);

        (new GenerateExportJob($export))->handle(app(ExportSourceResolver::class), app(ExportValueNormalizer::class));

        $export->refresh();
        $this->assertSame(ExportStatus::Completed, $export->status);
        $this->assertSame('custom.json', $export->file_name);
        $this->assertSame('exports/custom.json', $export->path);
        Event::assertDispatched(ExportCompleted::class);
    }

    public function test_completed_export_sends_export_completed_notification(): void
    {
        Notification::fake();
        Excel::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        config()->set('export.resources.test_exports', FakeExportSource::class);

        $user = $this->createUserWithRole(DefaultRole::Manager->value, [
            'is_active' => true,
        ]);

        $this->setWorkflowChannels('export_completed', [
            'in_app' => [
                DefaultRole::Manager->value => true,
            ],
        ]);

        $export = Export::query()->create([
            'user_id' => $user->id,
            'resource_key' => 'test_exports',
            'context_key' => '',
            'format' => ExportFormat::Xlsx,
            'status' => ExportStatus::Pending,
            'filters' => [],
            'sorts' => [],
            'context' => [],
        ]);

        (new GenerateExportJob($export))->handle(app(ExportSourceResolver::class), app(ExportValueNormalizer::class));

        Notification::assertSentTo($user, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($user) {
            $payload = $notification->toDatabase($user);

            return $channels === ['database']
                && $payload['type'] === 'export'
                && $this->notificationTitleMatchesBase($payload, 'Export Completed');
        });
    }

    public function test_failed_export_sends_export_failed_notification(): void
    {
        Notification::fake();
        $this->fakeServiceRegistry(['in_app']);
        $this->seed(NotificationDatabaseSeeder::class);

        config()->set('export.resources.failing_exports', FailingExportSource::class);

        $user = $this->createUserWithRole(DefaultRole::Manager->value, [
            'is_active' => true,
        ]);

        $this->setWorkflowChannels('export_failed', [
            'in_app' => [
                DefaultRole::Manager->value => true,
            ],
        ]);

        $export = Export::query()->create([
            'user_id' => $user->id,
            'resource_key' => 'failing_exports',
            'context_key' => '',
            'format' => ExportFormat::Xlsx,
            'status' => ExportStatus::Pending,
            'filters' => [],
            'sorts' => [],
            'context' => [],
        ]);

        try {
            (new GenerateExportJob($export))->handle(app(ExportSourceResolver::class), app(ExportValueNormalizer::class));
            $this->fail('Expected export generation to fail.');
        } catch (Throwable) {
        }

        Notification::assertSentTo($user, RenderedTemplateNotification::class, function (RenderedTemplateNotification $notification, array $channels) use ($user) {
            $payload = $notification->toDatabase($user);

            return $channels === ['database']
                && $payload['type'] === 'export'
                && $this->notificationTitleMatchesBase($payload, 'Export Failed');
        });
    }
}

class FakeCustomExportSource implements GeneratesExportFilesInterface
{
    public function exportResourceKey(): string
    {
        return 'custom_exports';
    }

    public function exportPermission(): string
    {
        return 'admin.exports.index';
    }

    public function validateExport(array $filters = [], ?array $sorts = [], array $context = []): void {}

    public function generate(Export $export): GeneratedExportFile
    {
        return new GeneratedExportFile('local', 'exports/custom.json', 'custom.json');
    }
}

class FakeExportSource implements TableExportSourceInterface
{
    public function label(): string
    {
        return __('export::exports.export');
    }

    public function exportResourceKey(): string
    {
        return 'test_exports';
    }

    public function exportPermission(): string
    {
        return 'admin.exports.index';
    }

    public function exportModelClass(): string
    {
        return FakeExportRecord::class;
    }

    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder
    {
        return FakeExportRecord::query();
    }
}

class FailingExportSource extends FakeExportSource
{
    public function exportResourceKey(): string
    {
        return 'failing_exports';
    }

    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder
    {
        throw new RuntimeException('Export source failed.');
    }
}

class FakeExportRecord extends Model implements ModelExportDefinitionInterface
{
    protected $table = 'exports';

    protected $guarded = [];

    public static function exportColumns(): array
    {
        return [
            ['path' => 'id', 'label' => 'admin::admin.table.id'],
        ];
    }
}
