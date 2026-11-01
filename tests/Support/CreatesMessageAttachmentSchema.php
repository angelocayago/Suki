<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

trait CreatesMessageAttachmentSchema
{
    protected function createAttachmentTable(): void
    {
        $this->assertSame('sqlite',config('database.default'));
        $this->assertSame(':memory:',DB::connection()->getDatabaseName());
        $this->assertEmpty(config('database.connections.sqlite.url'));
        DB::statement("CREATE TABLE message_attachments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            message_id INTEGER NOT NULL REFERENCES messages(id) ON DELETE CASCADE,
            position INTEGER NOT NULL CHECK(position BETWEEN 0 AND 4),
            disk VARCHAR(50) NOT NULL, path VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(255) NOT NULL CHECK(mime_type IN ('image/jpeg','image/png','image/webp')),
            size INTEGER NOT NULL CHECK(size BETWEEN 1 AND 5242880),
            width INTEGER NOT NULL CHECK(width > 0), height INTEGER NOT NULL CHECK(height > 0),
            created_at TEXT, updated_at TEXT, UNIQUE(message_id, position), UNIQUE(disk, path)
        )");
    }
}
