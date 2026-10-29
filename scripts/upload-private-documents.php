<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Storage;

$local = Storage::disk('public');
$private = Storage::disk('supabase_private');

$folders = [
    'customer-documents',
    'seller-documents',
    'rider-documents',
    'logistic-documents',
];

$uploaded = 0;
$skipped = 0;
$failed = 0;
$total = 0;

foreach ($folders as $folder) {
    echo "\nChecking {$folder}...\n";

    foreach ($local->allFiles($folder) as $path) {
        $total++;

        try {
            if ($private->exists($path)) {
                echo "SKIPPED: {$path}\n";
                $skipped++;
                continue;
            }

            $stream = $local->readStream($path);

            if ($stream === false) {
                throw new RuntimeException('Cannot read local file');
            }

            try {
                $result = $private->put($path, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if (!$result || !$private->exists($path)) {
                throw new RuntimeException('Upload verification failed');
            }

            echo "UPLOADED: {$path}\n";
            $uploaded++;
        } catch (Throwable $e) {
            echo "FAILED: {$path} — {$e->getMessage()}\n";
            $failed++;
        }
    }
}

echo "\nRESULT:\n";
echo "Uploaded: {$uploaded}\n";
echo "Skipped: {$skipped}\n";
echo "Failed: {$failed}\n";
echo "Total: {$total}\n";
