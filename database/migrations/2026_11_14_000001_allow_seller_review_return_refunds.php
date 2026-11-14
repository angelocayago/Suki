<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { $this->modes(['FINANCIAL','ADMIN_REVIEW','SELLER_REVIEW']); }

    public function down(): void
    {
        if (DB::table('return_refund_requests')->where('workflow_mode','SELLER_REVIEW')->exists()) {
            throw new RuntimeException('Retain the workflow discriminator while Seller-review requests exist.');
        }
        $this->modes(['FINANCIAL','ADMIN_REVIEW']);
    }

    private function modes(array $values): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // PostgreSQL implements Laravel enums as varchar with a named CHECK.
            // Replace it transactionally; existing values, defaults and identities stay intact.
            DB::statement('ALTER TABLE return_refund_requests DROP CONSTRAINT return_refund_requests_workflow_mode_check');
            $allowed = implode(',', array_map(fn($value)=>"'".$value."'",$values));
            DB::statement('ALTER TABLE return_refund_requests ADD CONSTRAINT return_refund_requests_workflow_mode_check CHECK (workflow_mode IN ('.$allowed.'))');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::transaction(function()use($values){
                $deferred=(int)DB::selectOne('PRAGMA defer_foreign_keys')->defer_foreign_keys;
                DB::statement('PRAGMA defer_foreign_keys = ON');
                Schema::table('return_refund_requests', fn(Blueprint $table)=>$table->enum('workflow_mode',$values)->default('FINANCIAL')->change());
                if (DB::select('PRAGMA foreign_key_check')) throw new RuntimeException('Return/refund foreign keys failed migration verification.');
                DB::statement('PRAGMA defer_foreign_keys = '.($deferred?'ON':'OFF'));
            });
        } else {
            throw new RuntimeException('Seller-review migration supports reviewed PostgreSQL and SQLite connections only.');
        }
    }
};
