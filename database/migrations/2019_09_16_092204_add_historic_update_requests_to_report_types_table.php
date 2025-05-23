<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = Date::now();

        DB::table('report_types')->insert([
            'id' => uuid(),
            'name' => 'Historic Update Requests Export',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $reportType = DB::table('report_types')
            ->where('name', '=', 'Historic Update Requests Export')
            ->first();

        $fileIds = DB::table('reports')
            ->where('report_type_id', '=', $reportType->id)
            ->pluck('file_id')
            ->toArray();

        DB::table('files')
            ->whereIn('id', $fileIds)
            ->delete();

        DB::table('reports')
            ->where('report_type_id', '=', $reportType->id)
            ->delete();

        DB::table('report_types')
            ->where('name', '=', 'Historic Update Requests Export')
            ->delete();
    }
};
