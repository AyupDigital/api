<?php

namespace App\Http\Requests\CollectionCategory;

use App\Models\Collection;
use App\Models\File;
use App\Models\Role;
use App\Models\Taxonomy;
use App\Models\UserRole;
use App\Rules\FileIsMimeType;
use App\Rules\FileIsPendingAssignment;
use App\Rules\RootTaxonomyIs;
use App\Rules\UserHasRole;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if ($this->user()->isSuperAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'intro' => ['required', 'string', 'min:1', 'max:300'],
            'order' => ['required', 'integer', 'min:1', 'max:'.Collection::categories()->count()],
            'enabled' => [
                'required',
                'boolean',
                new UserHasRole(
                    $this->user('api'),
                    new UserRole([
                        'user_id' => $this->user('api')->id,
                        'role_id' => Role::superAdmin()->id,
                    ]),
                    $this->collection->enabled
                ),
            ],
            'homepage' => [
                'required',
                'boolean',
                new UserHasRole(
                    $this->user('api'),
                    new UserRole([
                        'user_id' => $this->user('api')->id,
                        'role_id' => Role::globalAdmin()->id,
                    ]),
                    $this->collection->homepage
                ),
            ],
            'sideboxes' => ['present', 'array', 'max:3'],
            'parent_uuid' => ['sometimes', 'nullable', 'exists:collections,id'],
            'sideboxes.*' => ['array'],
            'sideboxes.*.title' => ['required_with:sideboxes.*', 'string'],
            'sideboxes.*.content' => ['required_with:sideboxes.*', 'string'],
            'category_taxonomies' => ['present', 'array'],
            'category_taxonomies.*' => ['string', 'exists:taxonomies,id', new RootTaxonomyIs(Taxonomy::NAME_CATEGORY)],
            'image_file_id' => [
                'required_if:order,'.$this->collection->order,
                'exists:files,id',
                new FileIsMimeType(File::MIME_TYPE_PNG, File::MIME_TYPE_JPG, File::MIME_TYPE_JPEG, File::MIME_TYPE_SVG),
                new FileIsPendingAssignment(function ($file) {
                    return $file->id === ($this->collection->meta['image_file_id'] ?? null);
                }),
            ],
        ];
    }
}
