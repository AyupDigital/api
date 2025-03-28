<?php

namespace App\Http\Requests\Service;

use App\Models\Organisation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class DestroyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $service = $this->route('service');
        if ($this->user()->isOrganisationAdmin($service->organisation)) {
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
            //
        ];
    }
}
