<?php

namespace App\Console\Commands;

use App\Models\WorldMatch;
use App\Services\EspnApiService;
use Illuminate\Console\Command;

class BackfillGoalsCommand extends Command
{
    protected $signature = 'mundial:backfill-goals
                            {--force : Re-sync even matches that already have goals}';

    protected $description = 'Backfill goals from ESPN API for all finished matches that are missing goal data';

    public function handle(EspnApiService $espnApi): int
    {
        $query = WorldMatch::query()
            ->finished()
            ->where(function ($q): void {
                $q->where('score_home', '>', 0)
                    ->orWhere('score_away', '>', 0);
            });

        if (! $this->option('force')) {
            $query->whereDoesntHave('goals');
        }

        $matches = $query->get();

        if ($matches->isEmpty()) {
            $this->info('Brak meczów do przetworzenia.');

            return self::SUCCESS;
        }

        $totalGoals = 0;
        $processedCount = 0;

        foreach ($matches as $match) {
            $label = "[{$match->home_team} vs {$match->away_team}]";

            $saved = $espnApi->syncGoalsForMatch($match, bypassCache: true);

            if ($saved === null) {
                $this->line("{$label} pominięto (brak ESPN event)");
            } else {
                $this->info("{$label} {$saved} goli zapisano");
                $totalGoals += $saved;
            }

            $processedCount++;
        }

        $this->newLine();
        $this->info("Gotowe. Przetworzono {$processedCount} meczów, zapisano {$totalGoals} goli łącznie.");

        return self::SUCCESS;
    }
}
