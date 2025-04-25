<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $record = DB::table('settings')->where('key', 'cms')->first();

        if ($record) {
            $data = json_decode($record->value, true);

            if (isset($data['frontend']['global'])) {
                $global = $data['frontend']['global'];

                $socialMedias = [];
                if (!empty($global['facebook_handle'])) {
                    $socialMedias[] = [
                        'type' => 'facebook',
                        'url' => $global['facebook_handle'],
                    ];
                }
                if (!empty($global['twitter_handle'])) {
                    $socialMedias[] = [
                        'type' => 'twitter',
                        'url' => $global['twitter_handle'],
                    ];
                }

                unset($global['facebook_handle'], $global['twitter_handle']);
                $global['social_medias'] = $socialMedias;

                $data['frontend']['global'] = $global;

                DB::table('settings')->where('key', 'cms')->update([
                    'value' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $record = DB::table('settings')->where('key', 'cms')->first();

        if ($record) {
            $data = json_decode($record->value, true);

            if (isset($data['frontend']['global'])) {
                $global = $data['frontend']['global'];

                if (isset($global['social_medias'])) {
                    foreach ($global['social_medias'] as $socialMedia) {
                        if ($socialMedia['type'] === 'facebook') {
                            $global['facebook_handle'] = $socialMedia['url'];
                        }
                        if ($socialMedia['type'] === 'twitter') {
                            $global['twitter_handle'] = $socialMedia['url'];
                        }
                    }
                }

                unset($global['social_medias']);

                $data['frontend']['global'] = $global;

                DB::table('settings')->where('key', 'cms')->update([
                    'value' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                ]);
            }
        }
    }
};
