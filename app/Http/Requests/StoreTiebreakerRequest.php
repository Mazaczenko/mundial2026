<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreTiebreakerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Carbon::now()->lt(Carbon::parse('2026-06-11 18:00:00', 'UTC'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'top_scorer_name' => ['required', 'string', 'max:100'],
        ];
    }

    public function failedAuthorization(): never
    {
        abort(403, 'Deadline na tiebreaker minął.');
    }
}
