<?php

namespace App\Console\Commands;

use App\Models\WorldMatch;
use App\Services\BetService;
use Illuminate\Console\Command;

class ResolveAllBetsCommand extends Command
{
    protected $signature = 'mundial:resolve-bets';

    protected $description = 'Przelicz is_correct dla wszystkich obstawień zakończonych meczów';

    public function handle(BetService $betService): int
    {
        $matches = WorldMatch::where('status', 'finished')
            ->whereNotNull('score_home')
            ->whereNotNull('score_away')
            ->with('bets')
            ->orderBy('kickoff_at')
            ->get();

        if ($matches->isEmpty()) {
            $this->warn('Brak zakończonych meczów.');
            return self::SUCCESS;
        }

        $this->info("Znaleziono {$matches->count()} zakończonych meczów.");

        foreach ($matches as $match) {
            $betService->resolveBets($match);
            $correct = $match->bets->where('is_correct', true)->count();
            $total   = $match->bets->count();
            $this->line("  ✓ {$match->home_team} {$match->score_home}:{$match->score_away} {$match->away_team} — {$correct}/{$total} poprawnych typów");
        }

        $this->info('Gotowe.');

        return self::SUCCESS;
    }
}
