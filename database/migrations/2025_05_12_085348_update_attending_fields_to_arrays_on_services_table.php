<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->json('attending_type_array')->after('national')->nullable();
            $table->json('attending_access_array')->after('attending_type_array')->nullable();
        });

        DB::table('services')->get()->each(function ($service) {
            DB::table('services')
                ->where('id', $service->id)
                ->update([
                    'attending_type_array' => json_encode([$service->attending_type]),
                    'attending_access_array' => json_encode([$service->attending_access]),
                ]);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('attending_type');
            $table->dropColumn('attending_access');

            $table->renameColumn('attending_type_array', 'attending_type');
            $table->renameColumn('attending_access_array', 'attending_access');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->renameColumn('attending_type', 'attending_type_array');
            $table->renameColumn('attending_access', 'attending_access_array');
            $table->enum('attending_type', ['phone', 'online', 'venue', 'home'])->default('venue');
            $table->enum('attending_access', ['referral', 'appointment', 'membership', 'drop_in'])->default('drop_in')->after('attending_type');
        });

        DB::table('services')->get()->each(function ($service) {
            DB::table('services')
                ->where('id', $service->id)
                ->update([
                    'attending_type' => json_decode($service->attending_type_array)[0] ?? null,
                    'attending_access' => json_decode($service->attending_access_array)[0] ?? null,
                ]);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('attending_type_array');
            $table->dropColumn('attending_access_array');
        });
    }
};
