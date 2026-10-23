<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('sellers')->restrictOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->unique(['buyer_id', 'seller_id']);
            $table->index(['seller_id', 'last_message_at', 'id']);
        });
        Schema::create('seller_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('seller_conversations')->restrictOnDelete();
            $table->enum('sender_role', ['buyer', 'seller']);
            $table->foreignId('sender_user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('submission_key');
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['conversation_id', 'sender_role', 'submission_key'], 'seller_message_submission_unique');
            $table->index(['conversation_id', 'created_at', 'id']);
            $table->index(['conversation_id', 'sender_role', 'read_at', 'id'], 'seller_messages_unread');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_messages');
        Schema::dropIfExists('seller_conversations');
    }
};
