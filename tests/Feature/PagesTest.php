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
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->faker = Faker::create('en_GB');
    }

    /**
     * @test
     */
    public function list_enabled_pages_as_guest200(): void
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
                    'excerpt',
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
    public function audit_created_on_list(): void
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
    public function list_mixed_state_pages_as_guest200(): void
    {
        $page = Page::factory()->withImage()->withParent()->disabled()
            ->create();

        $landingPage = Page::factory()->withImage()->landingPage()
            ->create();

        $topicPage = Page::factory()->withImage()->topicPage()
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
            'id' => $topicPage->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page->id,
        ]);
    }

    /**
     * @test
     */
    public function list_mixed_state_pages_as_global_admin403(): void
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

        $topicPage = Page::factory()->withImage()->topicPage()
            ->create();

        $response = $this->json('GET', '/core/v1/pages/index');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function list_mixed_state_pages_as_content_admin200(): void
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

        $topicPage = Page::factory()->withImage()->topicPage()
            ->create();

        $response = $this->json('GET', '/core/v1/pages/index');

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonCount(4, 'data');
        $response->assertJsonFragment([
            'id' => $page->parent->id,
        ]);
        $response->assertJsonFragment([
            'id' => $landingPage->id,
        ]);
        $response->assertJsonFragment([
            'id' => $topicPage->id,
        ]);
        $response->assertJsonFragment([
            'id' => $page->id,
        ]);
    }

    /**
     * @test
     */
    public function list_mixed_state_pages_as_guest_filter_by_i_d200(): void
    {
        $pages = Page::factory()->count(2)->create();
        $disabledPage = Page::factory()->disabled()->create();
        $landingPages = Page::factory()->count(2)->landingPage()->create();
        $disabledLandingPage = Page::factory()->landingPage()->disabled()->create();
        $topicPages = Page::factory()->count(2)->topicPage()->create();
        $disabledTopicPage = Page::factory()->topicPage()->disabled()->create();

        $ids = [
            $pages->get(1)->id,
            $disabledPage->id,
            $landingPages->get(1)->id,
            $disabledLandingPage->id,
            $topicPages->get(1)->id,
            $disabledTopicPage->id,
        ];

        $response = $this->json('GET', '/core/v1/pages/index?filter[id]=' . implode(',', $ids));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonFragment([
            'id' => $pages->get(1)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $landingPages->get(1)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $topicPages->get(1)->id,
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
        $response->assertJsonMissing([
            'id' => $topicPages->get(0)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $disabledTopicPage->id,
        ]);
    }

    /**
     * @test
     */
    public function list_mixed_state_pages_as_content_admin_filter_by_i_d200(): void
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
        $topicPages = Page::factory()->count(2)->topicPage()->create();
        $disabledTopicPage = Page::factory()->topicPage()->disabled()->create();

        $ids = [
            $pages->get(1)->id,
            $disabledPage->id,
            $landingPages->get(1)->id,
            $disabledLandingPage->id,
            $topicPages->get(1)->id,
            $disabledTopicPage->id,
        ];

        $response = $this->json('GET', '/core/v1/pages/index?filter[id]=' . implode(',', $ids));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(6, 'data');
        $response->assertJsonFragment([
            'id' => $pages->get(1)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $landingPages->get(1)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $topicPages->get(1)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $pages->get(0)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $landingPages->get(0)->id,
        ]);
        $response->assertJsonMissing([
            'id' => $topicPages->get(0)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $disabledPage->id,
        ]);
        $response->assertJsonFragment([
            'id' => $disabledLandingPage->id,
        ]);
        $response->assertJsonFragment([
            'id' => $disabledTopicPage->id,
        ]);
    }

    /**
     * @test
     */
    public function list_pages_as_guest_filter_by_parent_i_d200(): void
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
    public function list_landing_page_child_pages_as_guest_filter_by_parent_i_d200(): void
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
    public function list_topic_page_child_pages_as_guest_filter_by_parent_i_d200(): void
    {
        $topicPage = Page::factory()->topicPage()->withChildren(Page::PAGE_TYPE_LANDING)->create();

        $response = $this->json('GET', '/core/v1/pages/index?filter[parent_id]=' . $topicPage->id);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonFragment([
            'id' => $topicPage->children->get(0)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $topicPage->children->get(1)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $topicPage->children->get(2)->id,
        ]);
        $response->assertJsonFragment([
            'id' => $topicPage->id,
        ]);
    }

    /**
     * @test
     */
    public function list_pages_as_guest_filter_by_title200(): void
    {
        $page1 = Page::factory()->create(['title' => 'Page One']);
        $page2 = Page::factory()->create(['title' => 'Second Page']);
        $page3 = Page::factory()->create(['title' => 'Third']);
        $page4 = Page::factory()->create(['title' => 'Page the Fourth']);
        $page5 = Page::factory()->create(['title' => 'Final']);
        $landingPage = Page::factory()->landingPage()->create(['title' => 'Landing Page']);
        $topicPage = Page::factory()->topicPage()->create(['title' => 'Topic Page']);

        $response = $this->json('GET', '/core/v1/pages/index?filter[title]=page');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(5, 'data');
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
        $response->assertJsonFragment([
            'id' => $topicPage->id,
        ]);
    }

    /**
     * @test
     */
    public function list_mixed_state_pages_as_guest_filter_by_landing_page200(): void
    {
        $page1 = Page::factory()->landingPage()->create();
        $page2 = Page::factory()->disabled()->create();
        $page3 = Page::factory()->create();
        $page4 = Page::factory()->landingPage()->disabled()->create();
        $page5 = Page::factory()->topicPage()->create();
        $page6 = Page::factory()->topicPage()->disabled()->create();

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
        $response->assertJsonMissing([
            'id' => $page5->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page6->id,
        ]);
    }

    /**
     * @test
     */
    public function list_mixed_state_pages_as_guest_filter_by_topic_page200(): void
    {
        $page1 = Page::factory()->topicPage()->create();
        $page2 = Page::factory()->disabled()->create();
        $page3 = Page::factory()->create();
        $page4 = Page::factory()->topicPage()->disabled()->create();

        $response = $this->json('GET', '/core/v1/pages/index?filter[page_type]=topic');

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
    public function list_mixed_state_pages_as_guest_filter_by_information_page200(): void
    {
        $page1 = Page::factory()->landingPage()->create();
        $page2 = Page::factory()->disabled()->create();
        $page3 = Page::factory()->create();
        $page4 = Page::factory()->landingPage()->disabled()->create();
        $page5 = Page::factory()->topicPage()->create();
        $page6 = Page::factory()->topicPage()->disabled()->create();

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
        $response->assertJsonMissing([
            'id' => $page5->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page6->id,
        ]);
    }

    /**
     * @test
     */
    public function list_mixed_state_pages_as_content_admin_filter_by_landing_page200(): void
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
        $page5 = Page::factory()->topicPage()->create();
        $page6 = Page::factory()->topicPage()->disabled()->create();

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
        $response->assertJsonMissing([
            'id' => $page5->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page6->id,
        ]);
    }

    /**
     * @test
     */
    public function list_mixed_state_pages_as_admin_filter_by_information_page200(): void
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
        $page5 = Page::factory()->topicPage()->create();
        $page6 = Page::factory()->topicPage()->disabled()->create();

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
        $response->assertJsonMissing([
            'id' => $page5->id,
        ]);
        $response->assertJsonMissing([
            'id' => $page6->id,
        ]);
    }

    /**
     * @test
     */
    public function list_pages_as_guest_filter_by_title_and_page_type200(): void
    {
        $page1 = Page::factory()->create(['title' => 'Page One']);
        $page2 = Page::factory()->create(['title' => 'Second Page']);
        $page3 = Page::factory()->create(['title' => 'Third']);
        $landingPage1 = Page::factory()->landingPage()->create(['title' => 'Landing Page One']);
        $landingPage2 = Page::factory()->landingPage()->create(['title' => 'Landing Two']);
        $landingPage3 = Page::factory()->landingPage()->create(['title' => 'Landing Three']);
        $topicPage1 = Page::factory()->topicPage()->create(['title' => 'Topic Page One']);
        $topicPage2 = Page::factory()->topicPage()->create(['title' => 'Topic Two']);
        $topicPage3 = Page::factory()->topicPage()->create(['title' => 'Topic Three']);

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
        $response->assertJsonMissing([
            'id' => $topicPage1->id,
        ]);
        $response->assertJsonMissing([
            'id' => $topicPage2->id,
        ]);
        $response->assertJsonMissing([
            'id' => $topicPage3->id,
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
        $response->assertJsonMissing([
            'id' => $topicPage1->id,
        ]);
        $response->assertJsonMissing([
            'id' => $topicPage2->id,
        ]);
        $response->assertJsonMissing([
            'id' => $topicPage3->id,
        ]);

        $response = $this->json('GET', '/core/v1/pages/index?filter[title]=page&filter[page_type]=topic');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'id' => $topicPage1->id,
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
            'id' => $landingPage1->id,
        ]);
        $response->assertJsonMissing([
            'id' => $landingPage2->id,
        ]);
        $response->assertJsonMissing([
            'id' => $landingPage3->id,
        ]);
        $response->assertJsonMissing([
            'id' => $topicPage2->id,
        ]);
        $response->assertJsonMissing([
            'id' => $topicPage3->id,
        ]);
    }

    /**
     * @test
     */
    public function list_enabled_pages_as_guest_include_parent200(): void
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
                    'excerpt',
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
    public function list_enabled_pages_as_guest_include_landing_page200(): void
    {
        Page::factory()->withParent()->withChildren()->create();

        $response = $this->json('GET', '/core/v1/pages/index?include=landingPageAncestors');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'slug',
                    'title',
                    'excerpt',
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
    public function list_enabled_landing_page_as_guest_include_topic_page200(): void
    {
        Page::factory()->topicPage()->withChildren(Page::PAGE_TYPE_LANDING)->create();

        $response = $this->json('GET', '/core/v1/pages/index?include=topicPageAncestors');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'slug',
                    'title',
                    'excerpt',
                    'order',
                    'enabled',
                    'page_type',
                    'image',
                    'topic_page',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function list_enabled_pages_as_guest_include_children200(): void
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
                    'excerpt',
                    'order',
                    'enabled',
                    'page_type',
                    'image',
                    'children' => [
                        '*' => [
                            'id',
                            'slug',
                            'title',
                            'order',
                            'enabled',
                            'page_type',
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
    public function list_landing_page_descendants_as_guest_include_parent200(): void
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
                    'excerpt',
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
    public function create_page_as_guest401(): void
    {
        $parentPage = Page::factory()->create();

        $data = [
            'title' => 'A New Page',
            'slug' => 'a-new-page',
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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
    public function create_page_as_service_worker403(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceWorker($service);
        $parentPage = Page::factory()->create();

        Passport::actingAs($user);

        $data = [
            'title' => 'A New Page',
            'slug' => 'a-new-page',
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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
    public function create_page_as_service_admin403(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);
        $parentPage = Page::factory()->create();

        Passport::actingAs($user);

        $data = [
            'title' => 'A New Page',
            'slug' => 'a-new-page',
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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
    public function create_page_as_organisation_admin403(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create()->makeOrganisationAdmin($service->organisation);
        $parentPage = Page::factory()->create();

        Passport::actingAs($user);

        $data = [
            'title' => 'A New Page',
            'slug' => 'a-new-page',
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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
    public function create_page_as_global_admin403(): void
    {
        $user = User::factory()->create()->makeGlobalAdmin();
        $parentPage = Page::factory()->create();

        Passport::actingAs($user);

        $data = [
            'title' => 'A New Page',
            'slug' => 'a-new-page',
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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
    public function create_page_as_content_admin200(): void
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
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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

        // Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest)->getTable(), [
            'user_id' => $user->id,
            'updateable_type' => UpdateRequest::NEW_TYPE_PAGE,
            'updateable_id' => null,
        ]);

        $updateRequest = UpdateRequest::find($response->json('id'));

        $data['page_type'] = Page::PAGE_TYPE_INFORMATION;
        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas((new Page)->getTable(), [
            'title' => 'A New Page',
            'slug' => 'a-new-page',
            'excerpt' => $data['excerpt'],
            'parent_uuid' => $data['parent_id'],
        ]);
    }

    /**
     * @test
     */
    public function create_page_as_super_admin201(): void
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
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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
            'ancestors',
            'collection_categories',
            'collection_personas',
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * @test
     */
    public function create_information_page_with_minimal_data_as_content_admin200(): void
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

        // Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest)->getTable(), [
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

        $this->assertDatabaseHas((new Page)->getTable(), [
            'title' => $data['title'],
            'slug' => Str::slug($data['title']),
            'parent_uuid' => $data['parent_id'],
        ]);
    }

    /**
     * @test
     */
    public function create_landing_page_with_minimal_data_as_content_admin200(): void
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

        // Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest)->getTable(), [
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

        $this->assertDatabaseHas((new Page)->getTable(), [
            'title' => $data['title'],
            'slug' => Str::slug($data['title']),
            'parent_uuid' => null,
        ]);
    }

    /**
     * @test
     */
    public function create_topic_page_with_minimal_data_as_content_admin200(): void
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
            'page_type' => Page::PAGE_TYPE_TOPIC,
        ];

        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_OK);

        // Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest)->getTable(), [
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

        $this->assertDatabaseHas((new Page)->getTable(), [
            'title' => $data['title'],
            'slug' => Str::slug($data['title']),
            'parent_uuid' => null,
        ]);
    }

    /**
     * @test
     */
    public function create_page_audit_created(): void
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
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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
    public function create_page_as_content_admin_with_invalid_data422(): void
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

        // Exceeds max characters for 'copy' content type
        $this->json('POST', '/core/v1/pages', [
            'title' => $this->faker->sentence(),
            'excerpt' => str_pad($this->faker->paragraph(2), 151, 'words '),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => implode(' ', array_fill(0, config('local.page_copy_max_chars') + 1, 'test')),
                        ],
                    ],
                ],
            ],
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Invalid parent id
        $this->json('POST', '/core/v1/pages', [
            'title' => $this->faker->sentence(),
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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
    public function create_child_page_as_content_admin200(): void
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
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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

        // Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest)->getTable(), [
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

        $this->assertDatabaseHas((new Page)->getTable(), [
            'title' => $data['title'],
            'slug' => Str::slug($data['title']),
            'parent_uuid' => $data['parent_id'],
        ]);
    }

    /**
     * @test
     */
    public function create_child_page_inherit_parent_status_as_content_admin200(): void
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
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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

        // Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest)->getTable(), [
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

        $this->assertDatabaseHas((new Page)->getTable(), [
            'title' => $data['title'],
            'slug' => Str::slug($data['title']),
            'parent_uuid' => $data['parent_id'],
            'enabled' => false,
        ]);
    }

    /**
     * @test
     */
    public function create_information_page_root_as_content_admin200(): void
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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

        // Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest)->getTable(), [
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

        $this->assertDatabaseHas((new Page)->getTable(), [
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
    public function create_landing_page_as_content_admin200(): void
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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

        // Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest)->getTable(), [
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

        $this->assertDatabaseHas((new Page)->getTable(), [
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
    public function create_page_after_sibling_as_content_admin200(): void
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
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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

        // Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest)->getTable(), [
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
    public function create_first_child_page_as_content_admin200(): void
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
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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

        // Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest)->getTable(), [
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
    public function create_page_with_image_png_as_content_admin200(): void
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
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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

        // Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest)->getTable(), [
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

        $this->assertDatabaseHas((new Page)->getTable(), [
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
    public function create_page_with_image_jpg_as_content_admin200(): void
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
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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

        // Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest)->getTable(), [
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
        $data['excerpt'] = trim($data['excerpt']);
        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas((new Page)->getTable(), [
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
    public function create_page_with_image_svg_as_content_admin200(): void
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
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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

        // Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest)->getTable(), [
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

        $this->assertDatabaseHas((new Page)->getTable(), [
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
    public function create_information_page_with_collections_as_content_admin422(): void
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
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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
    public function create_landing_page_with_collections_as_content_admin200(): void
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
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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

        // Then an update request should be created for the new page
        $this->assertDatabaseHas((new UpdateRequest)->getTable(), [
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
    public function create_information_page_with_call_to_action_as_content_admin422(): void
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $parentPage = Page::factory()->create();

        // Missing title
        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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

        // Missing description
        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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

        // Missing button text
        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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

        // Invalid URL
        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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
    public function create_information_page_with_call_to_action_as_content_admin200(): void
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
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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
    public function create_information_page_with_video_as_content_admin200(): void
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
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                        [
                            'type' => 'video',
                            'title' => $this->faker->sentence(),
                            'url' => 'https://www.youtube.com/watch?v=dummy_id',
                        ],
                    ],
                ],
            ],
            'parent_id' => $parentPage->id,
            'page_type' => 'information',
        ];
        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonFragment($data);
    }

    /**
     * @test
     */
    public function create_landing_page_with_call_to_actions_as_content_admin200(): void
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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
    public function create_landing_page_with_videos_as_content_admin200(): void
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $data = [
            'title' => $this->faker->sentence(),
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => $this->faker->realText(),
                        ],
                        [
                            'type' => 'video',
                            'title' => $this->faker->sentence(),
                            'url' => 'https://www.youtube.com/watch?v=dummy_id',
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
                            'type' => 'video',
                            'title' => $this->faker->sentence(),
                            'url' => 'https://www.youtube.com/watch?v=dummy_id',
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
                            'type' => 'video',
                            'title' => $this->faker->sentence(),
                            'url' => 'https://www.youtube.com/watch?v=dummy_id',
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

        $response->assertJsonFragment($data);
    }

    /**
     * @test
     */
    public function create_page_with_same_title_as_existing_page_increments_slug_as_content_admin200(): void
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
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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

        $updateRequest = UpdateRequest::find($response->json('id'));

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas((new Page)->getTable(), [
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

        $this->assertDatabaseHas((new Page)->getTable(), [
            'title' => $data['title'],
            'parent_uuid' => $parentPage->id,
            'slug' => 'test-page-title-2',
        ]);
    }

    /**
     * @test
     */
    public function create_page_with_slug_as_content_admin201(): void
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
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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

        $this->assertDatabaseHas((new Page)->getTable(), [
            'parent_uuid' => $parentPage->id,
            'slug' => 'different-slug',
        ]);
    }

    /**
     * @test
     */
    public function create_multiple_pages_with_same_slug_as_content_admin201(): void
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
            'slug' => 'page-slug',
            'excerpt' => trim(substr($this->faker->paragraph(2), 0, 149)),
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

        $updateRequest1 = UpdateRequest::find($response->json('id'));

        $this->assertEquals('page-slug', $updateRequest1->data['slug']);

        $response = $this->json('POST', '/core/v1/pages', $data);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest2 = UpdateRequest::find($response->json('id'));

        $this->assertEquals('page-slug', $updateRequest2->data['slug']);

        $response = $this->approveUpdateRequest($updateRequest1->id);

        $this->assertDatabaseHas((new Page)->getTable(), [
            'id' => $response['updateable_id'],
            'slug' => 'page-slug',
        ]);

        $response = $this->approveUpdateRequest($updateRequest2->id);
    }

    /**
     * Get a single page
     */

    /**
     * @test
     */
    public function get_enabled_information_page_as_guest200(): void
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
                'alt_text',
                'url',
            ],
            'landing_page',
            'children' => [
                '*' => [
                    'id',
                    'slug',
                    'title',
                    'excerpt',
                    'page_type',
                    'enabled',
                    'order',
                ],
            ],
            'ancestors' => [
                '*' => [
                    'id',
                    'slug',
                    'title',
                    'excerpt',
                    'page_type',
                    'enabled',
                    'order',
                ],
            ],
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * @test
     */
    public function get_enabled_landing_page_as_guest200(): void
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
                'alt_text',
                'url',
            ],
            'landing_page',
            'parent',
            'children' => [
                '*' => [
                    'id',
                    'slug',
                    'title',
                    'excerpt',
                    'page_type',
                    'enabled',
                    'order',
                ],
            ],
            'ancestors',
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * @test
     */
    public function get_information_page_by_slug_as_guest200(): void
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
    public function get_enabled_information_page_with_ancestors_as_guest200(): void
    {
        $page = Page::factory()->withImage()->withChildren()->create();
        $parent = Page::factory()->create([
            'title' => 'Parent',
        ]);
        $parent->appendNode($page);
        $landingPage = Page::factory()->landingPage()->create([
            'title' => 'Landing Page',
        ]);
        $landingPage->appendNode($parent);

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
                'alt_text',
                'url',
            ],
            'children' => [
                '*' => [
                    'id',
                    'slug',
                    'title',
                    'excerpt',
                    'page_type',
                    'enabled',
                    'order',
                ],
            ],
            'ancestors' => [
                '*' => [
                    'id',
                    'slug',
                    'title',
                    'excerpt',
                    'page_type',
                    'enabled',
                    'order',
                ],
            ],
            'created_at',
            'updated_at',
        ]);

        $this->assertEquals($landingPage->id, $response->json('data')['ancestors'][0]['id']);
        $this->assertEquals($parent->id, $response->json('data')['ancestors'][1]['id']);
    }

    /**
     * @test
     */
    public function audit_created_on_show(): void
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
    public function get_disabled_page_as_guest403(): void
    {
        $page = Page::factory()->withImage()->withParent()->withChildren()->disabled()
            ->create();

        $response = $this->json('GET', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function get_disabled_page_as_content_admin200(): void
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
                'alt_text',
                'url',
            ],
            'landing_page',
            'children' => [
                '*' => [
                    'id',
                    'slug',
                    'title',
                    'excerpt',
                    'page_type',
                    'enabled',
                    'order',
                ],
            ],
            'ancestors' => [
                '*' => [
                    'id',
                    'slug',
                    'title',
                    'excerpt',
                    'page_type',
                    'enabled',
                    'order',
                ],
            ],
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * @test
     */
    public function get_enabled_page_image_png_as_guest200(): void
    {
        $image = File::factory()->imagePng()->create();

        $page = Page::factory()->create([
            'image_file_id' => $image->id,
        ]);

        $response = $this->json('GET', '/core/v1/pages/' . $page->id . '/image.png');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(Storage::disk('local')->get('/test-data/image.png'), $response->content());
    }

    /**
     * @test
     */
    public function get_enabled_page_image_jpg_as_guest200(): void
    {
        $image = File::factory()->imageJpg()->create();

        $page = Page::factory()->create([
            'image_file_id' => $image->id,
        ]);

        $response = $this->json('GET', '/core/v1/pages/' . $page->id . '/image.jpg');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(Storage::disk('local')->get('/test-data/image.jpg'), $response->content());
    }

    /**
     * @test
     */
    public function get_enabled_page_image_svg_as_guest200(): void
    {
        $image = File::factory()->imageSvg()->create();

        $page = Page::factory()->create([
            'image_file_id' => $image->id,
        ]);

        $response = $this->json('GET', '/core/v1/pages/' . $page->id . '/image.svg');
        $response->assertStatus(Response::HTTP_OK);
        $this->assertEquals(Storage::disk('local')->get('/test-data/image.svg'), $response->content());
    }

    /**
     * Update page
     */

    /**
     * @test
     */
    public function update_page_as_guest401(): void
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
    public function update_page_as_service_worker403(): void
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
    public function update_page_as_service_admin403(): void
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
    public function update_page_as_organisation_admin403(): void
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
    public function update_page_as_global_admin403(): void
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
    public function update_page_as_content_admin200(): void
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create()->makeContentAdmin();

        Passport::actingAs($user);

        $page = Page::factory()
            ->withImage()
            ->withParent()
            ->withChildren()
            ->disabled()
            ->create();

        $data = [
            'title' => 'New Title',
            'enabled' => true,
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
                        [
                            'type' => 'video',
                            'title' => $this->faker->sentence(),
                            'url' => 'https://www.youtube.com/watch?v=dummy_id',
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        // Then an update request should be created for the updated page
        $this->assertDatabaseHas((new UpdateRequest)->getTable(), [
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

        $this->assertDatabaseHas(table(Page::class), [
            'id' => $page->id,
            'title' => $page->title,
            'enabled' => false,
        ]);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas(table(Page::class), [
            'id' => $page->id,
            'title' => 'New Title',
            'enabled' => true,
        ]);

        $response = $this->json('GET', '/core/v1/pages/' . $page->id);

        $response->assertJsonFragment($data);
    }

    /**
     * @test
     */
    public function update_page_as_super_admin200(): void
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeSuperAdmin();

        Passport::actingAs($user);

        $page = Page::factory()
            ->withImage()
            ->withParent()
            ->withChildren()
            ->disabled()
            ->create();

        $payload = [
            'title' => 'New Title',
            'enabled' => true,
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $payload);

        $response->assertStatus(Response::HTTP_OK);

        // The organisation event is updated
        $this->assertDatabaseHas((new Page)->getTable(), array_merge(['id' => $page->id], $payload));

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
    public function update_page_audit_created(): void
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
    public function update_page_as_admin_with_invalid_data422(): void
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
            'page_type' => Page::PAGE_TYPE_TOPIC,
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
    public function update_page_add_image_as_content_admin200(): void
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

        // Then an update request should be created for the updated page
        $this->assertDatabaseHas((new UpdateRequest)->getTable(), [
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
    public function update_page_remove_image_as_content_admin200(): void
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

        // Then an update request should be created for the updated page
        $this->assertDatabaseHas((new UpdateRequest)->getTable(), [
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
    public function update_page_change_image_as_content_admin200(): void
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $imageJpg = File::factory()->imageJpg()->pendingAssignment()->create();

        $imagePng = File::factory()->imagePng()->pendingAssignment()->create();

        $page = Page::factory()->withParent()->withChildren()->disabled()
            ->create([
                'image_file_id' => $imageJpg->id,
            ]);

        $data = [
            'image_file_id' => $imagePng->id,
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        // Then an update request should be created for the updated page
        $this->assertDatabaseHas((new UpdateRequest)->getTable(), [
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
    public function update_page_change_parent_as_content_admin200(): void
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

        // Then an update request should be created for the updated page
        $this->assertDatabaseHas((new UpdateRequest)->getTable(), [
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
    public function update_page_change_parent_inherit_status_as_content_admin200(): void
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

        // Then an update request should be created for the updated page
        $this->assertDatabaseHas((new UpdateRequest)->getTable(), [
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
    public function update_page_change_page_type_as_content_admin200(): void
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

        $response->assertStatus(Response::HTTP_OK);

        $data = [
            'page_type' => Page::PAGE_TYPE_LANDING,
            'parent_id' => null,
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        // Then an update request should be created for the updated page
        $this->assertDatabaseHas((new UpdateRequest)->getTable(), [
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
    public function update_page_change_order_as_content_admin200(): void
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
    public function update_page_enable_page_as_content_admin200(): void
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create()->makeContentAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->disabled()->create();

        $data = [
            'enabled' => true,
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest = UpdateRequest::find($response->json()['id']);

        $this->assertEquals($data, $updateRequest->data);

        $this->assertFalse($page->fresh()->enabled);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertTrue($page->fresh()->enabled);

        $this->assertDatabaseHas(table(Page::class), [
            'id' => $page->id,
            'enabled' => true,
        ]);
    }

    /**
     * @test
     */
    public function update_page_disabled_cascadesto_child_pages_as_content_admin200(): void
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
    public function update_information_page_add_collections_as_content_admin422(): void
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
    public function update_landing_page_add_collections_as_content_admin200(): void
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
    public function update_landing_page_update_collections_as_content_admin200(): void
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

        // Then an update request should be created for the updated page
        $this->assertDatabaseHas((new UpdateRequest)->getTable(), [
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
    public function update_page_update_slug_as_content_admin200(): void
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeContentAdmin();

        Passport::actingAs($user);

        $page1 = Page::factory()->create([
            'slug' => 'page-slug',
        ]);
        $page2 = Page::factory()->create([
            'slug' => 'page-slug-1',
        ]);
        $page3 = Page::factory()->create([
            'slug' => 'other-slug',
        ]);

        $data = [
            'slug' => 'page-slug',
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page3->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest = UpdateRequest::find($response->json()['id']);

        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas(table(Page::class), [
            'id' => $page3->id,
            'slug' => 'page-slug-2',
        ]);

        $data = [
            'slug' => 'page-slug',
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page2->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest = UpdateRequest::find($response->json()['id']);

        $this->assertEquals($data, $updateRequest->data);

        $this->approveUpdateRequest($updateRequest->id);

        $this->assertDatabaseHas(table(Page::class), [
            'id' => $page2->id,
            'slug' => 'page-slug-1',
        ]);
    }

    /**
     * @test
     */
    public function update_page_with_conflicting_update_requests_as_content_admin200(): void
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create()->makeContentAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->create();

        $data1 = [
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => 'Updated text 1',
                        ],
                    ],
                ],
            ],
        ];

        $response1 = $this->json('PUT', '/core/v1/pages/' . $page->id, $data1);

        $response1->assertStatus(Response::HTTP_OK);

        $updateRequest1 = UpdateRequest::find($response1->json()['id']);

        $this->assertEquals($data1, $updateRequest1->data);

        $data2 = [
            'title' => 'New page title',
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'video',
                            'title' => 'Updated video title',
                            'url' => 'https://www.youtube.com/watch?v=dummy_id',
                        ],
                    ],
                ],
            ],
        ];

        $response2 = $this->json('PUT', '/core/v1/pages/' . $page->id, $data2);

        $response2->assertStatus(Response::HTTP_OK);

        $updateRequest2 = UpdateRequest::find($response2->json()['id']);

        $this->assertEquals($data2, $updateRequest2->data);

        $data3 = [
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => 'Updated text 3',
                        ],
                    ],
                ],
            ],
        ];

        $response3 = $this->json('PUT', '/core/v1/pages/' . $page->id, $data3);

        $response3->assertStatus(Response::HTTP_OK);

        $updateRequest3 = UpdateRequest::find($response3->json()['id']);

        $this->assertEquals($data3, $updateRequest3->data);

        $updateRequest1->refresh();

        $this->assertTrue($updateRequest1->trashed());

        $updateRequest2->refresh();

        $this->assertEquals(['title' => 'New page title'], $updateRequest2->data);

        $updateRequest3->refresh();

        $this->assertEquals($data3, $updateRequest3->data);
    }

    /**
     * Delete page
     */

    /**
     * @test
     */
    public function delete_page_as_guest401(): void
    {
        $page = Page::factory()->create();

        $response = $this->json('DELETE', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function delete_page_as_service_worker403(): void
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
    public function delete_page_as_service_admin403(): void
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
    public function delete_page_as_organisation_admin403(): void
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
    public function delete_page_as_global_admin403(): void
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
    public function delete_page_as_content_admin403(): void
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create()->makeContentAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->create();

        $response = $this->json('DELETE', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * @test
     */
    public function delete_page_as_super_admin200(): void
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
    public function delete_page_audit_created(): void
    {
        $this->fakeEvents();

        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeSuperAdmin();

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
    public function delete_page_with_children_as_super_admin422(): void
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeSuperAdmin();

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
    public function delete_landing_page_with_children_as_super_admin422(): void
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeSuperAdmin();

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
    public function delete_page_with_collections_as_super_admin200(): void
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeSuperAdmin();

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
    public function delete_landing_page_with_collections_as_super_admin200(): void
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeSuperAdmin();

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
    public function delete_page_with_image_as_super_admin200(): void
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create();
        $user->makeSuperAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->withImage()->create();

        $imageId = $page->image_file_id;

        $response = $this->json('DELETE', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
        $this->assertDatabaseMissing('files', ['id' => $imageId]);
    }

    /**
     * @test
     */
    public function delete_page_with_update_requests_as_super_admin200(): void
    {
        /**
         * @var \App\Models\User $user
         */
        $user = User::factory()->create()->makeContentAdmin();

        Passport::actingAs($user);

        $page = Page::factory()->create();

        $data = [
            'content' => [
                'introduction' => [
                    'content' => [
                        [
                            'type' => 'copy',
                            'value' => 'Updated text',
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->json('PUT', '/core/v1/pages/' . $page->id, $data);

        $response->assertStatus(Response::HTTP_OK);

        $updateRequest = UpdateRequest::find($response->json()['id']);

        $this->assertEquals($data, $updateRequest->data);

        Passport::actingAs(User::factory()->create()->makeSuperAdmin());

        $response = $this->json('DELETE', '/core/v1/pages/' . $page->id);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
        $this->assertDatabaseMissing('update_requests', ['id' => $updateRequest->id, 'deleted_at' => null]);
    }
}
