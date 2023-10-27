<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        $resource = [
            'id' => $this->id,
            'organisation_id' => $this->organisation_id,
            'has_logo' => $this->hasLogo(),
            'slug' => $this->slug,
            'name' => $this->name,
            'type' => $this->type,
            'status' => $this->status,
            'intro' => $this->intro,
            'description' => $this->description,
            'wait_time' => $this->wait_time,
            'is_free' => $this->is_free,
            'fees_text' => $this->fees_text,
            'fees_url' => $this->fees_url,
            'testimonial' => $this->testimonial,
            'video_embed' => $this->video_embed,
            'url' => $this->url,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'show_referral_disclaimer' => $this->show_referral_disclaimer,
            'referral_method' => $this->referral_method,
            'referral_button_text' => $this->referral_button_text,
            'referral_email' => $this->referral_email,
            'referral_url' => $this->referral_url,
            'useful_infos' => UsefulInfoResource::collection($this->usefulInfos),
            'offerings' => OfferingResource::collection($this->offerings),
            'gallery_items' => ServiceGalleryItemResource::collection($this->serviceGalleryItems),
            'tags' => TagResource::collection($this->tags),
            'category_taxonomies' => TaxonomyResource::collection($this->taxonomies),
            'eligibility_types' => new ServiceEligibilityResource($this->serviceEligibilities),
            'score' => $this->score,
            'ends_at' => $this->ends_at?->format(CarbonImmutable::ISO8601),
            'last_modified_at' => $this->last_modified_at->format(CarbonImmutable::ISO8601),
            'created_at' => $this->created_at->format(CarbonImmutable::ISO8601),
            'updated_at' => $this->updated_at->format(CarbonImmutable::ISO8601),

            // Relationships.
            'service_locations' => ServiceLocationResource::collection($this->whenLoaded('serviceLocations')),
            'organisation' => new OrganisationResource($this->whenLoaded('organisation')),
        ];

        /**
         * Flagged items.
         */
        if (config('flags.cqc_location')) {
            $resource['cqc_location_id'] = $this->cqc_location_id;
        }

        return $resource;
    }
}
