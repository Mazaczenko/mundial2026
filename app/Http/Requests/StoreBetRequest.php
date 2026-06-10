<?php

namespace App\Http\Requests;

use App\Models\WorldMatch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class StoreBetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'match_id' => ['required', 'exists:world_matches,id'],
            'prediction_1x2' => ['required', 'in:1,X,2'],
            'predicted_home' => ['nullable', 'integer', 'min:0', 'max:20', 'required_with:predicted_away'],
            'predicted_away' => ['nullable', 'integer', 'min:0', 'max:20', 'required_with:predicted_home'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $match = WorldMatch::find($this->match_id);

                if ($match && ! $match->canBet()) {
                    $validator->errors()->add('match_id', 'Czas na obstawienie tego meczu minął.');
                }
            },
        ];
    }
}
