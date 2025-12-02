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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('device_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform'); // 'android'|'ios'|'web'
            $table->string('token')->nullable(); // FCM/APNs token or web push identifier
            $table->string('reverb_channel')->nullable();
            $table->json('capabilities')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('online')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
