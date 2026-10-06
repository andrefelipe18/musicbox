<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\UserReleaseStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReplaceUserReleaseRequest extends FormRequest
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
            'status' => ['required', Rule::enum(UserReleaseStatus::class)],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'listened_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.Carbon::now('UTC')->toDateString()],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['status', 'rating', 'listened_at'])) {
                return;
            }

            if ($this->input('status') !== UserReleaseStatus::Listened->value
                && ($this->filled('rating') || $this->filled('listened_at'))) {
                $validator->errors()->add('status', 'Rating and listened date require listened status.');
            }

            if ($this->has('rating') && $this->input('rating') !== null && ! is_int($this->input('rating'))) {
                $validator->errors()->add('rating', 'Rating must be an integer.');
            }
        }];
    }
}
