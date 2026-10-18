<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('oauth_provider', 32)->nullable()->after('email')->index();
            $table->string('oauth_provider_user_id', 191)->nullable()->after('oauth_provider');
            $table->unique(['oauth_provider', 'oauth_provider_user_id'], 'users_oauth_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_oauth_identity_unique');
            $table->dropColumn(['oauth_provider', 'oauth_provider_user_id']);
        });
    }
};
