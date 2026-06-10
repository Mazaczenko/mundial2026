<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiebreakerPick extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'participant_id',
        'top_scorer_name',
        'submitted_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
