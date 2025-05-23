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
        Schema::create('update_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuidKeyColumn('user_id', 'users');
            $table->uuidMorphs('updateable');
            $table->json('data');
            $table->timestamps();
            $table->timestamp('approved_at')->nullable();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('update_requests');
    }
};
