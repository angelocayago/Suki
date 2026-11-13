<?php

namespace App\Console\Commands;

use App\Services\PresentationMarketplaceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class GeneratePresentationMarketplace extends Command
{
    protected $signature = 'presentation:marketplace-data
        {--connection= : Explicit local/testing SQLite connection}
        {--expect-environment= : Exact environment confirmation}
        {--confirm-database= : Exact SQLite :memory: value or real database path}
        {--sellers=6 : Between one and six fictional sellers}
        {--products-per-seller=4 : Between one and four products per seller}
        {--without-history : Do not create workflow-consistent presentation orders}
        {--execute : Write the reviewed presentation plan}
        {--confirm-execution= : Required exact phrase for writes}';

    protected $description = 'Preview or explicitly generate fictional, internally consistent local presentation data';

    public function handle(PresentationMarketplaceService $presentation): int
    {
        try {
            $connection = $this->safeConnection();
            $previous = DB::getDefaultConnection();
            DB::setDefaultConnection($connection);
            try {
                $sellers = $this->bounded('sellers', 1, 6);
                $products = $this->bounded('products-per-seller', 1, 4);
                $history = ! $this->option('without-history');
                $plan = $presentation->plan($sellers, $products, $history);
                $this->table(['Record type', 'Proposed count'], collect($plan)->map(fn ($count, $type) => [$type, $count])->all());
                $this->line('All people are fictional; customer-facing text contains no demo, sample, test, or placeholder labels.');
                $this->line('Products use reviewed local media and retain internal presentation provenance only.');
                if (! $this->option('execute')) {
                    $this->info('DRY RUN: no users, products, orders, reviews, media, or existing records were changed.');
                    return self::SUCCESS;
                }
                if ($this->option('confirm-execution') !== 'GENERATE FICTIONAL PRESENTATION DATA') {
                    throw ValidationException::withMessages(['presentation' => 'Execution requires --confirm-execution="GENERATE FICTIONAL PRESENTATION DATA".']);
                }
                $result = $presentation->apply($sellers, $products, $history);
                $this->info('COMMITTED: fictional presentation data is ready. Existing records were not overwritten or deleted.');
                $this->line('Created or reused: '.json_encode($result, JSON_THROW_ON_ERROR));
                return self::SUCCESS;
            } finally {
                DB::setDefaultConnection($previous);
            }
        } catch (ValidationException $exception) {
            $this->error($exception->errors()['presentation'][0] ?? $exception->errors()['media'][0] ?? $exception->errors()['categories'][0] ?? 'Presentation data validation failed.');
        } catch (Throwable $error) {
            report($error);
            $this->error('Presentation data was not generated. Review local diagnostics and retry from a fresh dry run.');
        }
        return self::FAILURE;
    }

    private function safeConnection(): string
    {
        $name = (string) $this->option('connection');
        $config = config('database.connections.'.$name);
        if ($name === '' || ! is_array($config) || ($config['driver'] ?? null) !== 'sqlite') {
            throw ValidationException::withMessages(['presentation' => 'An explicit SQLite connection is required. PostgreSQL and shared targets are refused.']);
        }
        if (! in_array(app()->environment(), ['local', 'testing', 'staging'], true) || $this->option('expect-environment') !== app()->environment()) {
            throw ValidationException::withMessages(['presentation' => 'A matching local, testing, or staging environment confirmation is required. Production is always refused.']);
        }
        if (! empty($config['url']) || ! empty($config['read']) || ! empty($config['write'])) {
            throw ValidationException::withMessages(['presentation' => 'Connection URLs and split connections are refused.']);
        }
        $database = $config['database'] ?? null;
        if (! is_string($database) || $database === '') throw ValidationException::withMessages(['presentation' => 'The SQLite database must be explicitly configured.']);
        if ($database === ':memory:') {
            if (app()->environment() !== 'testing' || $this->option('confirm-database') !== ':memory:') throw ValidationException::withMessages(['presentation' => 'In-memory SQLite requires testing and an explicit :memory: confirmation.']);
            return $name;
        }
        $path = realpath($database);
        if (! $path || ! is_file($path) || $this->option('confirm-database') !== $path) throw ValidationException::withMessages(['presentation' => 'Confirm the exact path of an existing local SQLite database.']);
        return $name;
    }

    private function bounded(string $option, int $min, int $max): int
    {
        $value = (string) $this->option($option);
        if (! ctype_digit($value) || (int) $value < $min || (int) $value > $max) throw ValidationException::withMessages(['presentation' => '--'.$option.' must be between '.$min.' and '.$max.'.']);
        return (int) $value;
    }
}
