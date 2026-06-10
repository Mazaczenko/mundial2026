<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class Participant extends Authenticatable implements FilamentUser
{
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_admin',
        'paid_entry',
        'eliminated',
        'sms_notifications',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_admin' => 'boolean',
        'paid_entry' => 'boolean',
        'eliminated' => 'boolean',
        'sms_notifications' => 'boolean',
    ];

    protected function password(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Hash::make($value),
        );
    }

    public function bets(): HasMany
    {
        return $this->hasMany(Bet::class);
    }

    public function tiebreakerPick(): HasOne
    {
        return $this->hasOne(TiebreakerPick::class);
    }

    public function pointsTotal(): int
    {
        return (int) $this->bets()
            ->where('is_correct', true)
            ->whereHas('match', fn ($q) => $q->finished())
            ->count();
    }

    public function missedMatchesCount(): int
    {
        $finishedCount = WorldMatch::query()->finished()->count();
        $bettedFinishedCount = $this->bets()
            ->whereHas('match', fn ($q) => $q->finished())
            ->count();

        return $finishedCount - $bettedFinishedCount;
    }

    public function exactScoreCount(): int
    {
        return $this->bets()
            ->where('is_correct', true)
            ->whereNotNull('predicted_home')
            ->whereNotNull('predicted_away')
            ->whereHas('match', function ($q) {
                $q->finished()
                    ->whereColumn('score_home', 'bets.predicted_home')
                    ->whereColumn('score_away', 'bets.predicted_away');
            })
            ->count();
    }

    public function groupCorrectCount(): int
    {
        return $this->bets()
            ->where('is_correct', true)
            ->whereHas('match', fn ($q) => $q->finished()->where('stage', 'group'))
            ->count();
    }

    public function scorerCorrect(): bool
    {
        $pick = $this->tiebreakerPick;

        if ($pick === null) {
            return false;
        }

        $topScorers = Cache::get('mundial.topscorers', []);

        if (empty($topScorers)) {
            return false;
        }

        $topScorerName = $topScorers[0]['player']['name'] ?? null;

        if ($topScorerName === null) {
            return false;
        }

        return mb_strtolower(trim($pick->top_scorer_name)) === mb_strtolower(trim($topScorerName));
    }
}
