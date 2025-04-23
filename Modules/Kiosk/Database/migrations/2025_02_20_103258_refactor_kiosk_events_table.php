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
        Schema::dropIfExists('kiosk_events');

        Schema::create('kiosk_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('type', ['session_start', 'session_end', 'session_timeout', 'demographic', 'pageview', 'interaction', 'notification', 'feedback', 'error']);
            $table->string('name');
            $table->string('group');
            $table->json('value')->nullable();
            $table->uuid('kiosk_session_id');
            $table->foreign('kiosk_session_id')->references('id')->on('kiosk_sessions')->onDelete('cascade');
            $table->dateTime('logged_at');
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
