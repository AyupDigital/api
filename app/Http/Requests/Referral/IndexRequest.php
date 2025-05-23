<?php

namespace App\Http\Requests\Referral;

use App\Http\Requests\QueryBuilderUtilities;
use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{
    use QueryBuilderUtilities;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if ($this->user()->isServiceWorker() && ! ($this->user()->isGlobalAdmin() && ! $this->user()->isSuperAdmin())) {
            return true;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [];
    }
}
