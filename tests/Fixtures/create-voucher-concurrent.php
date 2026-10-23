<?php

// Standalone workers exercise the actual SQLite migration's unique index.
[$script, $database, $gate, $ready, $code] = $argv;
$pdo = new PDO('sqlite:'.$database, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA busy_timeout=5000');
file_put_contents($ready, 'ready');
$deadline = microtime(true) + 10;
while (! file_exists($gate)) {
    if (microtime(true) > $deadline) {
        exit(2);
    }
    usleep(10000);
}
try {
    $pdo->prepare("INSERT INTO seller_vouchers (seller_id,name,code,discount_type,discount_bps,product_scope,starts_at,ends_at,per_buyer_limit,status) VALUES (1,'Race',?,'percentage',1000,'all','2026-10-23 04:00:00','2026-10-30 04:00:00',1,'paused')")->execute([$code]);
    echo 'created';
} catch (PDOException $exception) {
    if ($exception->getCode() !== '23000') {
        throw $exception;
    }
    echo 'collision';
}
