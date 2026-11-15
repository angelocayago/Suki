<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CodRiderFinancialUniquenessTest extends TestCase
{
    private const KEYS = [
        ['rider_cod_remittances', 'shipment_id', 'rider_cod_remittances_shipment_id_unique'],
        ['rider_shipping_fee_earnings', 'seller_order_id', 'rider_shipping_fee_earnings_seller_order_id_unique'],
        ['rider_shipping_fee_earnings', 'shipment_id', 'rider_shipping_fee_earnings_shipment_id_unique'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // This suite creates its own small schema. Never use an application database.
        if (DB::getDriverName() === 'pgsql') {
            $id = DB::selectOne("SELECT current_database() AS db,current_user AS role,host(inet_server_addr()) AS host,inet_server_port() AS port,current_setting('data_directory') AS data");
            $data = str_replace('\\', '/', $id->data);
            if ($id->db !== 'suki_w5lb_unit' || $id->role !== 'suki_rr_test' || $id->host !== '127.0.0.1'
                || (int) $id->port !== 55439 || strcasecmp($data, 'C:/suki ulet/return-refund-pg-r5b/data') !== 0) {
                throw new \RuntimeException('Pinned disposable PostgreSQL unit database required.');
            }
        } elseif (DB::getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Disposable in-memory SQLite or pinned PostgreSQL required.');
        }
        DB::beginTransaction();
        foreach (['seller_orders', 'shipments', 'riders'] as $table) {
            Schema::create($table, fn (Blueprint $t) => $t->id());
            DB::table($table)->insert(array_map(fn ($id) => ['id' => $id], range(1, 5)));
        }
        Schema::create('rider_cod_remittances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shipment_id')->constrained()->unique(); // Original broken chain.
            $t->foreignId('seller_order_id')->constrained();
            $t->foreignId('rider_id')->constrained();
            $t->bigInteger('expected_minor');
            $t->string('status');
        });
        Schema::create('rider_shipping_fee_earnings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('seller_order_id')->constrained()->unique();
            $t->foreignId('shipment_id')->constrained()->unique();
            $t->foreignId('rider_id')->constrained();
            $t->bigInteger('amount_minor');
        });
        Schema::create('historical_financial_fixture', function (Blueprint $t) {
            $t->id(); $t->string('kind'); $t->bigInteger('amount_minor');
        });
        DB::table('historical_financial_fixture')->insert([
            ['kind' => 'refund', 'amount_minor' => 29900],
            ['kind' => 'seller_deficit', 'amount_minor' => -29900],
            ['kind' => 'paid_payout', 'amount_minor' => 5000],
        ]);
        $this->insert('rider_cod_remittances', 1, 1);
        $this->insert('rider_shipping_fee_earnings', 1, 1);
    }

    protected function tearDown(): void
    {
        try {
            while (DB::transactionLevel() > 0) DB::rollBack();
        } finally {
            parent::tearDown();
        }
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_11_15_000000_enforce_cod_rider_financial_uniqueness.php');
    }

    private function insert(string $table, int $sellerOrder, int $shipment): void
    {
        $row = ['seller_order_id' => $sellerOrder, 'shipment_id' => $shipment, 'rider_id' => 1];
        $row += $table === 'rider_cod_remittances' ? ['expected_minor' => 29900, 'status' => 'ACCEPTED'] : ['amount_minor' => 5000];
        DB::table($table)->insert($row);
    }

    private function snapshot(): array
    {
        $out = [];
        foreach (['rider_cod_remittances', 'rider_shipping_fee_earnings', 'historical_financial_fixture'] as $table) {
            $out[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        return $out;
    }

    private function assertNoRepairIndexes(): void
    {
        foreach (self::KEYS as [$table, , $name]) {
            $this->assertNotContains($name, array_column(Schema::getIndexes($table), 'name'));
        }
    }

    private function refused(callable $operation, string $message): void
    {
        try {
            $operation();
            $this->fail('Expected fail-closed refusal.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    public function test_original_chain_omits_indexes_and_additive_repair_preserves_rows_and_foreign_keys(): void
    {
        $this->assertNoRepairIndexes();
        $rows = $this->snapshot();
        $fks = [Schema::getForeignKeys('rider_cod_remittances'), Schema::getForeignKeys('rider_shipping_fee_earnings')];
        $this->migration()->up();
        foreach (self::KEYS as [$table, $column, $name]) {
            $index = collect(Schema::getIndexes($table))->firstWhere('name', $name);
            $this->assertTrue($index['unique']); $this->assertSame([$column], $index['columns']);
        }
        $this->assertSame($rows, $this->snapshot());
        $this->assertSame($fks, [Schema::getForeignKeys('rider_cod_remittances'), Schema::getForeignKeys('rider_shipping_fee_earnings')]);
        $this->insert('rider_cod_remittances', 2, 2);
        $this->insert('rider_shipping_fee_earnings', 2, 2);
        $this->assertSame(2, DB::table('rider_cod_remittances')->count());
        $this->assertSame(2, DB::table('rider_shipping_fee_earnings')->count());
    }

    public static function identities(): array
    {
        return [['rider_cod_remittances', 2, 1], ['rider_shipping_fee_earnings', 1, 2], ['rider_shipping_fee_earnings', 2, 1]];
    }

    #[DataProvider('identities')]
    public function test_duplicate_identity_is_rejected_after_repair(string $table, int $sellerOrder, int $shipment): void
    {
        $this->migration()->up(); $before = $this->snapshot();
        try {
            DB::transaction(fn () => $this->insert($table, $sellerOrder, $shipment));
            $this->fail('Duplicate identity was accepted.');
        } catch (QueryException $e) {
            $this->assertContains($e->errorInfo[0], ['23505', '23000']);
        }
        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('identities')]
    public function test_historical_duplicates_refuse_all_three_indexes_without_rewriting_data(string $table, int $sellerOrder, int $shipment): void
    {
        $this->insert($table, $sellerOrder, $shipment); $before = $this->snapshot();
        $this->refused(fn () => $this->migration()->up(), 'Conflicting COD/Rider identities');
        $this->assertNoRepairIndexes(); $this->assertSame($before, $this->snapshot());
    }

    public static function names(): array { return array_map(fn ($key) => [$key[2]], self::KEYS); }

    #[DataProvider('names')]
    public function test_unexpected_name_collision_refuses_without_partial_changes(string $name): void
    {
        DB::statement('CREATE INDEX "'.$name.'" ON rider_cod_remittances (expected_minor)');
        $before = Schema::getIndexes('rider_cod_remittances'); $rows = $this->snapshot();
        $this->refused(fn () => $this->migration()->up(), 'name collision');
        $this->assertSame($before, Schema::getIndexes('rider_cod_remittances'));
        $this->assertSame($rows, $this->snapshot());
        $this->assertCount(1, Schema::getIndexes('rider_shipping_fee_earnings'));
    }

    public function test_alternative_preexisting_identity_is_not_silently_adopted(): void
    {
        DB::statement('CREATE UNIQUE INDEX independently_managed_identity ON rider_cod_remittances (shipment_id)');
        $this->refused(fn () => $this->migration()->up(), 'requires review');
        $this->assertNoRepairIndexes();
    }

    public function test_failure_during_third_index_creation_rolls_back_the_first_two(): void
    {
        $before = $this->snapshot(); $inject = true;
        DB::listen(function ($event) use (&$inject) {
            if ($inject && str_starts_with($event->sql, 'CREATE UNIQUE INDEX')
                && str_contains($event->sql, self::KEYS[2][2])) {
                $inject = false; throw new \RuntimeException('Injected index-build failure');
            }
        });
        $this->refused(fn () => $this->migration()->up(), 'Injected index-build failure');
        $this->assertFalse($inject); $this->assertNoRepairIndexes(); $this->assertSame($before, $this->snapshot());
    }

    public function test_foreign_key_rejection_and_destructive_rollback_refusal_are_preserved(): void
    {
        $this->migration()->up();
        try {
            DB::transaction(fn () => $this->insert('rider_cod_remittances', 2, 999));
            $this->fail('Orphan shipment was accepted.');
        } catch (QueryException $e) {
            $this->assertContains($e->errorInfo[0], ['23503', '23000']);
        }
        $before = $this->snapshot();
        $this->refused(fn () => $this->migration()->down(), 'Retain COD/Rider');
        $this->assertSame($before, $this->snapshot());
        foreach (self::KEYS as [$table, , $name]) $this->assertContains($name, array_column(Schema::getIndexes($table), 'name'));
    }
}
