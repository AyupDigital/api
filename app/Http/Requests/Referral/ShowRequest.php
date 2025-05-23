<?php

namespace App\Http\Requests\Referral;

use App\Http\Requests\QueryBuilderUtilities;
use Illuminate\Foundation\Http\FormRequest;

class ShowRequest extends FormRequest
{
    use QueryBuilderUtilities;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (! $this->user()->isOnlyGlobalAdmin() && $this->user()->isServiceWorker($this->referral->service)) {
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
