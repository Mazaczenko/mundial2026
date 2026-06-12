<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RankingSnapshot extends Model
{
    protected $fillable = ['world_match_id', 'participant_id', 'points', 'position'];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
