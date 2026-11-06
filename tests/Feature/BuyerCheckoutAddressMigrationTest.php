<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\Support\IsolatedStorageTestCase;

class BuyerCheckoutAddressMigrationTest extends IsolatedStorageTestCase
{
    public function test_nullable_migration_preserves_rows_indexes_relations_and_null_provinces(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) throw new \RuntimeException('SQLite memory required.');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertStoragePathIsIsolated();
        // No test transaction: SQLite column changes rebuild tables and temporarily
        // disable foreign keys, just as Laravel's normal migration runner requires.
        Schema::create('orders', function (Blueprint $table) {
            $table->id(); $table->string('order_number')->unique(); $table->string('province');
        });
        Schema::create('address_test_children', function (Blueprint $table) {
            $table->id(); $table->foreignId('order_id')->constrained('orders');
        });
        DB::table('orders')->insert(['id' => 1, 'order_number' => 'OLD', 'province' => 'Province A']);
        DB::table('address_test_children')->insert(['id' => 1, 'order_id' => 1]);
        $migration = require base_path('database/migrations/2026_11_07_000001_make_order_province_nullable.php');
        $migration->up();
        $this->assertSame('Province A', DB::table('orders')->where('id', 1)->value('province'));
        $this->assertSame(1, DB::table('address_test_children')->value('order_id'));
        $this->assertTrue(collect(Schema::getIndexes('orders'))->contains(fn ($index) => $index['unique'] && $index['columns'] === ['order_number']));
        DB::table('orders')->insert(['id' => 2, 'order_number' => 'NCR', 'province' => null]);
        try { $migration->down(); $this->fail('Rollback must not invent provinces.'); }
        catch (\RuntimeException $exception) { $this->assertStringContainsString('province-less orders exist', $exception->getMessage()); }
        $this->assertNull(DB::table('orders')->where('id', 2)->value('province'));
        DB::table('orders')->where('id', 2)->delete();
        $migration->down();
        $this->assertFalse(collect(Schema::getColumns('orders'))->firstWhere('name', 'province')['nullable']);
        $migration->up();
        $this->assertTrue(collect(Schema::getColumns('orders'))->firstWhere('name', 'province')['nullable']);
        $this->assertEmpty(DB::select('PRAGMA foreign_key_check'));
    }
}
