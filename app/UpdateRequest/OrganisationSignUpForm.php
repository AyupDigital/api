<?php

namespace App\UpdateRequest;

use App\Contracts\AppliesUpdateRequests;
use App\Http\Requests\OrganisationSignUpForm\StoreRequest as StoreOrganisationSignUpFormRequest;
use App\Models\Organisation;
use App\Models\Service;
use App\Models\UpdateRequest;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Validator as ValidatorFacade;

class OrganisationSignUpForm implements AppliesUpdateRequests
{
    /**
     * Check if the update request is valid.
     */
    public function validateUpdateRequest(UpdateRequest $updateRequest): Validator
    {
        $rules = (new StoreOrganisationSignUpFormRequest)
            ->merge($updateRequest->data)
            ->rules();

        // Update rules for hashed password instead of raw.
        $rules['user.password'] = ['required', 'string'];

        // Update rules for email. Don't check for exists in users or update requests
        $rules['user.email'] = [
            'required',
            'email',
            'max:255',
        ];

        return ValidatorFacade::make($updateRequest->data, $rules);
    }

    /**
     * Apply the update request.
     */
    public function applyUpdateRequest(UpdateRequest $updateRequest): UpdateRequest
    {
        $data = $updateRequest->data;

        /** @var User $user */
        $user = User::create([
            'first_name' => Arr::get($data, 'user.first_name'),
            'last_name' => Arr::get($data, 'user.last_name'),
            'email' => Arr::get($data, 'user.email'),
            'phone' => Arr::get($data, 'user.phone'),
            'password' => Arr::get($data, 'user.password'),
        ]);

        /** @var Organisation $organisation */
        $organisationId = Arr::get($data, 'organisation.id', null);
        $organisation = $organisationId ? Organisation::find($organisationId) : Organisation::create([
            'slug' => Arr::get($data, 'organisation.slug'),
            'name' => Arr::get($data, 'organisation.name'),
            'description' => sanitize_markdown(
                Arr::get($data, 'organisation.description')
            ),
            'url' => Arr::get($data, 'organisation.url'),
            'email' => Arr::get($data, 'organisation.email'),
            'phone' => Arr::get($data, 'organisation.phone'),
        ]);

        if (Arr::get($data, 'service.name', null)) {
            /** @var Service $service */
            $service = Service::create([
                'organisation_id' => $organisation->id,
                'slug' => Arr::get($data, 'service.slug'),
                'name' => Arr::get($data, 'service.name'),
                'type' => Arr::get($data, 'service.type'),
                'status' => Service::STATUS_INACTIVE,
                'intro' => Arr::get($data, 'service.intro'),
                'description' => sanitize_markdown(
                    Arr::get($data, 'service.description')
                ),
                'wait_time' => Arr::get($data, 'service.wait_time'),
                'is_free' => Arr::get($data, 'service.is_free'),
                'fees_text' => Arr::get($data, 'service.fees_text'),
                'fees_url' => Arr::get($data, 'service.fees_url'),
                'testimonial' => Arr::get($data, 'service.testimonial'),
                'video_embed' => Arr::get($data, 'service.video_embed'),
                'url' => Arr::get($data, 'service.url'),
                'contact_name' => Arr::get($data, 'service.contact_name'),
                'contact_phone' => Arr::get($data, 'service.contact_phone'),
                'contact_email' => Arr::get($data, 'service.contact_email'),
                'referral_method' => Service::REFERRAL_METHOD_NONE,
                'referral_button_text' => null,
                'referral_email' => null,
                'referral_url' => null,
                'logo_file_id' => null,
                'last_modified_at' => Date::now(),
            ]);

            // Create the useful info records.
            foreach (Arr::get($data, 'service.useful_infos') as $usefulInfo) {
                $service->usefulInfos()->create([
                    'title' => $usefulInfo['title'],
                    'description' => sanitize_markdown($usefulInfo['description']),
                    'order' => $usefulInfo['order'],
                ]);
            }

            // Create the offering records.
            foreach (Arr::get($data, 'service.offerings') as $offering) {
                $service->offerings()->create([
                    'offering' => $offering['offering'],
                    'order' => $offering['order'],
                ]);
            }

            // Create the social media records.
            foreach (Arr::get($data, 'service.social_medias') as $socialMedia) {
                $service->socialMedias()->create([
                    'type' => $socialMedia['type'],
                    'url' => $socialMedia['url'],
                ]);
            }
        }

        $user->makeOrganisationAdmin($organisation->load('services'));

        return $updateRequest;
    }

    /**
     * Custom logic for returning the data. Useful when wanting to transform
     * or modify the data before returning it, e.g. removing passwords.
     */
    public function getData(array $data): array
    {
        Arr::forget($data, ['user.password']);

        return $data;
    }
}
