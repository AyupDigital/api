<?php

namespace App\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'intro' => $this->meta['intro'],
            'image_file_id' => $this->meta['image_file_id'] ?? null,
            'image' => $this->image ? [
                'id' => $this->image->id,
                'url' => $this->image->url(),
                'mime_type' => $this->image->mime_type,
                'alt_text' => $this->image->meta['alt_text'] ?? null,
            ] : null,
            'parent' => $this->whenLoaded('parent', function () {
                return $this->parent ? [
                    'id' => $this->parent->id,
                    'slug' => $this->parent->slug,
                    'name' => $this->parent->name,
                    'intro' => $this->parent->meta['intro'],
                    'image_file_id' => $this->parent->meta['image_file_id'] ?? null,
                    'image' => $this->parent->image ? [
                        'id' => $this->parent->image->id,
                        'url' => $this->parent->image->url(),
                        'mime_type' => $this->parent->image->mime_type,
                        'alt_text' => $this->parent->image->meta['alt_text'] ?? null,
                    ] : null,
                    'order' => $this->parent->order,
                    'enabled' => $this->parent->enabled,
                    'homepage' => $this->parent->homepage,
                    'sideboxes' => $this->parent->meta['sideboxes'],
                    'category_taxonomies' => TaxonomyResource::collection($this->parent->taxonomies),
                    'created_at' => $this->parent->created_at->format(CarbonImmutable::ISO8601),
                    'updated_at' => $this->parent->updated_at->format(CarbonImmutable::ISO8601),
                ] : null;
            }),
            'children' => $this->whenLoaded('children', function () {
                return $this->children->map(function ($child) {
                    return [
                        'id' => $child->id,
                        'slug' => $child->slug,
                        'name' => $child->name,
                        'intro' => $child->meta['intro'],
                        'image_file_id' => $child->meta['image_file_id'] ?? null,
                        'image' => $child->image ? [
                            'id' => $child->image->id,
                            'url' => $child->image->url(),
                            'mime_type' => $child->image->mime_type,
                            'alt_text' => $child->image->meta['alt_text'] ?? null,
                        ] : null,
                        'order' => $child->order,
                        'enabled' => $child->enabled,
                        'homepage' => $child->homepage,
                        'sideboxes' => $child->meta['sideboxes'],
                        'category_taxonomies' => TaxonomyResource::collection($child->taxonomies),
                        'created_at' => $child->created_at->format(CarbonImmutable::ISO8601),
                        'updated_at' => $child->updated_at->format(CarbonImmutable::ISO8601),
                    ];
                });
            }),
            'order' => $this->order,
            'enabled' => $this->enabled,
            'homepage' => $this->homepage,
            'sideboxes' => $this->meta['sideboxes'],
            'category_taxonomies' => TaxonomyResource::collection($this->taxonomies),
            'created_at' => $this->created_at->format(CarbonImmutable::ISO8601),
            'updated_at' => $this->updated_at->format(CarbonImmutable::ISO8601),
        ];
    }
}
