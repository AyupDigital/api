<?php

use App\Models\Organisation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('slug')->after('logo_file_id');
        });

        Organisation::query()->chunk(200, function (Collection $organisations) {
            $organisations->each(function (Organisation $organisation) {
                $iteration = 0;
                do {
                    $slug = $iteration === 0
                        ? Str::slug($organisation->name)
                        : Str::slug($organisation->name).'-'.$iteration;
                    $iteration++;
                } while (Organisation::query()->where('slug', $slug)->exists());

                $organisation->update(['slug' => $slug]);
            });
        });

        Schema::table('organisations', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
