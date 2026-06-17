<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchCard extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'world_match_id',
        'player_name',
        'team_side',
        'minute',
        'card_type',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'minute' => 'string',
    ];

    public function worldMatch(): BelongsTo
    {
        return $this->belongsTo(WorldMatch::class);
    }
}
