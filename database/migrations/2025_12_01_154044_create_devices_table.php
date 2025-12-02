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
            $table->uuid('device_id')->unique(); // client-provided or generated
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('platform'); // 'android'|'ios'|'web'
            $table->string('token')->nullable(); // FCM token or APNs token or web push endpoint id
            $table->string('reverb_channel')->nullable(); // e.g. "devices.{device_id}"
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
