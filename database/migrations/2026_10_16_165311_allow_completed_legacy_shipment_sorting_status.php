<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE shipments
            DROP CONSTRAINT IF EXISTS shipments_sorting_status_check
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE shipments
            ADD CONSTRAINT shipments_sorting_status_check
            CHECK (
                sorting_status IN (
                    'pending',
                    'received',
                    'scanned',
                    'sorted',
                    'staged',
                    'completed'
                )
            )
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Do not silently invalidate historical completed shipments.
        $completedExists = DB::table('shipments')
            ->where('sorting_status', 'completed')
            ->exists();

        if ($completedExists) {
            throw new RuntimeException(
                'Cannot roll back: shipments with completed sorting status exist.'
            );
        }

        DB::statement(<<<'SQL'
            ALTER TABLE shipments
            DROP CONSTRAINT IF EXISTS shipments_sorting_status_check
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE shipments
            ADD CONSTRAINT shipments_sorting_status_check
            CHECK (
                sorting_status IN (
                    'pending',
                    'received',
                    'scanned',
                    'sorted',
                    'staged'
                )
            )
        SQL);
    }
};
