<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shopping_assistant_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_token', 80)->nullable();
            $table->timestamps();
            $table->unique(['user_id']);
            $table->unique(['guest_token']);
        });
        Schema::create('shopping_assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shopping_assistant_conversation_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant']);
            $table->string('source', 20)->nullable();
            $table->string('body', 1200);
            $table->timestamps();
            $table->index(['shopping_assistant_conversation_id', 'id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('shopping_assistant_messages');
        Schema::dropIfExists('shopping_assistant_conversations');
    }
};
