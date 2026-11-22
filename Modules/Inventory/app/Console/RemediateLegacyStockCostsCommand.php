<?php

namespace Modules\Inventory\Console;

use Illuminate\Console\Command;
use Modules\Inventory\Services\LegacyStockCostRemediation\LegacyStockCostRemediationServiceInterface;
use Throwable;

class RemediateLegacyStockCostsCommand extends Command
{
    protected $signature = 'inventory:remediate-legacy-stock-costs
        {--branch= : Limit remediation to one branch}
        {--apply : Persist the audited remediation}
        {--force : Skip the production confirmation}';

    protected $description = 'Audit and remediate legacy stock movements that have no immutable cost snapshot.';

    /**
     * @throws Throwable
     */
    public function handle(LegacyStockCostRemediationServiceInterface $service): int
    {
        $apply = (bool) $this->option('apply');
        if ($apply && ! $this->option('force') && ! $this->confirm(
            'Apply provenance-tagged costs to all matching legacy movements?',
        )) {
            return self::FAILURE;
        }

        $result = $service->remediate(
            apply: $apply,
            branchId: $this->option('branch') ? (int) $this->option('branch') : null,
        );

        $this->table(
            ['Legacy', 'Source document', 'Moving average', 'Current-cost estimate', 'Baselines', 'Remediated'],
            [[
                $result['legacy_movements'],
                $result['source_document'],
                $result['reconstructed_moving_average'],
                $result['estimated_current_ingredient_cost'],
                $result['reconciliation_baselines'],
                $result['remediated'],
            ]],
        );

        if (! $apply) {
            $this->components->warn('Dry run only. Re-run with --apply after reviewing the provenance totals.');
        }

        return self::SUCCESS;
    }
}
