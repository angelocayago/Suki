<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('users') ||
            ! Schema::hasTable('roles') ||
            ! Schema::hasTable('role_user')
        ) {
            return;
        }

        DB::transaction(function () {

            /*
             * Get all legacy roles currently used by users.
             *
             * Example:
             * admin
             * buyer
             */
            $legacyRoles = DB::table('users')
                ->whereNotNull('role')
                ->where('role', '!=', '')
                ->distinct()
                ->pluck('role');

            /*
             * Ensure every legacy role exists
             * in the new roles table.
             */
            foreach ($legacyRoles as $roleName) {
                DB::table('roles')->insertOrIgnore([
                    'name' => $roleName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            /*
             * Copy each user's legacy role
             * into the role_user pivot table.
             */
            DB::table('users')
                ->whereNotNull('role')
                ->where('role', '!=', '')
                ->orderBy('id')
                ->chunkById(100, function ($users) {

                    foreach ($users as $user) {

                        $roleId = DB::table('roles')
                            ->where('name', $user->role)
                            ->value('id');

                        if (! $roleId) {
                            continue;
                        }

                        DB::table('role_user')->insertOrIgnore([
                            'role_id' => $roleId,
                            'user_id' => $user->id,
                        ]);
                    }
                });
        });
    }

    public function down(): void
    {
        /*
         * Intentionally left non-destructive.
         *
         * This migration copies legacy role data
         * into the new role system. Removing those
         * assignments automatically during rollback
         * could delete valid role assignments created
         * after the migration.
         *
         * The legacy users.role column remains intact.
         */
    }
};