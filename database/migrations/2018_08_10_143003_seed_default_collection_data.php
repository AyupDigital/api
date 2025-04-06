<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var Carbon\CarbonImmutable
     */
    protected $now;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->now = Date::now();
        $this->categoryTaxonomy = DB::table('taxonomies')
            ->whereNull('parent_id')
            ->where('name', 'Category')
            ->first();

        $this->seedCategoryCollections();
        $this->seedPersonaCollections();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('collection_taxonomies')->truncate();
        DB::table('collections')->truncate();
    }

    /**
     * Seed the category collections.
     */
    protected function seedCategoryCollections()
    {
        $this->seedLeisureCategory();
        $this->seedSelfHelpCategory();
        $this->seedAdviceCategory();
    }

    /**
     * Seed the Leisure and Social Activities category.
     */
    protected function seedLeisureCategory()
    {
        $uuid = uuid();
        DB::table('collections')->insert([
            'id' => $uuid,
            'type' => 'category',
            'name' => 'Leisure and Social Activities',
            'meta' => json_encode([
                'intro' => 'Lorem ipsum',
                'icon' => 'coffee',
            ]),
            'order' => 1,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
    }

    /**
     * Seed the Self Help category.
     */
    protected function seedSelfHelpCategory()
    {
        $uuid = uuid();
        DB::table('collections')->insert([
            'id' => $uuid,
            'type' => 'category',
            'name' => 'Self Help',
            'meta' => json_encode([
                'intro' => 'Lorem ipsum',
                'icon' => 'life-ring',
            ]),
            'order' => 2,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

    }

    /**
     * Seed the Advice and Support Services category.
     */
    protected function seedAdviceCategory()
    {
        $uuid = uuid();
        DB::table('collections')->insert([
            'id' => $uuid,
            'type' => 'category',
            'name' => 'Advice and Support Services',
            'meta' => json_encode([
                'intro' => 'Lorem ipsum',
                'icon' => 'info-circle',
            ]),
            'order' => 3,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
    }

    /**
     * Seed the persona collections.
     */
    protected function seedPersonaCollections()
    {
        $this->seedHomelessPersona();
        $this->seedRefugeesPersona();
        $this->seedSocialIsolationPersona();
    }

    /**
     * Seed the Homeless persona.
     */
    protected function seedHomelessPersona()
    {
        $uuid = uuid();
        DB::table('collections')->insert([
            'id' => $uuid,
            'type' => 'persona',
            'name' => 'Homeless',
            'meta' => json_encode([
                'intro' => 'Lorem ipsum',
                'subtitle' => 'Or at risk of homelessness',
                'image_file_id' => null,
            ]),
            'order' => 1,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

    }

    /**
     * Seed the Refugees persona.
     */
    protected function seedRefugeesPersona()
    {
        $uuid = uuid();
        DB::table('collections')->insert([
            'id' => $uuid,
            'type' => 'persona',
            'name' => 'Refugees',
            'meta' => json_encode([
                'intro' => 'Lorem ipsum',
                'subtitle' => 'Lorem ipsum',
                'image_file_id' => null,
            ]),
            'order' => 2,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
    }

    /**
     * Seed the Social Isolation persona.
     */
    protected function seedSocialIsolationPersona()
    {
        $uuid = uuid();
        DB::table('collections')->insert([
            'id' => $uuid,
            'type' => 'persona',
            'name' => 'Social Isolation',
            'meta' => json_encode([
                'intro' => 'Lorem ipsum',
                'subtitle' => 'Lorem ipsum',
                'image_file_id' => null,
            ]),
            'order' => 3,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
    }
};
