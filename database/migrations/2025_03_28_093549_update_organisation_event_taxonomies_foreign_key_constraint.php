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
        Schema::table('organisation_event_taxonomies', function (Blueprint $table) {
            $table->dropForeign(['organisation_event_id']);
            $table->foreign('organisation_event_id')
                ->references('id')
                ->on('organisation_events')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organisation_event_taxonomies', function (Blueprint $table) {
            $table->dropForeign(['organisation_event_id']);
            $table->foreign('organisation_event_id')
                ->references('id')
                ->on('organisation_events');
        });
    }
};
