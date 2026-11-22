<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;

class UpgradeCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'forkiva:upgrade';

    /**
     * The console command description.
     */
    protected $description = 'Command for upgrade forkiva.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->call('module:migrate', ['-a' => true, '--force' => true]);
        $this->call('permission:sync-permissions');
        $this->call('permission:sync-default-roles');
        $this->call('cache:clear');
        $this->call('config:clear');
        $this->call('optimize:clear');

        return self::SUCCESS;
    }
}
