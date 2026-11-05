<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

abstract class IsolatedStorageTestCase extends BaseTestCase
{
    private ?string $isolatedStoragePath = null;

    /** @var array{env: mixed, server: mixed, process: string|false}|null */
    private ?array $previousStoragePath = null;

    protected function setUp(): void
    {
        $this->createIsolatedStoragePath();

        try {
            parent::setUp();
        } catch (\Throwable $exception) {
            $this->restoreStoragePathEnvironment();
            $this->removeIsolatedStoragePath();

            throw $exception;
        }
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            $this->restoreStoragePathEnvironment();
            $this->removeIsolatedStoragePath();
        }
    }

    protected function assertStoragePathIsIsolated(): void
    {
        $tempRoot = realpath(sys_get_temp_dir());
        $storageRoot = realpath(storage_path());

        $this->assertNotFalse($tempRoot, 'The system temporary directory must resolve to a real path.');
        $this->assertNotFalse($storageRoot, 'The isolated Laravel storage directory must exist.');

        $normalizedTempRoot = $this->normalizePath($tempRoot);
        $normalizedStorageRoot = $this->normalizePath($storageRoot);
        $expectedRoot = realpath((string) $this->isolatedStoragePath);
        $this->assertNotFalse($expectedRoot, 'The test-owned isolated storage directory must resolve to a real path.');
        $normalizedExpectedRoot = $this->normalizePath($expectedRoot);

        $this->assertSame($normalizedExpectedRoot, $normalizedStorageRoot, 'Laravel must use this test case’s unique storage root.');
        $this->assertStringStartsWith($normalizedTempRoot.'/', $normalizedStorageRoot, 'Isolated storage must be contained by the system temporary directory.');
    }

    private function createIsolatedStoragePath(): void
    {
        $tempRoot = sys_get_temp_dir();
        $realTempRoot = realpath($tempRoot);

        if ($realTempRoot === false || ! is_dir($realTempRoot)) {
            throw new RuntimeException('The system temporary directory is unavailable.');
        }

        // Build under the exact OS-provided temp path. realpath-based checks below
        // canonicalize Windows 8.3 aliases for containment and cleanup validation.
        $directory = rtrim($tempRoot, "\\/").DIRECTORY_SEPARATOR.'suki-laravel-test-'.bin2hex(random_bytes(16));

        if (! mkdir($directory, 0700) || ! is_dir($directory)) {
            throw new RuntimeException('Unable to create an isolated Laravel storage directory.');
        }

        $this->isolatedStoragePath = $directory;

        if (! mkdir($directory.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'views', 0700, true)) {
            $this->removeIsolatedStoragePath();
            throw new RuntimeException('Unable to create the isolated compiled-view cache directory.');
        }

        $this->previousStoragePath = [
            'env' => $_ENV['LARAVEL_STORAGE_PATH'] ?? null,
            'server' => $_SERVER['LARAVEL_STORAGE_PATH'] ?? null,
            'process' => getenv('LARAVEL_STORAGE_PATH'),
        ];

        $_ENV['LARAVEL_STORAGE_PATH'] = $directory;
        $_SERVER['LARAVEL_STORAGE_PATH'] = $directory;
        putenv('LARAVEL_STORAGE_PATH='.$directory);
    }

    private function restoreStoragePathEnvironment(): void
    {
        if ($this->previousStoragePath === null) {
            return;
        }

        if ($this->previousStoragePath['env'] === null) {
            unset($_ENV['LARAVEL_STORAGE_PATH']);
        } else {
            $_ENV['LARAVEL_STORAGE_PATH'] = $this->previousStoragePath['env'];
        }
        if ($this->previousStoragePath['server'] === null) {
            unset($_SERVER['LARAVEL_STORAGE_PATH']);
        } else {
            $_SERVER['LARAVEL_STORAGE_PATH'] = $this->previousStoragePath['server'];
        }

        if ($this->previousStoragePath['process'] === false) {
            putenv('LARAVEL_STORAGE_PATH');
        } else {
            putenv('LARAVEL_STORAGE_PATH='.$this->previousStoragePath['process']);
        }

        $this->previousStoragePath = null;
    }

    private function removeIsolatedStoragePath(): void
    {
        if ($this->isolatedStoragePath === null) {
            return;
        }

        $tempRoot = realpath(sys_get_temp_dir());
        $storageRoot = realpath($this->isolatedStoragePath);

        if ($tempRoot === false || $storageRoot === false || is_link($this->isolatedStoragePath)) {
            throw new RuntimeException('Refusing to remove an unresolved or linked isolated storage path.');
        }

        $normalizedTempRoot = $this->normalizePath($tempRoot);
        $normalizedStorageRoot = $this->normalizePath($storageRoot);
        $expectedParent = rtrim($normalizedTempRoot, '/').'/';

        if (! str_starts_with($normalizedStorageRoot, $expectedParent)
            || dirname($normalizedStorageRoot) !== $normalizedTempRoot
            || ! str_starts_with(basename($normalizedStorageRoot), 'suki-laravel-test-')) {
            throw new RuntimeException('Refusing to remove storage outside this test’s unique temporary directory.');
        }

        if (! (new Filesystem())->deleteDirectory($storageRoot) && is_dir($storageRoot)) {
            throw new RuntimeException('Unable to remove the isolated temporary storage directory.');
        }
        $this->isolatedStoragePath = null;
    }

    private function normalizePath(string $path): string
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');

        return PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;
    }
}
