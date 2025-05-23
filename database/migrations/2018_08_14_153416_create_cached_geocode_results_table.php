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
        Schema::create('cached_geocode_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('query')->unique();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lon', 9, 6)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cached_geocode_results');
    }
};
