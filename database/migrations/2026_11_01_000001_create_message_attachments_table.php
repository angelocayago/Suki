<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('disk', 50);
            $table->string('path', 255);
            $table->string('original_name', 255);
            $table->enum('mime_type', ['image/jpeg','image/png','image/webp']);
            $table->unsignedInteger('size');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->timestamps();
            $table->unique(['message_id','position']);
            $table->unique(['disk','path']);
        });

        $valid='NEW.position BETWEEN 0 AND 4 AND NEW.width > 0 AND NEW.height > 0 AND NEW.size BETWEEN 1 AND 5242880';
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite cannot add table CHECK constraints after CREATE TABLE.
            foreach (['INSERT','UPDATE'] as $operation) {
                DB::statement('CREATE TRIGGER message_attachment_bounds_'.strtolower($operation).' BEFORE '.$operation.' ON message_attachments WHEN NOT ('.$valid.") BEGIN SELECT RAISE(ABORT, 'Invalid attachment bounds'); END");
            }
        } else {
            DB::statement('ALTER TABLE message_attachments ADD CONSTRAINT message_attachment_bounds CHECK (position BETWEEN 0 AND 4 AND width > 0 AND height > 0 AND size BETWEEN 1 AND 5242880)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
    }
};
