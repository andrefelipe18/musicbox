<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ReleaseType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArtistReleasesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'type' => ['sometimes', Rule::enum(ReleaseType::class)],
            'sort' => ['sometimes', 'string', 'in:title,release_year,created_at'],
            'direction' => ['sometimes', 'string', 'in:asc,desc'],
        ];
    }
}
