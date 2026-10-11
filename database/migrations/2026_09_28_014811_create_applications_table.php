<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {

            $table->id();


            /*
            |--------------------------------------------------------------------------
            | Applicant User
            |--------------------------------------------------------------------------
            */

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();



            /*
            |--------------------------------------------------------------------------
            | Application Type
            |--------------------------------------------------------------------------
            */

            $table->enum('application_type', [
                'buyer',
                'seller',
                'courier',
                'logistics'
            ]);



            /*
            |--------------------------------------------------------------------------
            | Application Status
            |--------------------------------------------------------------------------
            */

            $table->enum('status', [
                'pending',
                'approved',
                'rejected'
            ])
            ->default('pending');



            /*
            |--------------------------------------------------------------------------
            | Review Information
            |--------------------------------------------------------------------------
            */

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();



            $table->timestamp('reviewed_at')
                ->nullable();



            $table->text('rejection_reason')
                ->nullable();



            $table->timestamps();

        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};