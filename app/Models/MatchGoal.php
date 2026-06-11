<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchGoal extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'world_match_id',
        'player_id',
        'player_name',
        'team_side',
        'minute',
        'own_goal',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'own_goal' => 'boolean',
        'minute' => 'integer',
    ];

    public function worldMatch(): BelongsTo
    {
        return $this->belongsTo(WorldMatch::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
