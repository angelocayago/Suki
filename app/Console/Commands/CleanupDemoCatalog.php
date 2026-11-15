<?php

namespace App\Console\Commands;

use App\Services\CatalogDemoCleanupService;
use Illuminate\Console\Command;

class CleanupDemoCatalog extends Command
{
    protected $signature = 'catalog:cleanup-demo-content {--apply : Apply the reviewed cleanup instead of only previewing it}';
    protected $description = 'Preview or explicitly normalize known demo-provenance catalog records';

    public function handle(CatalogDemoCleanupService $cleanup): int
    {
        $plan = $cleanup->plan();
        $this->table(['Product ID', 'Reference', 'Action', 'Reason'], array_map(fn ($entry) => [
            $entry['product']->id, $entry['product']->legacy_reference, $entry['action'], $entry['reason'],
        ], $plan));
        if (! $this->option('apply')) {
            $this->info('DRY RUN: no products, orders, reviews, or historical references were changed. Re-run with --apply only after review.');
            return self::SUCCESS;
        }
        $changed = $cleanup->apply();
        $this->info('APPLIED: '.count($changed).' known demo-provenance products normalized or unpublished; audit records were written.');
        return self::SUCCESS;
    }
}
