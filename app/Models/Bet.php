<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bet extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'participant_id',
        'match_id',
        'prediction_1x2',
        'predicted_home',
        'predicted_away',
        'is_correct',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(WorldMatch::class, 'match_id');
    }

    public function points(): int
    {
        return (int) ($this->is_correct ?? false);
    }
}
