<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ReleaseType;
use App\Enums\UserReleaseStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListReleasesRequest extends FormRequest
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
            'artist_id' => ['sometimes', 'ulid', 'exists:artists,id'],
            'type' => ['sometimes', Rule::enum(ReleaseType::class)],
            'status' => ['sometimes', Rule::enum(UserReleaseStatus::class)],
            'rating' => ['sometimes', 'integer', 'between:1,5'],
            'sort' => ['sometimes', 'string', 'in:title,release_year,created_at,rating,listened_at'],
            'direction' => ['sometimes', 'string', 'in:asc,desc'],
        ];
    }
}
