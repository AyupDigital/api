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
        Schema::create('kiosk_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('type', ['sms', 'letter', 'email', 'print']);
            $table->json('service_ids');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->json('address')->nullable();
            $table->boolean('success')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kiosk_notifications');
    }
};
