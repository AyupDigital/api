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
        Schema::create('kiosk_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->integer('duration');
            $table->string('device_id');
            $table->enum('status', ['complete', 'incomplete'])->default('incomplete');
            $table->boolean('has_demographic')->default(false);
            $table->boolean('has_shared_shortlist')->default(false);
            $table->boolean('has_feedback')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kiosk_sessions');
    }
};
