<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('settings')
            ->where('key', '=', 'cms')
            ->update([
                'value' => DB::raw(
                    <<<'EOT'
JSON_INSERT(
    `settings`.`value`,
    '$.frontend.cookie_policy',
    JSON_OBJECT(
        "title", 'Title',
        "content", 'Content'
    )
)
EOT
                ),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')
            ->where('key', '=', 'cms')
            ->update([
                'value' => DB::raw(
                    <<<'EOT'
JSON_REMOVE(
    `settings`.`value`,
    '$.frontend.cookie_policy'
)
EOT
                ),
            ]);
    }
};
