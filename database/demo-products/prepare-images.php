<?php

// Offline asset preparation only. Does not boot Laravel or access any database.
// Input: local source inventory produced by image generation, not product media.
if (PHP_SAPI !== 'cli') exit(1);
if (! function_exists('imagewebp')) throw new RuntimeException('GD with WebP support is required.');
$sourceFile = $argv[1] ?? '';
if (! is_file($sourceFile)) throw new RuntimeException('Provide the local generated-image source inventory JSON.');
$sources = json_decode(ltrim(file_get_contents($sourceFile), "\xEF\xBB\xBF"), true, flags: JSON_THROW_ON_ERROR);
$dataset = json_decode(file_get_contents(__DIR__.'/lahat-meron-v1.json'), true, flags: JSON_THROW_ON_ERROR);
$byKey = [];
foreach ($sources as $source) {
    $key = $source['key'] ?? '';
    if (! preg_match('/^lm[0-9]{3}$/', $key) || isset($byKey[$key])) throw new RuntimeException('Invalid or duplicate generated key.');
    $byKey[$key] = $source;
}
if (count($byKey) !== 60) throw new RuntimeException('All 60 generated sources are required before preparing assets.');
foreach ($dataset as $item) {
    $source = $byKey[$item['key']] ?? null;
    if (! $source || $source['name'] !== $item['name'] || $source['description'] !== $item['description']
        || ! is_file($source['source']) || ! @getimagesize($source['source'])) {
        throw new RuntimeException('Missing, undecodable or mismatched generated source for '.$item['key']);
    }
    $decoded = @imagecreatefromstring(file_get_contents($source['source']));
    if (! $decoded) throw new RuntimeException('Undecodable generated source for '.$item['key']);
    unset($decoded);
}
$directory = __DIR__.'/images';
if (! is_dir($directory)) mkdir($directory, 0755, true);
$manifest = [];
foreach ($dataset as $item) {
    $key = $item['key'];
    $image = @imagecreatefromstring(file_get_contents($byKey[$key]['source']));
    if (! $image) throw new RuntimeException('Cannot decode generated source for '.$key);
    $out = imagecreatetruecolor(640, 640);
    imagefill($out, 0, 0, imagecolorallocate($out, 250, 246, 237));
    $scale = min(640 / imagesx($image), 640 / imagesy($image));
    $w = (int) round(imagesx($image) * $scale); $h = (int) round(imagesy($image) * $scale);
    imagecopyresampled($out, $image, intdiv(640 - $w, 2), intdiv(640 - $h, 2), 0, 0, $w, $h, imagesx($image), imagesy($image));
    if (! imagewebp($out, $directory.'/'.$key.'.webp', 82)) throw new RuntimeException('Cannot save '.$key);
    unset($image, $out);
    $manifest[$key] = [
        'file' => $key.'.webp', 'product' => $item['name'], 'width' => 640, 'height' => 640,
        'sha256' => hash_file('sha256', $directory.'/'.$key.'.webp'),
        'source_method' => 'Original unbranded product mockup generated with OpenAI image generation',
    ];
}
file_put_contents($directory.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
echo 'Prepared 60 local 640x640 WebP assets and manifest; '.array_sum(array_map('filesize', glob($directory.'/*.webp')))." bytes.\n";
