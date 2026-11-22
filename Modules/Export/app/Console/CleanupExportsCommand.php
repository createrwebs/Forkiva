<?php

namespace Modules\Export\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\Export\Models\Export;

class CleanupExportsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'exports:cleanup';

    /**
     * @var string
     */
    protected $description = 'Delete expired generated exports and their files';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Export::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($exports) {
                foreach ($exports as $export) {
                    if ($export->disk && $export->path) {
                        Storage::disk($export->disk)->delete($export->path);
                    }

                    $export->delete();
                }
            });

        $this->info(__('export::exports.messages.cleanup_completed'));

        return self::SUCCESS;
    }
}
