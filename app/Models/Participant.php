<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class Participant extends Authenticatable implements FilamentUser
{
    use Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        Notification::sendNow($this, new ResetPassword($token));
    }

    public function getAuthPassword(): string
    {
        $hash = $this->password;

        // Plain-text sentinel — return an unmatchable bcrypt hash instead of
        // crashing Hash::check() in Laravel 12 which rejects non-bcrypt values.
        if (!str_starts_with((string) $hash, '$2')) {
            return '$2y$10$' . str_repeat('x', 53);
        }

        return $hash;
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
        'email_notifications',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_admin' => 'boolean',
        'paid_entry' => 'boolean',
        'eliminated' => 'boolean',
        'email_notifications' => 'boolean',
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
        $base = (int) $this->bets()
            ->where('is_correct', true)
            ->whereHas('match', fn ($q) => $q->finished())
            ->count();

        return $base + $this->exactScoreCount();
    }

    public function missedMatchesCount(): int
    {
        $pastCount = WorldMatch::where('kickoff_at', '<=', \Illuminate\Support\Carbon::now()->subHour())->count();
        $bettedCount = $this->bets()
            ->whereHas('match', fn ($q) => $q->where('kickoff_at', '<=', \Illuminate\Support\Carbon::now()->subHour()))
            ->count();

        return $pastCount - $bettedCount;
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
            ->where(function ($q) {
                $q->where(fn ($i) => $i->where('prediction_1x2', '1')->whereColumn('predicted_home', '>', 'predicted_away'))
                  ->orWhere(fn ($i) => $i->where('prediction_1x2', 'X')->whereColumn('predicted_home', 'predicted_away'))
                  ->orWhere(fn ($i) => $i->where('prediction_1x2', '2')->whereColumn('predicted_home', '<', 'predicted_away'));
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
