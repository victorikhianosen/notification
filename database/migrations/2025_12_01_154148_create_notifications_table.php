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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('notification_id')->unique();
            $table->foreignId('sender_id')->nullable()->constrained('users');
            $table->json('payload');
            $table->string('priority')->default('normal');
            $table->integer('ttl')->nullable();
            $table->enum('status', ['pending', 'processing', 'delivered', 'failed'])->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
