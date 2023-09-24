<?php

namespace Tests\Feature;

use App\Events\EndpointHit;
use App\Models\Audit;
use App\Models\Collection;
use App\Models\File;
use App\Models\Page;
use App\Models\Service;
use App\Models\UpdateRequest;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

class PagesTest extends TestCase
{
    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->faker = Faker::create('en_GB');
    }

    /**
     * @test
     */
    public function listEnabledPagesAsGuest200()
    {
        Page::factory()->withParent()->withChildren()->create();

        $response = $this->json('GET', '/core/v1/pages/index');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'slug',
                    'title',
                    'content',
                    'order',
                    'enabled',
                    'page_type',
                    'image',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function auditCreatedOnList()
    {
        $this->fakeEvents();

        $this->json('GET', '/core/v1/pages/index');

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) {
            return $event->getAction() === Audit::ACTION_READ;
        });
    }

    /**
     * @test
     */
    public function listMixedStatePagesAsGuest200()
    {
        $page = Page::factory()->withImage()->withParent()->disabled()
            ->create();

        $landingPage = Page::factory()->withImage()->landingPage()
            ->create();

        $response = $this->json('GET', '/core/v1/pages/index');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment([
            'id' => $page->parent->id,
        ]);
        $response->assertJsonFragment([
            'id' => $landingPage->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page->id,
        ]);
    }

    /**
     * @test
     */
    public function listMixedStatePagesAsGlobalAdmin403()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeGlobalAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->withImage()->withParent()->disabled()
            ->create();

        $landingPage = Page::factory()->withImage()->landingPage()
            ->create();

        $response = $this->json('GET', '/core/v1/pages/index');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function listMixedStatePagesAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->withImage()->withParent()->disabled()
            ->create();

        $landingPage = Page::factory()->withImage()->landingPage()
            ->create();

        $response = $this->json('GET', '/core/v1/pages/index');

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonCount(3, 'data');
        $response->assertJsonFragment([
            'id' => $page->parent->id,
        ]);
        $response->assertJsonFragment([
            'id' => $landingPage->id,
        ]);
        $response->assertJsonFragment([
            'id' => $page->id,
        ]);
    }

    /**
     * @test
     */
    public function listMixedStatePagesAsGuestFilterByID200()
    {
        $pages = Page::factory()->count(2)->create();
        $disabledPage = Page::factory()->disabled()->create();
        $landingPages = Page::factory()->count(2)->landingPage()->create();
        $disabledLandingPage = Page::factory()->landingPage()->disabled()->create();

        $ids = [
            $pages->get(1)->id,
            $disabledPage->id,
            $landingPages->get(1)->id,
            $disabledLandingPage->id,
        ];

        $response = $this->json('GET', '/core/v1/pages/index?filter[id]=' . implode(',', $ids));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment([
            'id' => $pages->get(1)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $landingPages->get(1)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $pages->get(0)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $landingPages->get(0)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $disabledPage->id,
        ]);
        $response->assertJsonMissing([
            'id' => $disabledLandingPage->id,
        ]);
    }

    /**
     * @test
     */
    public function listMixedStatePagesAsContentAdminFilterByID200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $pages = Page::factory()->count(2)->create();
        $disabledPage = Page::factory()->disabled()->create();
        $landingPages = Page::factory()->count(2)->landingPage()->create();
        $disabledLandingPage = Page::factory()->landingPage()->disabled()->create();

        $ids = [
            $pages->get(1)->id,
            $disabledPage->id,
            $landingPages->get(1)->id,
            $disabledLandingPage->id,
        ];

        $response = $this->json('GET', '/core/v1/pages/index?filter[id]=' . implode(',', $ids));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(4, 'data');
        $response->assertJsonFragment([
            'id' => $pages->get(1)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $landingPages->get(1)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $pages->get(0)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $landingPages->get(0)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $disabledPage->id,
        ]);
        $response->assertJsonFragment([
            'id' => $disabledLandingPage->id,
        ]);
    }

    /**
     * @test
     */
    public function listPagesAsGuestFilterByParentID200()
    {
        $page1 = Page::factory()->withChildren()->create();
        $page2 = Page::factory()->withChildren()->create();

        $response = $this->json('GET', '/core/v1/pages/index?filter[parent_id]=' . $page1->id);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonFragment([
            'id' => $page1->children->get(0)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $page1->children->get(1)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $page1->children->get(2)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $page1->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page2->children->get(0)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page2->children->get(1)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page2->children->get(2)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page2->id,
        ]);
    }

    /**
     * @test
     */
    public function listLandingPageChildPagesAsGuestFilterByParentID200()
    {
        $landingPage = Page::factory()->landingPage()->withChildren()->create();

        $response = $this->json('GET', '/core/v1/pages/index?filter[parent_id]=' . $landingPage->id);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonFragment([
            'id' => $landingPage->children->get(0)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $landingPage->children->get(1)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $landingPage->children->get(2)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $landingPage->id,
        ]);
    }

    /**
     * @test
     */
    public function listPagesAsGuestFilterByTitle200()
    {
        $page1 = Page::factory()->create(['title' => 'Page One']);
        $page2 = Page::factory()->create(['title' => 'Second Page']);
        $page3 = Page::factory()->create(['title' => 'Third']);
        $page4 = Page::factory()->create(['title' => 'Page the Fourth']);
        $page5 = Page::factory()->create(['title' => 'Final']);
        $landingPage = Page::factory()->landingPage()->create(['title' => 'Landing Page']);

        $response = $this->json('GET', '/core/v1/pages/index?filter[title]=page');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(4, 'data');
        $response->assertJsonFragment([
            'id' => $page1->id,
        ]);
        $response->assertJsonFragment([
            'id' => $page2->id,
        ]);
        $response->assertJsonFragment([
            'id' => $page4->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page3->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page5->id,
        ]);
        $response->assertJsonFragment([
            'id' => $landingPage->id,
        ]);
    }

    /**
     * @test
     */
    public function listMixedStatePagesAsGuestFilterByLandingPage200()
    {
        $page1 = Page::factory()->landingPage()->create();
        $page2 = Page::factory()->disabled()->create();
        $page3 = Page::factory()->create();
        $page4 = Page::factory()->landingPage()->disabled()->create();

        $response = $this->json('GET', '/core/v1/pages/index?filter[page_type]=landing');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'id' => $page1->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page2->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page3->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page4->id,
        ]);
    }

    /**
     * @test
     */
    public function listMixedStatePagesAsGuestFilterByInformationPage200()
    {
        $page1 = Page::factory()->landingPage()->create();
        $page2 = Page::factory()->disabled()->create();
        $page3 = Page::factory()->create();
        $page4 = Page::factory()->landingPage()->disabled()->create();

        $response = $this->json('GET', '/core/v1/pages/index?filter[page_type]=information');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'id' => $page3->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page1->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page2->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page4->id,
        ]);
    }

    /**
     * @test
     */
    public function listMixedStatePagesAsContentAdminFilterByLandingPage200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $page1 = Page::factory()->landingPage()->create();
        $page2 = Page::factory()->disabled()->create();
        $page3 = Page::factory()->create();
        $page4 = Page::factory()->landingPage()->disabled()->create();

        $response = $this->json('GET', '/core/v1/pages/index?filter[page_type]=landing');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment([
            'id' => $page1->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page2->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page3->id,
        ]);
        $response->assertJsonFragment([
            'id' => $page4->id,
        ]);
    }

    /**
     * @test
     */
    public function listMixedStatePagesAsAdminFilterByInformationPage200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $page1 = Page::factory()->landingPage()->create();
        $page2 = Page::factory()->disabled()->create();
        $page3 = Page::factory()->create();
        $page4 = Page::factory()->landingPage()->disabled()->create();

        $response = $this->json('GET', '/core/v1/pages/index?filter[page_type]=information');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment([
            'id' => $page2->id,
        ]);
        $response->assertJsonFragment([
            'id' => $page3->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page1->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page4->id,
        ]);
    }

    /**
     * @test
     */
    public function listPagesAsGuestFilterByTitleAndPageType200()
    {
        $page1 = Page::factory()->create(['title' => 'Page One']);
        $page2 = Page::factory()->create(['title' => 'Second Page']);
        $page3 = Page::factory()->create(['title' => 'Third']);
        $landingPage1 = Page::factory()->landingPage()->create(['title' => 'Landing Page One']);
        $landingPage2 = Page::factory()->landingPage()->create(['title' => 'Landing Two']);
        $landingPage3 = Page::factory()->landingPage()->create(['title' => 'Landing Three']);

        $response = $this->json('GET', '/core/v1/pages/index?filter[title]=page&filter[page_type]=information');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment([
            'id' => $page1->id,
        ]);
        $response->assertJsonFragment([
            'id' => $page2->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page3->id,
        ]);
        $response->assertJsonMissing([
            'id' => $landingPage1->id,
        ]);
        $response->assertJsonMissing([
            'id' => $landingPage2->id,
        ]);
        $response->assertJsonMissing([
            'id' => $landingPage3->id,
        ]);

        $response = $this->json('GET', '/core/v1/pages/index?filter[title]=page&filter[page_type]=landing');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'id' => $landingPage1->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page1->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page2->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page3->id,
        ]);
        $response->assertJsonMissing([
            'id' => $landingPage2->id,
        ]);
        $response->assertJsonMissing([
            'id' => $landingPage3->id,
        ]);
    }

    /**
     * @test
     */
    public function listEnabledPagesAsGuestIncludeParent200()
    {
        Page::factory()->withParent()->withChildren()->create();

        $response = $this->json('GET', '/core/v1/pages/index?include=parent');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'slug',
                    'title',
                    'content',
                    'order',
                    'enabled',
                    'page_type',
                    'image',
                    'parent',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function listEnabledPagesAsGuestIncludeLandingPage200()
    {
        Page::factory()->withParent()->withChildren()->create();

        $response = $this->json('GET', '/core/v1/pages/index?include=landing-page-ancestors');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'slug',
                    'title',
                    'content',
                    'order',
                    'enabled',
                    'page_type',
                    'image',
                    'landing_page',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function listEnabledPagesAsGuestIncludeChildren200()
    {
        Page::factory()->withParent()->withChildren()->create();

        $response = $this->json('GET', '/core/v1/pages/index?include=children');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'slug',
                    'title',
                    'content',
                    'order',
                    'enabled',
                    'page_type',
                    'image',
                    'children' => [
                        '*' => [
                            'id',
                            'slug',
                            'title',
                            'image',
                            'content',
                            'order',
                            'enabled',
                            'page_type',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function listLandingPageDescendantsAsGuestIncludeParent200()
    {
        $page = Page::factory()->withImage()->withChildren()->create();
        $parent = Page::factory()->create();
        $parent->appendNode($page);
        $landingPage1 = Page::factory()->landingPage()->create();
        $landingPage1->appendNode($parent);
        $landingPage2 = Page::factory()->landingPage()->withChildren()->create();

        $response = $this->json('GET', '/core/v1/pages/index?filter[landing_page]=' . $landingPage1->id . '&include=parent');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'slug',
                    'title',
                    'content',
                    'order',
                    'enabled',
                    'page_type',
                    'image',
                    'parent',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);

        $response->assertJsonFragment([
            'id' => $parent->id,
        ]);

        $response->assertJsonFragment([
            'id' => $page->id,
        ]);

        foreach ($page->children as $child) {
            $response->assertJsonFragment([
                'id' => $child->id,
            ]);
        }

        $response->assertJsonFragment([
            'id' => $landingPage1->id,
        ]);

        $response->assertJsonMissing([
            'id' => $landingPage2->id,
        ]);

        foreach ($landingPage2->children as $child) {
            $response->assertJsonMissing([
                'id' => $child->id,
            ]);
        }
    }

    /**
     * Create a page
     */

    /**
     * @test
     */
    public function createPageAsGuest401()
    {
        $parentPage = Page::factory()->create();

        $data = [
            'title' => 'A New Page',
            'slug' => 'a-new-page',
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
        ];

        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function createPageAsServiceWorker403()
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);
        $parentPage = Page::factory()->create();

        Passport::actingAs($user);

        $data = [
            'title' => 'A New Page',
            'slug' => 'a-new-page',
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
        ];

        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function createPageAsServiceAdmin403()
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);
        $parentPage = Page::factory()->create();

        Passport::actingAs($user);

        $data = [
            'title' => 'A New Page',
            'slug' => 'a-new-page',
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
        ];

        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function createPageAsOrganisationAdmin403()
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        $parentPage = Page::factory()->create();

        Passport::actingAs($user);

        $data = [
            'title' => 'A New Page',
            'slug' => 'a-new-page',
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
        ];

        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function createPageAsGlobalAdmin403()
    {
        $user = User::factory()->create()->makeGlobalAdmin();
        $parentPage = Page::factory()->create();

        Passport::actingAs($user);

        $data = [
            'title' => 'A New Page',
            'slug' => 'a-new-page',
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
        ];

        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function createPageAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $parentPage = Page::factory()->create();

        $data = [
            'title' => 'A New Page',
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
        ];

        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::NEW_TYPE_PAGE,
            'updateable_id' => null,
        ]);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_PAGE)
            ->where('updateable_id', null)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // $data['slug'] = Str::slug($data['title']);
        $data['page_type'] = Page::PAGE_TYPE_INFORMATION;
        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas((new Page())->getTable(), [
            'title' => 'A New Page',
            'slug' => 'a-new-page',
            'excerpt' => $data['excerpt'],
            'parent_uuid' => $data['parent_id'],
        ]);
    }

    /**
     * @test
     */
    public function createPageAsSuperAdmin201()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeSuperAdmin();

        Passport::actingAs($user);

        $parentPage = Page::factory()->create();

        $data = [
            'title' => 'A New Page',
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
        ];

        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_CREATED);

        $response->assertJsonResource([
            'id',
            'title',
            'excerpt',
            'content',
            'order',
            'enabled',
            'page_type',
            'image',
            'landing_page',
            'parent',
            'children',
            'collection_categories',
            'collection_personas',
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * @test
     */
    public function createInformationPageWithMinimalDataAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $parentPage = Page::factory()->create();

        $data = [
            'title' => $this->faker->sentence(),
            'parent_id' => $parentPage->id,
        ];

        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::NEW_TYPE_PAGE,
            'updateable_id' => null,
        ]);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_PAGE)
            ->where('updateable_id', null)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $data['page_type'] = Page::PAGE_TYPE_INFORMATION;
        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas((new Page())->getTable(), [
            'title' => $data['title'],
            'slug' => Str::slug($data['title']),
            'parent_uuid' => $data['parent_id'],
        ]);
    }

    /**
     * @test
     */
    public function createLandingPageWithMinimalDataAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $data = [
            'title' => $this->faker->sentence(),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
                'info_pages' => [
                    'title' => $this->faker->sentence(),
                ],
                'collections' => [
                    'title' => $this->faker->sentence(),
                ],
            ],
            'page_type' => Page::PAGE_TYPE_LANDING,
        ];

        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::NEW_TYPE_PAGE,
            'updateable_id' => null,
        ]);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_PAGE)
            ->where('updateable_id', null)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas((new Page())->getTable(), [
            'title' => $data['title'],
            'slug' => Str::slug($data['title']),
            'parent_uuid' => null,
        ]);
    }

    /**
     * @test
     */
    public function createPageAuditCreated()
    {
        $this->fakeEvents();

        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $parentPage = Page::factory()->create();

        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
        ];

        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_OK);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) {
            return $event->getAction() === Audit::ACTION_CREATE;
        });
    }

    /**
     * @test
     */
    public function createPageAsContentAdminWithInvalidData422()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        // Missing title
        $this->json('POST', '/core/v1/pages', [
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Empty title and content
        $this->json('POST', '/core/v1/pages', [
            'title' => '',
            'content' => '',
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Content not an array
        $this->json('POST', '/core/v1/pages', [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->realText(),
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Invalid structure for 'copy' content type
        $this->json('POST', '/core/v1/pages', [
            'title' => $this->faker->sentence(),
            'excerpt' => str_pad($this->faker->paragraph(2), 151, 'words '),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'copy' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Invalid parent id
        $this->json('POST', '/core/v1/pages', [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'parent_id' => 1,
        ])->assertStatus(Response::HTTP_NOT_FOUND);

        // Unknown parent id
        $this->json('POST', '/core/v1/pages', [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'parent_id' => $this->faker->uuid(),
        ])->assertStatus(Response::HTTP_NOT_FOUND);

        // Invalid content stucture for landing page
        $this->json('POST', '/core/v1/pages', [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'page_type' => Page::PAGE_TYPE_LANDING,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $parentPage = Page::factory()->withChildren()->create();

        // Invalid order
        $this->json('POST', '/core/v1/pages', [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
            'order' => $parentPage->children->count() + 1,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Invalid order
        $this->json('POST', '/core/v1/pages', [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
            'order' => -1,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Landing page cannot have parent
        $this->json('POST', '/core/v1/pages', [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
            'page_type' => Page::PAGE_TYPE_LANDING,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Assigned Images not allowed
        $image = File::factory()->create([
            'filename' => Str::random() . '.png',
            'mime_type' => 'image/png',
        ]);

        $this->json('POST', '/core/v1/pages', [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
            'order' => 1,
            'image_file_id' => $image->id,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function createChildPageAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $parentPage = Page::factory()->create();

        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::NEW_TYPE_PAGE,
            'updateable_id' => null,
        ]);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_PAGE)
            ->where('updateable_id', null)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $data['page_type'] = Page::PAGE_TYPE_INFORMATION;
        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas((new Page())->getTable(), [
            'title' => $data['title'],
            'slug' => Str::slug($data['title']),
            'parent_uuid' => $data['parent_id'],
        ]);
    }

    /**
     * @test
     */
    public function createChildPageInheritParentStatusAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $parentPage = Page::factory()->disabled()->create();

        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'enabled' => true,
            'parent_id' => $parentPage->id,
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::NEW_TYPE_PAGE,
            'updateable_id' => null,
        ]);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_PAGE)
            ->where('updateable_id', null)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $data['page_type'] = Page::PAGE_TYPE_INFORMATION;
        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas((new Page())->getTable(), [
            'title' => $data['title'],
            'slug' => Str::slug($data['title']),
            'parent_uuid' => $data['parent_id'],
            'enabled' => false,
        ]);
    }

    /**
     * @test
     */
    public function createInformationPageRootAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'parent_id' => null,
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::NEW_TYPE_PAGE,
            'updateable_id' => null,
        ]);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_PAGE)
            ->where('updateable_id', null)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $data['page_type'] = Page::PAGE_TYPE_INFORMATION;
        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas((new Page())->getTable(), [
            'title' => $data['title'],
            'slug' => Str::slug($data['title']),
            'parent_uuid' => $data['parent_id'],
        ]);

        $rootPage = Page::where('title', $data['title'])->firstOrFail();

        $this->assertTrue($rootPage->isRoot());
    }

    /**
     * @test
     */
    public function createLandingPageAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
                'about' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
                'info_pages' => [
                    'title' => $this->faker->sentence(),
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
                'collections' => [
                    'title' => $this->faker->sentence(),
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'page_type' => Page::PAGE_TYPE_LANDING,
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::NEW_TYPE_PAGE,
            'updateable_id' => null,
        ]);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_PAGE)
            ->where('updateable_id', null)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $data['page_type'] = Page::PAGE_TYPE_LANDING;
        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas((new Page())->getTable(), [
            'title' => $data['title'],
            'slug' => Str::slug($data['title']),
            'parent_uuid' => null,
            'page_type' => Page::PAGE_TYPE_LANDING,
        ]);

        $rootPage = Page::where('title', $data['title'])->firstOrFail();

        $this->assertTrue($rootPage->isRoot());
    }

    /**
     * @test
     */
    public function createPageAfterSiblingAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $parentPage = Page::factory()->withChildren()->create();

        $childPage = $parentPage->children()->defaultOrder()->offset(1)->limit(1)->first();

        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
            'order' => 1,
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::NEW_TYPE_PAGE,
            'updateable_id' => null,
        ]);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_PAGE)
            ->where('updateable_id', null)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $data['page_type'] = Page::PAGE_TYPE_INFORMATION;
        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $page = Page::where('title', $data['title'])->firstOrFail();

        $this->assertEquals(1, $page->order);

        $this->assertEquals($childPage->id, $page->getNextSibling()->id);
    }

    /**
     * @test
     */
    public function createFirstChildPageAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $parentPage = Page::factory()->withChildren()->create();

        $childPage = $parentPage->children()->defaultOrder()->offset(0)->limit(1)->first();

        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
            'order' => 0,
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::NEW_TYPE_PAGE,
            'updateable_id' => null,
        ]);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_PAGE)
            ->where('updateable_id', null)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $data['page_type'] = Page::PAGE_TYPE_INFORMATION;
        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $page = Page::where('title', $data['title'])->firstOrFail();

        $this->assertEquals(0, $page->order);

        $this->assertEquals($childPage->id, $page->getNextSibling()->id);
        $this->assertEquals(null, $page->getPrevSibling());
    }

    /**
     * @test
     */
    public function createPageWithImagePNGAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $parentPage = Page::factory()->create();

        $image = File::factory()->pendingAssignment()->create([
            'filename' => Str::random() . '.png',
            'mime_type' => 'image/png',
        ]);

        $image->uploadBase64EncodedFile(
            'data:image/png;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.png'))
        );

        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'image_file_id' => $image->id,
            'parent_id' => $parentPage->id,
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::NEW_TYPE_PAGE,
            'updateable_id' => null,
        ]);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_PAGE)
            ->where('updateable_id', null)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $data['page_type'] = Page::PAGE_TYPE_INFORMATION;
        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas((new Page())->getTable(), [
            'title' => $data['title'],
            'slug' => Str::slug($data['title']),
            'parent_uuid' => $data['parent_id'],
            'page_type' => Page::PAGE_TYPE_INFORMATION,
            'image_file_id' => $image->id,
        ]);
    }

    /**
     * @test
     */
    public function createPageWithImageJPGAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $parentPage = Page::factory()->create();

        $image = File::factory()->pendingAssignment()->create([
            'filename' => Str::random() . '.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $image->uploadBase64EncodedFile(
            'data:image/jpeg;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.jpg'))
        );

        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'image_file_id' => $image->id,
            'parent_id' => $parentPage->id,
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::NEW_TYPE_PAGE,
            'updateable_id' => null,
        ]);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_PAGE)
            ->where('updateable_id', null)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $data['page_type'] = Page::PAGE_TYPE_INFORMATION;
        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas((new Page())->getTable(), [
            'title' => $data['title'],
            'slug' => Str::slug($data['title']),
            'parent_uuid' => $data['parent_id'],
            'page_type' => Page::PAGE_TYPE_INFORMATION,
            'image_file_id' => $image->id,
        ]);
    }

    /**
     * @test
     */
    public function createPageWithImageSVGAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $parentPage = Page::factory()->create();

        $image = File::factory()->pendingAssignment()->create([
            'filename' => Str::random() . '.svg',
            'mime_type' => 'image/svg+xml',
        ]);

        $image->uploadBase64EncodedFile(
            'data:image/svg+xml;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.svg'))
        );

        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'image_file_id' => $image->id,
            'parent_id' => $parentPage->id,
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::NEW_TYPE_PAGE,
            'updateable_id' => null,
        ]);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_PAGE)
            ->where('updateable_id', null)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $data['page_type'] = Page::PAGE_TYPE_INFORMATION;
        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas((new Page())->getTable(), [
            'title' => $data['title'],
            'slug' => Str::slug($data['title']),
            'parent_uuid' => $data['parent_id'],
            'page_type' => Page::PAGE_TYPE_INFORMATION,
            'image_file_id' => $image->id,
        ]);
    }

    /**
     * @test
     */
    public function createInformationPageWithCollectionsAsContentAdmin422()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $parentPage = Page::factory()->create();

        $collections = Collection::factory()->count(5)->create();

        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
            'collections' => $collections->pluck('id'),
            'page_type' => 'information',
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function createLandingPageWithCollectionsAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $collections = Collection::factory()->count(5)->create();

        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
                'about' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
                'info_pages' => [
                    'title' => $this->faker->sentence(),
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
                'collections' => [
                    'title' => $this->faker->sentence(),
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'page_type' => Page::PAGE_TYPE_LANDING,
            'collections' => $collections->pluck('id')->all(),
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::NEW_TYPE_PAGE,
            'updateable_id' => null,
        ]);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_PAGE)
            ->where('updateable_id', null)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $page = Page::where('title', $data['title'])->firstOrFail();

        $this->assertDatabaseHas('collection_page', [
            'page_id' => $page->id,
            'collection_id' => $collections->get(0)->id,
        ]);

        $this->assertDatabaseHas('collection_page', [
            'page_id' => $page->id,
            'collection_id' => $collections->get(1)->id,
        ]);

        $this->assertDatabaseHas('collection_page', [
            'page_id' => $page->id,
            'collection_id' => $collections->get(2)->id,
        ]);

        $this->assertDatabaseHas('collection_page', [
            'page_id' => $page->id,
            'collection_id' => $collections->get(3)->id,
        ]);

        $this->assertDatabaseHas('collection_page', [
            'page_id' => $page->id,
            'collection_id' => $collections->get(4)->id,
        ]);
    }

    /**
     * @test
     */
    public function createInformationPageWithCallToActionAsContentAdmin422()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $parentPage = Page::factory()->create();

        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                        [
                            'type' => 'cta',
                            'title' => '',
                            'description' => $this->faker->realText(),
                            'url' => $this->faker->url(),
                            'buttonText' => $this->faker->words(3, true),
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
            'page_type' => 'information',
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                        [
                            'type' => 'cta',
                            'title' => $this->faker->sentence(),
                            'description' => null,
                            'url' => $this->faker->url(),
                            'buttonText' => $this->faker->words(3, true),
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
            'page_type' => 'information',
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                        [
                            'type' => 'cta',
                            'title' => $this->faker->sentence(),
                            'description' => $this->faker->realText(),
                            'url' => $this->faker->url(),
                            'buttonText' => null,
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
            'page_type' => 'information',
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                        [
                            'type' => 'cta',
                            'title' => $this->faker->sentence(),
                            'description' => $this->faker->realText(),
                            'url' => 'foo',
                            'buttonText' => $this->faker->words(3, true),
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
            'page_type' => 'information',
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function createInformationPageWithCallToActionAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $parentPage = Page::factory()->create();

        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                        [
                            'type' => 'cta',
                            'title' => $this->faker->sentence(),
                            'description' => $this->faker->realText(),
                            'url' => $this->faker->url(),
                            'buttonText' => $this->faker->words(3, true),
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
            'page_type' => 'information',
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * @test
     */
    public function createLandingPageWithCallToActionsAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                        [
                            'type' => 'cta',
                            'title' => $this->faker->sentence(),
                            'description' => $this->faker->realText(),
                            'url' => $this->faker->url(),
                            'buttonText' => $this->faker->words(3, true),
                        ],
                    ],
                ],
                'about' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                        [
                            'type' => 'cta',
                            'title' => $this->faker->sentence(),
                            'description' => $this->faker->realText(),
                            'url' => $this->faker->url(),
                            'buttonText' => $this->faker->words(3, true),
                        ],
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
                'info_pages' => [
                    'title' => $this->faker->sentence(),
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                        [
                            'type' => 'cta',
                            'title' => $this->faker->sentence(),
                            'description' => $this->faker->realText(),
                            'url' => $this->faker->url(),
                            'buttonText' => $this->faker->words(3, true),
                        ],
                    ],
                ],
                'collections' => [
                    'title' => $this->faker->sentence(),
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'page_type' => Page::PAGE_TYPE_LANDING,
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * @test
     */
    public function createPageWithSameTitleAsExistingPageIncrementsSlugAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        Page::factory()->withImage()->withParent()->withChildren()->disabled()
            ->create([
                'title' => 'Test Page Title',
                'slug' => 'test-page-title',
            ]);

        $parentPage = Page::factory()->create();

        $data = [
            'title' => 'Test Page Title',
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
        ];

        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_PAGE)
            ->where('updateable_id', null)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas((new Page())->getTable(), [
            'title' => $data['title'],
            'parent_uuid' => $parentPage->id,
            'slug' => 'test-page-title-1',
        ]);

        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_PAGE)
            ->where('updateable_id', null)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas((new Page())->getTable(), [
            'title' => $data['title'],
            'parent_uuid' => $parentPage->id,
            'slug' => 'test-page-title-2',
        ]);
    }

    /**
     * @test
     */
    public function createPageWithSlugAsContentAdmin201()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $parentPage = Page::factory()->create();

        $data = [
            'title' => 'Test Page Title',
            'slug' => 'different-slug',
            'excerpt' => substr($this->faker->paragraph(2), 0, 149),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
        ];

        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_PAGE)
            ->where('updateable_id', null)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas((new Page())->getTable(), [
            'parent_uuid' => $parentPage->id,
            'slug' => 'different-slug',
        ]);
    }

    /**
     * Get a single page
     */

    /**
     * @test
     */
    public function getEnabledInformationPageAsGuest200()
    {
        $page = Page::factory()->withImage()->withParent()->withChildren()->create();

        $response = $this->json('GET', '/core/v1/pages/' . $page->id);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonResource([
            'id',
            'slug',
            'title',
            'excerpt',
            'content',
            'order',
            'enabled',
            'page_type',
            'image' => [
                'id',
                'mime_type',
                'created_at',
                'updated_at',
            ],
            'landing_page',
            'parent' => [
                'id',
                'slug',
                'title',
                'image',
                'content',
                'order',
                'enabled',
                'page_type',
                'created_at',
                'updated_at',
            ],
            'children' => [
                '*' => [
                    'id',
                    'slug',
                    'title',
                    'image',
                    'content',
                    'order',
                    'enabled',
                    'page_type',
                    'created_at',
                    'updated_at',
                ],
            ],
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * @test
     */
    public function getEnabledLandingPageAsGuest200()
    {
        $page = Page::factory()->withImage()->landingPage()->withChildren()->create();

        $response = $this->json('GET', '/core/v1/pages/' . $page->id);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonResource([
            'id',
            'slug',
            'title',
            'excerpt',
            'content',
            'order',
            'enabled',
            'page_type',
            'image' => [
                'id',
                'mime_type',
                'created_at',
                'updated_at',
            ],
            'landing_page',
            'parent',
            'children' => [
                '*' => [
                    'id',
                    'slug',
                    'title',
                    'image',
                    'content',
                    'order',
                    'enabled',
                    'page_type',
                    'created_at',
                    'updated_at',
                ],
            ],
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * @test
     */
    public function getInformationPageBySlugAsGuest200()
    {
        $page = Page::factory()->withImage()->withParent()->withChildren()->create();

        $response = $this->json('GET', '/core/v1/pages/' . $page->slug);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'id' => $page->id,
        ]);
    }

    /**
     * @test
     */
    public function getEnabledInformationPageWithLandingPageAncestorAsGuest200()
    {
        $page = Page::factory()->withImage()->withChildren()->create();
        $parent = Page::factory()->create();
        $parent->appendNode($page);
        $landingPage = Page::factory()->landingPage()->create();
        $landingPage->appendNode($parent);

        $response = $this->json('GET', '/core/v1/pages/' . $page->id);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonResource([
            'id',
            'title',
            'excerpt',
            'content',
            'order',
            'enabled',
            'page_type',
            'image' => [
                'id',
                'mime_type',
                'created_at',
                'updated_at',
            ],
            'landing_page' => [
                'id',
                'title',
                'image',
                'content',
                'order',
                'enabled',
                'page_type',
                'created_at',
                'updated_at',
            ],
            'parent' => [
                'id',
                'title',
                'image',
                'content',
                'order',
                'enabled',
                'page_type',
                'created_at',
                'updated_at',
            ],
            'children' => [
                '*' => [
                    'id',
                    'title',
                    'image',
                    'content',
                    'order',
                    'enabled',
                    'page_type',
                    'created_at',
                    'updated_at',
                ],
            ],
            'created_at',
            'updated_at',
        ]);

        $response->assertJson([
            'data' => [
                'landing_page' => [
                    'id' => $landingPage->id,
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function auditCreatedOnShow()
    {
        $this->fakeEvents();

        $page = Page::factory()->create();

        $this->json('GET', '/core/v1/pages/' . $page->id);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) {
            return $event->getAction() === Audit::ACTION_READ;
        });
    }

    /**
     * @test
     */
    public function getDisabledPageAsGuest403()
    {
        $page = Page::factory()->withImage()->withParent()->withChildren()->disabled()
            ->create();

        $response = $this->json('GET', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function getDisabledPageAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->withImage()->withParent()->withChildren()->disabled()
            ->create();

        $response = $this->json('GET', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonResource([
            'id',
            'slug',
            'title',
            'excerpt',
            'content',
            'order',
            'enabled',
            'page_type',
            'image' => [
                'id',
                'mime_type',
                'created_at',
                'updated_at',
            ],
            'landing_page',
            'parent' => [
                'id',
                'slug',
                'title',
                'image',
                'content',
                'order',
                'enabled',
                'page_type',
                'created_at',
                'updated_at',
            ],
            'children' => [
                '*' => [
                    'id',
                    'slug',
                    'title',
                    'image',
                    'content',
                    'order',
                    'enabled',
                    'page_type',
                    'created_at',
                    'updated_at',
                ],
            ],
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * @test
     */
    public function getEnabledPageImagePNGAsGuest200()
    {
        $parentPage = Page::factory()->create();

        $image = File::factory()->pendingAssignment()->create([
            'filename' => Str::random() . '.png',
            'mime_type' => 'image/png',
        ]);

        $base64Image = 'data:image/png;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.png'));

        $image->uploadBase64EncodedFile($base64Image);

        $page = Page::factory()->create([
            'image_file_id' => $image->id,
        ]);

        $response = $this->json('GET', '/core/v1/pages/' . $page->id . '/image.png');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals($base64Image, 'data:image/png;base64,' . base64_encode($response->content()));
    }

    /**
     * @test
     */
    public function getEnabledPageImageJPGAsGuest200()
    {
        $parentPage = Page::factory()->create();

        $image = File::factory()->pendingAssignment()->create([
            'filename' => Str::random() . '.jpg',
            'mime_type' => 'image/jepg',
        ]);

        $base64Image = 'data:image/jpeg;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.jpg'));

        $image->uploadBase64EncodedFile($base64Image);

        $page = Page::factory()->create([
            'image_file_id' => $image->id,
        ]);

        $response = $this->json('GET', '/core/v1/pages/' . $page->id . '/image.jpg');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals($base64Image, 'data:image/jpeg;base64,' . base64_encode($response->content()));
    }

    /**
     * @test
     */
    public function getEnabledPageImageSVGAsGuest200()
    {
        $parentPage = Page::factory()->create();

        $image = File::factory()->pendingAssignment()->create([
            'filename' => Str::random() . '.svg',
            'mime_type' => 'image/svg+xml',
        ]);

        $base64Image = 'data:image/svg+xml;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.svg'));

        $image->uploadBase64EncodedFile($base64Image);

        $page = Page::factory()->create([
            'image_file_id' => $image->id,
        ]);

        $response = $this->json('GET', '/core/v1/pages/' . $page->id . '/image.svg');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals($base64Image, 'data:image/svg+xml;base64,' . base64_encode($response->content()));
    }

    /**
     * Update page
     */

    /**
     * @test
     */
    public function updatePageAsGuest401()
    {
        $page = Page::factory()->withImage()->withParent()->withChildren()->disabled()
            ->create();

        $data = [
            'title' => 'New Title',
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function updatePageAsServiceWorker403()
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);
        $page = Page::factory()->withImage()->withParent()->withChildren()->disabled()
            ->create();
        Passport::actingAs($user);

        $data = [
            'title' => 'New Title',
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function updatePageAsServiceAdmin403()
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);
        $page = Page::factory()->withImage()->withParent()->withChildren()->disabled()
            ->create();
        Passport::actingAs($user);

        $data = [
            'title' => 'New Title',
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function updatePageAsOrganisationAdmin403()
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        $page = Page::factory()->withImage()->withParent()->withChildren()->disabled()
            ->create();
        Passport::actingAs($user);

        $data = [
            'title' => 'New Title',
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function updatePageAsGlobalAdmin403()
    {
        $user = User::factory()->create()->makeGlobalAdmin();
        $page = Page::factory()->withImage()->withParent()->withChildren()->disabled()
            ->create();
        Passport::actingAs($user);

        $data = [
            'title' => 'New Title',
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function updatePageAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create()->makeContentAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->withImage()->withParent()->withChildren()->disabled()
            ->create();

        $data = [
            'title' => 'New Title',
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the updated page
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::EXISTING_TYPE_PAGE,
            'updateable_id' => $page->id,
        ]);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::EXISTING_TYPE_PAGE)
            ->where('updateable_id', $page->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas(table(Page::class), [
            'id' => $page->id,
            'title' => 'New Title',
        ]);
    }

    /**
     * @test
     */
    public function updatePageAsSuperAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeSuperAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->withImage()->withParent()->withChildren()->disabled()
            ->create();

        $payload = [
            'title' => 'New Title',
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $payload);

        $response->assertStatus(Response::HTTP_OK);

        // The organisation event is updated
        $this->assertDatabaseHas((new Page())->getTable(), array_merge(['id' => $page->id], $payload));

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::EXISTING_TYPE_PAGE)
            ->where('updateable_id', $page->id)
            ->firstOrFail();

        $this->assertEquals($updateRequest->data, $payload);

        $this->assertNotNull($updateRequest->approved_at);
    }

    /**
     * @test
     */
    public function updatePageAuditCreated()
    {
        $this->fakeEvents();

        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->create();

        $data = [
            'title' => 'New Title',
        ];

        $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) {
            return $event->getAction() === Audit::ACTION_UPDATE;
        });
    }

    /**
     * @test
     */
    public function updatePageAsAdminWithInvalidData422()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->withImage()->withParent()->withChildren()->disabled()
            ->create();

        $this->json('PUT', '/core/v1/pages/' . $page->id, [
            'title' => '',
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('PUT', '/core/v1/pages/' . $page->id, [
            'content' => '',
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('PUT', '/core/v1/pages/' . $page->id, [
            'title' => '',
            'content' => '',
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('PUT', '/core/v1/pages/' . $page->id, [
            'content' => $this->faker->realText(),
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('PUT', '/core/v1/pages/' . $page->id, [
            'parent_id' => $this->faker->uuid(),
        ])->assertStatus(Response::HTTP_NOT_FOUND);

        $this->json('PUT', '/core/v1/pages/' . $page->id, [
            'order' => $page->siblingsAndSelf()->count() + 1,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('PUT', '/core/v1/pages/' . $page->id, [
            'order' => -1,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->json('PUT', '/core/v1/pages/' . $page->id, [
            'page_type' => Page::PAGE_TYPE_LANDING,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        /**
         * Assigned Images not allowed
         */
        $image = File::factory()->create([
            'filename' => Str::random() . '.png',
            'mime_type' => 'image/png',
        ]);

        $this->json('PUT', '/core/v1/pages/' . $page->id, [
            'image_file_id' => $image->id,
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function updatePageAddImageAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $image = File::factory()->pendingAssignment()->create([
            'filename' => Str::random() . '.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $image->uploadBase64EncodedFile(
            'data:image/jpeg;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.jpg'))
        );

        $page = Page::factory()->withParent()->withChildren()->disabled()
            ->create();

        $data = [
            'image_file_id' => $image->id,
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the updated page
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::EXISTING_TYPE_PAGE,
            'updateable_id' => $page->id,
        ]);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::EXISTING_TYPE_PAGE)
            ->where('updateable_id', $page->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas(table(Page::class), [
            'id' => $page->id,
            'image_file_id' => $image->id,
        ]);
    }

    /**
     * @test
     */
    public function updatePageRemoveImageAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $image = File::factory()->create([
            'filename' => Str::random() . '.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $image->uploadBase64EncodedFile(
            'data:image/jpeg;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.jpg'))
        );

        $page = Page::factory()->withParent()->withChildren()->disabled()
            ->create([
                'image_file_id' => $image->id,
            ]);

        $data = [
            'image_file_id' => null,
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the updated page
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::EXISTING_TYPE_PAGE,
            'updateable_id' => $page->id,
        ]);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::EXISTING_TYPE_PAGE)
            ->where('updateable_id', $page->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas(table(Page::class), [
            'id' => $page->id,
            'image_file_id' => null,
        ]);
    }

    /**
     * @test
     */
    public function updatePageChangeImageAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $imageJpg = File::factory()->create([
            'filename' => Str::random() . '.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $imageJpg->uploadBase64EncodedFile(
            'data:image/jpeg;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.jpg'))
        );

        $imagePng = File::factory()->pendingAssignment()->create([
            'filename' => Str::random() . '.png',
            'mime_type' => 'image/png',
        ]);

        $imagePng->uploadBase64EncodedFile(
            'data:image/png;base64,' . base64_encode(Storage::disk('local')->get('/test-data/image.png'))
        );

        $page = Page::factory()->withParent()->withChildren()->disabled()
            ->create([
                'image_file_id' => $imageJpg->id,
            ]);

        $data = [
            'image_file_id' => $imagePng->id,
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the updated page
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::EXISTING_TYPE_PAGE,
            'updateable_id' => $page->id,
        ]);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::EXISTING_TYPE_PAGE)
            ->where('updateable_id', $page->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas(table(Page::class), [
            'id' => $page->id,
            'image_file_id' => $imagePng->id,
        ]);
    }

    /**
     * @test
     */
    public function updatePageChangeParentAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $parentPage1 = Page::factory()->withChildren()->create();
        $parentPage2 = Page::factory()->withChildren()->create();

        $page = Page::factory()->withChildren()
            ->create([
                'parent_uuid' => $parentPage1->id,
            ]);

        $data = [
            'parent_id' => $parentPage2->id,
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the updated page
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::EXISTING_TYPE_PAGE,
            'updateable_id' => $page->id,
        ]);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::EXISTING_TYPE_PAGE)
            ->where('updateable_id', $page->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas(table(Page::class), [
            'id' => $page->id,
            'parent_uuid' => $parentPage2->id,
        ]);
    }

    /**
     * @test
     */
    public function updatePageChangeParentInheritStatusAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $parentPage1 = Page::factory()->withChildren()->create();
        $parentPage2 = Page::factory()->disabled()->withChildren()->create();

        $page = Page::factory()->withChildren()
            ->create([
                'parent_uuid' => $parentPage1->id,
            ]);

        $data = [
            'parent_id' => $parentPage2->id,
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the updated page
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::EXISTING_TYPE_PAGE,
            'updateable_id' => $page->id,
        ]);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::EXISTING_TYPE_PAGE)
            ->where('updateable_id', $page->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas(table(Page::class), [
            'id' => $page->id,
            'enabled' => false,
        ]);
    }

    /**
     * @test
     */
    public function updatePageChangePageTypeAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $parentPage = Page::factory()->withChildren()->create();

        $page = Page::factory()->withChildren()
            ->create();

        $page->appendToNode($parentPage)->save();

        $data = [
            'page_type' => Page::PAGE_TYPE_LANDING,
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $data = [
            'page_type' => Page::PAGE_TYPE_LANDING,
            'parent_id' => null,
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the updated page
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::EXISTING_TYPE_PAGE,
            'updateable_id' => $page->id,
        ]);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::EXISTING_TYPE_PAGE)
            ->where('updateable_id', $page->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas(table(Page::class), [
            'id' => $page->id,
            'parent_uuid' => null,
            'page_type' => Page::PAGE_TYPE_LANDING,
        ]);
    }

    /**
     * @test
     */
    public function updatePageChangeOrderAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $parentPage = Page::factory()->withChildren()->create();

        $children = $parentPage->children()->defaultOrder()->get();

        $data = [
            'order' => 2,
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $children->get(1)->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest = UpdateRequest::find($response->json()['id']);

        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $children->get(2)->refreshNode();

        $this->assertEquals($children->get(1)->id, $children->get(2)->getNextSibling()->id);

        $reponse = $this->getJson('/core/v1/pages/' . $children->get(1)->id);

        $reponse->assertJsonFragment([
            'order' => 2,
        ]);

        $data = [
            'order' => 0,
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $children->get(1)->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest = UpdateRequest::find($response->json()['id']);

        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $children->get(1)->refreshNode();

        $this->assertEquals($children->get(0)->id, $children->get(1)->getNextSibling()->id);

        $reponse = $this->getJson('/core/v1/pages/' . $children->get(1)->id);

        $reponse->assertJsonFragment([
            'order' => 0,
        ]);
    }

    /**
     * @test
     */
    public function updatePageDisabledCascadestoChildPagesAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create()->makeContentAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->withParent()->withChildren()->create();

        $parent = $page->parent;

        $children = $page->children()->defaultOrder()->get();

        $data = [
            'enabled' => 0,
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $parent->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest = UpdateRequest::find($response->json()['id']);

        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertFalse($parent->fresh()->enabled);
        $this->assertFalse($page->fresh()->enabled);
        $this->assertFalse($children->get(0)->fresh()->enabled);

        $this->assertDatabaseHas(table(Page::class), [
            'id' => $parent->id,
            'enabled' => false,
        ]);

        $data = [
            'enabled' => 1,
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $parent->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest = UpdateRequest::find($response->json()['id']);

        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertTrue($parent->fresh()->enabled);
        $this->assertFalse($page->fresh()->enabled);
        $this->assertFalse($children->get(0)->fresh()->enabled);

        $this->assertDatabaseHas(table(Page::class), [
            'id' => $parent->id,
            'enabled' => true,
        ]);
    }

    /**
     * @test
     */
    public function updateInformationPageAddCollectionsAsContentAdmin422()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->create();

        $collections = Collection::factory()->count(5)->create();

        $data = [
            'collections' => $collections->pluck('id'),
        ];
        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function updateLandingPageAddCollectionsAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->landingPage()->create();

        $collections = Collection::factory()->count(5)->create();

        $data = [
            'collections' => $collections->pluck('id')->all(),
        ];
        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest = UpdateRequest::find($response->json()['id']);

        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas('collection_page', [
            'page_id' => $page->id,
            'collection_id' => $collections->get(0)->id,
        ]);

        $this->assertDatabaseHas('collection_page', [
            'page_id' => $page->id,
            'collection_id' => $collections->get(1)->id,
        ]);

        $this->assertDatabaseHas('collection_page', [
            'page_id' => $page->id,
            'collection_id' => $collections->get(2)->id,
        ]);

        $this->assertDatabaseHas('collection_page', [
            'page_id' => $page->id,
            'collection_id' => $collections->get(3)->id,
        ]);

        $this->assertDatabaseHas('collection_page', [
            'page_id' => $page->id,
            'collection_id' => $collections->get(4)->id,
        ]);
    }

    /**
     * @test
     */
    public function updateLandingPageUpdateCollectionsAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->landingPage()->withCollections()->create();

        $pageCollectionIds = $page->collections()->pluck('id');

        $collectionIds = Collection::factory()->count(3)->create()->pluck('id');

        $collectionIds->push($pageCollectionIds->get(0));
        $collectionIds->push($pageCollectionIds->get(1));

        $data = [
            'collections' => $collectionIds->all(),
        ];
        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        //Then an update request should be created for the updated page
        $this->assertDatabaseHas((new UpdateRequest())->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::EXISTING_TYPE_PAGE,
            'updateable_id' => $page->id,
        ]);

        $updateRequest = UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::EXISTING_TYPE_PAGE)
            ->where('updateable_id', $page->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas('collection_page', [
            'page_id' => $page->id,
            'collection_id' => $collectionIds->get(0),
        ]);

        $this->assertDatabaseHas('collection_page', [
            'page_id' => $page->id,
            'collection_id' => $collectionIds->get(1),
        ]);

        $this->assertDatabaseHas('collection_page', [
            'page_id' => $page->id,
            'collection_id' => $collectionIds->get(2),
        ]);

        $this->assertDatabaseHas('collection_page', [
            'page_id' => $page->id,
            'collection_id' => $pageCollectionIds->get(0),
        ]);

        $this->assertDatabaseHas('collection_page', [
            'page_id' => $page->id,
            'collection_id' => $pageCollectionIds->get(1),
        ]);

        $this->assertDatabaseMissing('collection_page', [
            'page_id' => $page->id,
            'collection_id' => $pageCollectionIds->get(2),
        ]);
    }

    /**
     * @test
     */
    public function updatePageUpdateSlugAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->withImage()->withParent()->withChildren()->disabled()
            ->create([
                'title' => 'Test Page Title',
                'slug' => 'test-page-title',
            ]);

        $data = [
            'title' => 'New Title',
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest = UpdateRequest::find($response->json()['id']);

        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas(table(Page::class), [
            'id' => $page->id,
            'title' => 'New Title',
            'slug' => 'test-page-title',
        ]);

        $data = [
            'slug' => 'new-title',
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest = UpdateRequest::find($response->json()['id']);

        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas(table(Page::class), [
            'id' => $page->id,
            'title' => 'New Title',
            'slug' => 'new-title',
        ]);

        $response->assertJsonFragment([
        ]);

        Page::factory()->withImage()->withParent()->withChildren()->disabled()
            ->create([
                'title' => 'Existing Page',
                'slug' => 'existing-page',
            ]);

        $data = [
            'slug' => 'existing-page',
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Delete page
     */

    /**
     * @test
     */
    public function deletePageAsGuest401()
    {
        $page = Page::factory()->create();

        $response = $this->json('DELETE', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function deletePageAsServiceWorker403()
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);

        Passport::actingAs($user);

        $page = Page::factory()->create();

        $response = $this->json('DELETE', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertDatabaseHas('pages', ['id' => $page->id]);
    }

    /**
     * @test
     */
    public function deletePageAsServiceAdmin403()
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $page = Page::factory()->create();

        $response = $this->json('DELETE', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertDatabaseHas('pages', ['id' => $page->id]);
    }

    /**
     * @test
     */
    public function deletePageAsOrganisationAdmin403()
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($service->organisation);

        Passport::actingAs($user);

        $page = Page::factory()->create();

        $response = $this->json('DELETE', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertDatabaseHas('pages', ['id' => $page->id]);
    }

    /**
     * @test
     */
    public function deletePageAsGlobalAdmin403()
    {
        $user = User::factory()->create()->makeGlobalAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->create();

        $response = $this->json('DELETE', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertDatabaseHas('pages', ['id' => $page->id]);
    }

    /**
     * @test
     */
    public function deletePageAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create()->makeContentAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->create();

        $response = $this->json('DELETE', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
    }

    /**
     * @test
     */
    public function deletePageAsSuperAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create()->makeSuperAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->create();

        $response = $this->json('DELETE', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
    }

    /**
     * @test
     */
    public function auditCreatedOnDelete()
    {
        $this->fakeEvents();

        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->create();

        $this->json('DELETE', '/core/v1/pages/' . $page->id);

        Event::assertDispatched(EndpointHit::class, function (EndpointHit $event) {
            return $event->getAction() === Audit::ACTION_DELETE;
        });
    }

    /**
     * @test
     */
    public function deletePageWithChildrenAsContentAdmin422()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->withParent()->withChildren()->create();

        $parent = $page->parent;

        $children = $page->children()->defaultOrder()->get();

        $response = $this->json('DELETE', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertDatabaseHas('pages', ['id' => $page->id]);
        $this->assertDatabaseHas('pages', ['id' => $children->get(0)->id]);
        $this->assertDatabaseHas('pages', ['id' => $children->get(1)->id]);
        $this->assertDatabaseHas('pages', ['id' => $children->get(2)->id]);
        $this->assertDatabaseHas('pages', ['id' => $parent->id]);
    }

    /**
     * @test
     */
    public function deleteLandingPageWithChildrenAsContentAdmin422()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->landingPage()->withChildren()->create();

        $parent = $page->parent;

        $children = $page->children()->defaultOrder()->get();

        $response = $this->json('DELETE', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertDatabaseHas('pages', ['id' => $page->id]);
        $this->assertDatabaseHas('pages', ['id' => $children->get(0)->id]);
        $this->assertDatabaseHas('pages', ['id' => $children->get(1)->id]);
        $this->assertDatabaseHas('pages', ['id' => $children->get(2)->id]);
    }

    /**
     * @test
     */
    public function deletePageWithCollectionsAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->withCollections()->create();

        $pageCollectionIds = $page->collections()->pluck('id');

        $response = $this->json('DELETE', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
        foreach ($pageCollectionIds as $collectionId) {
            $this->assertDatabaseMissing('collection_page', ['page_id' => $page->id, 'collection_id' => $collectionId]);
            $this->assertDatabaseHas('collections', ['id' => $collectionId]);
        }
    }

    /**
     * @test
     */
    public function deleteLandingPageWithCollectionsAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->landingPage()->withCollections()->create();

        $pageCollectionIds = $page->collections()->pluck('id');

        $response = $this->json('DELETE', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
        foreach ($pageCollectionIds as $collectionId) {
            $this->assertDatabaseMissing('collection_page', ['page_id' => $page->id, 'collection_id' => $collectionId]);
            $this->assertDatabaseHas('collections', ['id' => $collectionId]);
        }
    }

    /**
     * @test
     */
    public function deletePageWithImageAsContentAdmin200()
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->withImage()->create();

        $imageId = $page->image_file_id;

        $response = $this->json('DELETE', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
        $this->assertDatabaseMissing('files', ['id' => $imageId]);
    }
}
