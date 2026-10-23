<?php

use App\Models\Product;
use App\Support\SellerProductSku;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Only used by SellerProductSkuTest against its dedicated temporary SQLite file.
require __DIR__.'/../../vendor/autoload.php';
$database = $argv[1] ?? '';
$gate = $argv[2] ?? '';
$ready = $argv[3] ?? '';
$additional = ($argv[4] ?? '') === 'additional';
if (basename($database) !== 'sku.sqlite' || ! str_starts_with(realpath(dirname($database)) ?: '', realpath(sys_get_temp_dir()))) {
    throw new RuntimeException('An isolated temporary SKU database is required.');
}
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'sku_race', 'database.connections.sku_race' => [
    'driver' => 'sqlite', 'database' => $database, 'foreign_key_constraints' => true, 'busy_timeout' => 5000,
]]);
file_put_contents($ready, 'ready');
$deadline = microtime(true) + 10;
while (! is_file($gate)) {
    clearstatcache(true, $gate);
    if (microtime(true) > $deadline) {
        throw new RuntimeException('SKU test start gate timed out.');
    }
    usleep(10000);
}
$sku = DB::transaction(function () use ($additional) {
    $id = DB::table('products')->insertGetId(['seller_id' => 123]);
    $product = new Product(['seller_id' => 123]);
    $product->id = $id;
    $product->exists = true;

    return SellerProductSku::createVariant($product, [
        'name' => 'Concurrent fixture', 'price_minor' => 100, 'stock' => 1, 'is_active' => true,
    ], $additional)->sku;
}, 3);
echo $sku."\n";
