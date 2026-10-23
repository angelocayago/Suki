<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ChatImageWrites
{
    private array $pending = [];

    public function track(string $path): void
    {
        $connection = DB::connection();
        $this->pending[] = ['connection' => $connection, 'level' => $connection->transactionLevel(), 'path' => $path];
    }

    public function committed(Connection $connection): void
    {
        foreach ($this->pending as $key => &$write) {
            if ($write['connection'] !== $connection) {
                continue;
            }
            if ($connection->transactionLevel() === 0) {
                unset($this->pending[$key]);
            } else {
                $write['level'] = min($write['level'], $connection->transactionLevel());
            }
        }
    }

    public function rolledBack(Connection $connection): void
    {
        foreach ($this->pending as $key => $write) {
            if ($write['connection'] !== $connection || $write['level'] <= $connection->transactionLevel()) {
                continue;
            }
            try {
                Storage::disk('chat_images')->delete($write['path']);
            } catch (\Throwable $exception) {
                report($exception);
            }
            unset($this->pending[$key]);
        }
    }
}
