<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'api_player_id',
        'name',
        'position',
        'team_name',
        'api_team_id',
    ];

    public function goals(): HasMany
    {
        return $this->hasMany(MatchGoal::class);
    }
}
