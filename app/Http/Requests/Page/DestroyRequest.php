<?php

namespace App\Http\Requests\Page;

use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class DestroyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
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
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            //
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            if ($this->page->children()->count() !== 0) {
                $validator->errors()->add('id', 'The information page has child pages. Move or delete these prior to deleting this page.');
            }
        });
    }
}
