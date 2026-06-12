<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class WorldMatch extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'api_fixture_id',
        'espn_event_id',
        'home_team',
        'away_team',
        'home_team_flag',
        'away_team_flag',
        'kickoff_at',
        'stage',
        'group_name',
        'status',
        'score_home',
        'score_away',
        'reminder_sent',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kickoff_at' => 'datetime',
        'reminder_sent' => 'boolean',
    ];

    public function bets(): HasMany
    {
        return $this->hasMany(Bet::class, 'match_id');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(MatchGoal::class);
    }

    public function result1x2(): ?string
    {
        if ($this->status !== 'finished') {
            return null;
        }

        if ($this->score_home > $this->score_away) {
            return '1';
        }

        if ($this->score_home === $this->score_away) {
            return 'X';
        }

        return '2';
    }

    public function canBet(): bool
    {
        return $this->status === 'scheduled'
            && Carbon::now()->lt($this->kickoff_at->copy()->subHour());
    }

    public function isKnockout(): bool
    {
        return $this->stage !== 'group';
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', 'scheduled')
            ->where('kickoff_at', '>', Carbon::now());
    }

    public function scopeFinished(Builder $query): Builder
    {
        return $query->where('status', 'finished');
    }

    public function scopePendingResults(Builder $query): Builder
    {
        return $query->where('status', 'scheduled')
            ->where('kickoff_at', '<=', Carbon::now()->subMinutes(105))
            ->where('kickoff_at', '>=', Carbon::now()->subHours(5));
    }
}
