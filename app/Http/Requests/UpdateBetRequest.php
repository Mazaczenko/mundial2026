<?php

namespace App\Http\Requests;

use App\Models\Bet;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class UpdateBetRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Bet $bet */
        $bet = $this->route('bet');

        return $bet->participant_id === Auth::id();
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
                /** @var Bet $bet */
                $bet = $this->route('bet');
                $bet->loadMissing('match');

                if (! $bet->match->canBet()) {
                    $validator->errors()->add('match_id', 'Czas na obstawienie tego meczu minął.');
                }
            },
        ];
    }
}
