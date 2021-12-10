<?php

namespace App\Http\Requests\InformationPage;

use App\Models\File;
use App\Models\InformationPage;
use App\Models\Role;
use App\Models\UserRole;
use App\Rules\FileIsMimeType;
use App\Rules\FileIsPendingAssignment;
use App\Rules\UserHasRole;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if ($this->user()->isGlobalAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $parent = $this->parent_id ? InformationPage::find($this->parent_id) : $this->information_page->parent;
        return [
            'title' => ['required', 'string', 'min:1', 'max:255'],
            'content' => ['required', 'string', 'min:1', 'max:500'],
            'order' => [
                'required',
                'integer',
                'min:0',
                'max:' . ($parent->children->count()),
            ],
            'enabled' => [
                'required',
                'boolean',
                new UserHasRole(
                    $this->user('api'),
                    new UserRole([
                        'user_id' => $this->user('api')->id,
                        'role_id' => Role::globalAdmin()->id,
                    ]),
                    $this->information_page->enabled
                ),
            ],
            'parent_id' => ['string', 'exists:information_pages,id'],
            'image_file_id' => [
                'exists:files,id',
                new FileIsMimeType(File::MIME_TYPE_PNG, File::MIME_TYPE_JPG),
                new FileIsPendingAssignment(),
            ],
        ];
    }
}