<?php

namespace Modules\Import\Support;

use Illuminate\Support\Facades\Bus;
use InvalidArgumentException;
use Modules\Import\Jobs\ApplyImportJob;
use Modules\Import\Jobs\FinalizeImportJob;
use Modules\Import\Jobs\ValidateImportJob;
use Modules\Import\Models\DataImport;

class ImportContinuation
{
    /**
     * Opt-in browser clients advance sync imports in bounded requests; other clients keep queue behavior.
     */
    public static function requested(): bool
    {
        return request()->header('X-Import-Batches') === '1'
            && config('queue.connections.'.config('queue.default').'.driver') === 'sync';
    }

    public static function schedule(DataImport $import, string $phase, int $afterId = 0, bool $dependencies = false): void
    {
        $continuation = ['phase' => $phase, 'after_id' => $afterId, 'dependencies' => $dependencies];
        if ($import->client_processing) {
            $import->update(['continuation' => $continuation]);

            return;
        }

        Bus::dispatch(self::job($import, $continuation)->afterCommit());
    }

    public static function job(DataImport $import, array $continuation): ValidateImportJob|ApplyImportJob|FinalizeImportJob
    {
        $afterId = (int) $continuation['after_id'];

        return match ($continuation['phase']) {
            'validate' => new ValidateImportJob($import->id, $import->revision, $afterId, (bool) $continuation['dependencies']),
            'apply' => new ApplyImportJob($import->id, $import->revision, $afterId),
            'finalize' => new FinalizeImportJob($import->id, $import->revision, $afterId),
            default => throw new InvalidArgumentException('Unknown import continuation phase.'),
        };
    }
}
