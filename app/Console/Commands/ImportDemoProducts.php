<?php

namespace App\Console\Commands;

use App\Services\{ProductCatalogService, SellerCategoryService};
use App\Support\{DemoImportDatabase, DemoProductBatch, DemoProductImages, DemoProductImageException};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ImportDemoProducts extends Command
{
    protected $signature = 'demo:products:import
        {--seller-id= : Must explicitly be sellers.id 3}
        {--expect-store= : Must exactly be Lahat Meron}
        {--connection= : Explicit configured SQLite or approved PostgreSQL connection}
        {--expect-environment= : Exact application environment}
        {--confirm-database= : Exact SQLite real path or PostgreSQL database name}
        {--expect-owner-id= : Reviewed owner ID; required for PostgreSQL and all execution}
        {--expect-project= : Exact pinned Supabase project; PostgreSQL only}
        {--confirm-execution= : Exact separate PostgreSQL execution phrase}
        {--confirm-plan= : Reviewed dry-run SHA-256 fingerprint; PostgreSQL execution only}
        {--execute : Write the reviewed batch; otherwise preview only}';

    protected $description = 'Safely preview or import the fixed Lahat Meron demo pack';
    private ?array $postgresIdentity = null;

    public function handle(ProductCatalogService $catalog, SellerCategoryService $categories, DemoProductBatch $batch, DemoImportDatabase $guard): int
    {
        $previous = DB::getDefaultConnection();
        try {
            DB::setDefaultConnection($this->safeConnection($guard));
            $inspect = function () use ($batch, $categories) {
                [$seller, $category] = $batch->target($categories, false, $this->option('expect-owner-id'));
                $rows = $batch->dataset($category->id);
                app(DemoProductImages::class)->published(array_column($rows, 'key'));
                return [$seller, $category, $rows, $batch->preflight($seller, $rows)];
            };
            if ($this->postgresIdentity) {
                $guard->verifyConnectionConfiguration(DB::connection(), $this->postgresIdentity);
                DB::beginTransaction();
                try {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
                    DB::statement("SET LOCAL statement_timeout = '15000ms'");
                    DB::statement("SET LOCAL lock_timeout = '2000ms'");
                    $guard->verifyServer(DB::connection(), $this->postgresIdentity, true);
                    $preview = $inspect();
                } finally {
                    DB::rollBack();
                }
            } else {
                $preview = $inspect();
            }
            [$seller, $category, $rows, $plan] = $preview;
            $fingerprint = $batch->importFingerprint($seller,$category,$plan,$this->postgresIdentity);
            $this->info('Target: sellers.id=3; Lahat Meron; owner users.id='.$seller->user_id);
            $this->line('Database confirmation matched; environment='.app()->environment());
            if ($this->postgresIdentity) $this->line('Verified PostgreSQL project: '.DemoImportDatabase::PROJECT.'; read-only preflight completed.');
            $this->line('Category: Others > Miscellaneous; category_id='.$category->id);
            $this->line('Pack: 60 products / 102 variants; 36 simple, 12 two-option, 6 three-option, 6 four-option.');
            $this->table(['Key','Action','Product','Variants','Price minor range','Stock total'], array_map(fn ($row) => [
                $row['key'],$plan[$row['key']],$row['data']['name'],count($row['skus']),min($row['prices']).'..'.max($row['prices']),array_sum($row['stocks']),
            ], $rows));
            $this->line('60 published demo images validated. New products remain PENDING; normal moderation events and notification intents are preserved.');
            $this->line('Plan fingerprint: '.$fingerprint);
            if (!$this->option('execute')) {
                $insert = count(array_filter($plan, fn ($a) => $a === 'INSERT'));
                $this->info('DRY RUN: no records or media created or changed. Proposed inserts: '.$insert.'; existing demo items skipped: '.(60-$insert).'.');
                return self::SUCCESS;
            }
            $ownerId = (string)$this->option('expect-owner-id');
            $this->require(ctype_digit($ownerId) && (int)$ownerId === (int)$seller->user_id, 'Execution requires --expect-owner-id matching the reviewed owner.');
            $this->require(!$this->postgresIdentity || hash_equals($fingerprint,(string)$this->option('confirm-plan')), 'The reviewed PostgreSQL plan changed. Run and review a fresh dry-run.');
            $created = $variants = $skipped = 0;
            DB::transaction(function () use ($batch,$categories,$catalog,$guard,$ownerId,$fingerprint,&$created,&$variants,&$skipped) {
                if ($this->postgresIdentity) {
                    DB::statement("SET LOCAL lock_timeout = '5000ms'");
                    DB::statement("SET LOCAL statement_timeout = '30000ms'");
                    $guard->verifyServer(DB::connection(),$this->postgresIdentity,false);
                }
                [$seller,$category] = $batch->target($categories,true,$ownerId);
                $rows = $batch->dataset($category->id);
                app(DemoProductImages::class)->published(array_column($rows,'key'));
                $plan = $batch->preflight($seller,$rows);
                $this->require(!$this->postgresIdentity || hash_equals($fingerprint,$batch->importFingerprint($seller,$category,$plan,$this->postgresIdentity)), 'Preflight changed while acquiring locks. Review a fresh dry-run.');
                foreach ($rows as $row) {
                    if ($plan[$row['key']] !== 'INSERT') { $skipped++; continue; }
                    $product = $catalog->create($seller,$row['data']);
                    $this->require($product->slug === \Illuminate\Support\Str::slug($row['data']['name']), 'A demo slug changed during creation; batch rolled back. Review a fresh dry-run.');
                    $product->images()->create(['path'=>DemoProductImages::path($row['key']),'position'=>1]);
                    $product->update(['legacy_reference'=>$row['reference']]);
                    $this->require($product->variants->sortBy('id')->pluck('price_minor')->values()->all() === $row['prices'], 'Price conversion failed; batch rolled back.');
                    $created++; $variants += $product->variants->count();
                    $this->line('Prepared '.$created.': '.$row['key'].' (not committed yet)');
                }
            });
            $this->info("COMMITTED: {$created} products inserted; {$skipped} existing demo products skipped. No existing products updated or deleted.");
            $this->info("Variant records inserted: {$variants}.");
            $this->info("Primary images inserted: {$created}; no runtime media files copied.");
            return self::SUCCESS;
        } catch (ValidationException $error) {
            $message = $error->errors()['demo_database'][0] ?? $error->errors()['demo_batch'][0] ?? 'Demo dataset validation failed. No batch changes committed.';
            $this->error($message);
        } catch (RuntimeException $error) {
            $this->error($error instanceof DemoImportGuard || $error instanceof DemoProductImageException ? $error->getMessage() : 'Import failed. Inspect isolated/application diagnostics before retrying.');
        } catch (Throwable) {
            $this->error('Import failed. No automatic retry or data repair was attempted.');
        } finally {
            DB::setDefaultConnection($previous);
        }
        return self::FAILURE;
    }

    private function safeConnection(DemoImportDatabase $guard): string
    {
        $this->postgresIdentity = null;
        $this->require((string)$this->option('seller-id') === '3' && $this->option('expect-store') === 'Lahat Meron', 'Explicit target must be --seller-id=3 --expect-store="Lahat Meron".');
        $name = (string)$this->option('connection');
        $config = config('database.connections.'.$name);
        $this->require($name !== '' && is_array($config), 'An explicit configured connection is required.');
        $environment = app()->environment();
        $this->require($this->option('expect-environment') === $environment, 'Explicit environment confirmation is required.');
        if (($config['driver'] ?? null) === 'pgsql') {
            $this->postgresIdentity = $guard->configuration($config,[
                'project'=>$this->option('expect-project'),'owner'=>$this->option('expect-owner-id'),
                'database'=>$this->option('confirm-database'),'execute'=>$this->option('execute'),
                'confirmation'=>$this->option('confirm-execution'),
                'plan'=>$this->option('confirm-plan'),
            ]);
            return $name;
        }
        $this->require(in_array($environment,['local','testing'],true), 'SQLite requires the local/testing environment.');
        $this->require(($config['driver'] ?? null) === 'sqlite', 'Only explicit SQLite or guarded PostgreSQL connections are supported.');
        $this->require(empty($config['url']) && empty($config['read']) && empty($config['write']), 'Connection URLs and split read/write SQLite connections are refused.');
        $database = $config['database'] ?? null;
        $this->require(is_string($database) && $database !== '', 'SQLite database must be explicitly configured.');
        if ($database === ':memory:') {
            $this->require($environment === 'testing' && $this->option('confirm-database') === ':memory:', 'In-memory SQLite is allowed only in testing with explicit confirmation.');
        } else {
            $path=realpath($database);
            $this->require($path !== false && is_file($path) && $this->option('confirm-database') === $path, 'Confirm the exact real path of an existing SQLite database; new databases and URI paths are refused.');
        }
        return $name;
    }

    private function require(mixed $condition, string $message): void
    {
        if (!$condition) throw new DemoImportGuard($message);
    }
}

class DemoImportGuard extends RuntimeException {}
