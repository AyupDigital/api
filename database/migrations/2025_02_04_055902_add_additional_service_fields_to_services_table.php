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
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('national')->default(false)->after('fees_url');
            $table->enum('attending_type', ['phone', 'online', 'venue', 'home'])->default('venue')->after('national');
            $table->enum('attending_access', ['referral', 'appointment', 'membership', 'drop_in'])->default('drop_in')->after('attending_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('national');
            $table->dropColumn('attending_type');
            $table->dropColumn('attending_access');
        });
    }
};
