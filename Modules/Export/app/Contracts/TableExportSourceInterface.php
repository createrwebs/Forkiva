<?php

namespace Modules\Export\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface TableExportSourceInterface extends ExportSourceInterface
{
    /**
     * Backing model class used for export definitions.
     *
     * @return class-string<ModelExportDefinitionInterface>
     */
    public function exportModelClass(): string;

    /**
     * Shared query used by both the table and the export job.
     */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder;
}
