<?php

namespace App\Http\Controllers\Core\V1;

use App\Events\EndpointHit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\IndexRequest;
use App\Http\Requests\Setting\UpdateRequest;
use App\Models\File;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    /**
     * SettingController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:api')->except('index');
    }

    public function index(IndexRequest $request): Setting
    {
        event(EndpointHit::onRead($request, 'Viewed all settings'));

        return new Setting;
    }

    public function update(UpdateRequest $request): Setting
    {
        return DB::transaction(function () use ($request) {
            Setting::cms()
                ->update([
                    'value' => [
                        'frontend' => [
                            'global' => [
                                'footer_title' => $request->input('cms.frontend.global.footer_title'),
                                'footer_content' => sanitize_markdown($request->input('cms.frontend.global.footer_content')),
                                'contact_phone' => $request->input('cms.frontend.global.contact_phone'),
                                'contact_email' => $request->input('cms.frontend.global.contact_email'),
                                'facebook_handle' => $request->input('cms.frontend.global.facebook_handle') ?? '',
                                'twitter_handle' => $request->input('cms.frontend.global.twitter_handle') ?? '',
                            ],
                            'home' => [
                                'banners' => array_map(function ($banner) {
                                    $banner['content'] = sanitize_markdown($banner['content']);

                                    return $banner;
                                }, $request->input('cms.frontend.home.banners', [])),
                            ],
                            'terms_and_conditions' => [
                                'title' => $request->input('cms.frontend.terms_and_conditions.title'),
                                'content' => sanitize_markdown($request->input('cms.frontend.terms_and_conditions.content')),
                            ],
                            'privacy_policy' => [
                                'title' => $request->input('cms.frontend.privacy_policy.title'),
                                'content' => sanitize_markdown($request->input('cms.frontend.privacy_policy.content')),
                            ],
                            'cookie_policy' => [
                                'title' => $request->input('cms.frontend.cookie_policy.title'),
                                'content' => sanitize_markdown($request->input('cms.frontend.cookie_policy.content')),
                            ],
                            'accessibility_statement' => [
                                'title' => $request->input('cms.frontend.accessibility_statement.title'),
                                'content' => sanitize_markdown($request->input('cms.frontend.accessibility_statement.content')),
                            ],
                            'about' => [
                                'title' => $request->input('cms.frontend.about.title'),
                                'content' => sanitize_markdown($request->input('cms.frontend.about.content')),
                                'video_url' => $request->input('cms.frontend.about.video_url'),
                            ],
                            'contact' => [
                                'title' => $request->input('cms.frontend.contact.title'),
                                'content' => sanitize_markdown($request->input('cms.frontend.contact.content')),
                            ],
                            'get_involved' => [
                                'title' => $request->input('cms.frontend.get_involved.title'),
                                'content' => sanitize_markdown($request->input('cms.frontend.get_involved.content')),
                            ],
                            'favourites' => [
                                'title' => $request->input('cms.frontend.favourites.title'),
                                'content' => sanitize_markdown($request->input('cms.frontend.favourites.content')),
                            ],
                            'banner' => [
                                'title' => $request->input('cms.frontend.banner.title'),
                                'content' => $request->filled('cms.frontend.banner.content')
                                ? sanitize_markdown($request->input('cms.frontend.banner.content'))
                                : null,
                                'button_text' => $request->input('cms.frontend.banner.button_text'),
                                'button_url' => $request->input('cms.frontend.banner.button_url'),
                                'image_file_id' => $request->input(
                                    'cms.frontend.banner.image_file_id',
                                    Setting::cms()->value['frontend']['banner']['image_file_id']
                                ),
                            ],
                        ],
                    ],
                ]);

            if ($request->filled('cms.frontend.banner.image_file_id')) {
                /** @var File $file */
                $file = File::query()
                    ->findOrFail($request->input('cms.frontend.banner.image_file_id'))
                    ->assigned();

                // Create resized version for common dimensions.
                foreach (config('local.cached_image_dimensions') as $maxDimension) {
                    $file->resizedVersion($maxDimension);
                }
            }

            event(EndpointHit::onUpdate($request, 'Updated settings'));

            return new Setting;
        });
    }
}
