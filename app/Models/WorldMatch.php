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
        'result_type',
        'score_home_et',
        'score_away_et',
        'score_home_pen',
        'score_away_pen',
        'match_stats',
        'match_lineup',
        'reminder_sent',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kickoff_at' => 'datetime',
        'reminder_sent' => 'boolean',
        'match_stats' => 'array',
        'match_lineup' => 'array',
    ];

    public function bets(): HasMany
    {
        return $this->hasMany(Bet::class, 'match_id');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(MatchGoal::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(MatchCard::class);
    }

    public function result1x2(): ?string
    {
        if ($this->status !== 'finished') {
            return null;
        }

        // For penalty shootout the match winner is determined by the penalty score, not 90-min score
        if ($this->result_type === 'PEN') {
            if ($this->score_home_pen === null || $this->score_away_pen === null) {
                return null;
            }
            return $this->score_home_pen > $this->score_away_pen ? '1' : '2';
        }

        // For extra time, the winner is whoever leads after 120 min
        if ($this->result_type === 'AET') {
            $homeTotal = ($this->score_home ?? 0) + ($this->score_home_et ?? 0);
            $awayTotal = ($this->score_away ?? 0) + ($this->score_away_et ?? 0);
            if ($homeTotal > $awayTotal) return '1';
            if ($awayTotal > $homeTotal) return '2';
            return 'X';
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
        return $query->where(function (Builder $q) {
            // Matches that haven't been picked up yet
            $q->where('status', 'scheduled')
              // Matches the live job finished but football-data.org hasn't filled in result_type yet
              ->orWhere(function (Builder $q2) {
                  $q2->whereIn('status', ['finished', 'in_play'])
                     ->whereNull('result_type');
              });
        })
            ->where('kickoff_at', '<=', Carbon::now()->subMinutes(105))
            ->where('kickoff_at', '>=', Carbon::now()->subHours(5));
    }
}
