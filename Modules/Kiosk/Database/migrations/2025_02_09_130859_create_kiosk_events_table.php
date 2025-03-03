<?php

declare(strict_types=1);

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
        Schema::create('kiosk_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('session_id')->index();
            $table->dateTime('date_time');
            $table->enum('type', ['start', 'end', 'error', 'event', 'feedback']);
            $table->string('device_name')->index();
            $table->json('data');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kiosk_events');
    }
};
