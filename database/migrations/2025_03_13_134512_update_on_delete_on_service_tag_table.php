<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('service_tag', function (Blueprint $table) {
            DB::statement('ALTER TABLE service_tag DROP FOREIGN KEY IF EXISTS service_tag_service_id_foreign');
            DB::statement('ALTER TABLE service_tag DROP FOREIGN KEY IF EXISTS service_tag_tag_id_foreign');

            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_tag', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropForeign(['tag_id']);

            $table->foreign('service_id')->references('id')->on('services')->onDelete('restrict');
            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('restrict');
        });
    }
};
