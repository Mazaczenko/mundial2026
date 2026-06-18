<?php

namespace App\Console\Commands;

use App\Models\WorldMatch;
use App\Services\EspnApiService;
use Illuminate\Console\Command;

class BackfillDetailsCommand extends Command
{
    protected $signature = 'mundial:backfill-details
                            {--force : Re-sync even matches that already have cards/stats/lineup}';

    protected $description = 'Backfill cards, match stats and lineups from ESPN for all finished matches';

    public function handle(EspnApiService $espnApi): int
    {
        $query = WorldMatch::query()->finished();

        $matches = $query->get();

        if ($matches->isEmpty()) {
            $this->info('Brak zakończonych meczów.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');

        $cards = 0;
        $stats = 0;
        $lineups = 0;
        $skipped = 0;

        foreach ($matches as $match) {
            $label = "[{$match->home_team} vs {$match->away_team}]";

            $hasCards   = $match->cards()->exists();
            $hasStats   = $match->match_stats !== null;
            $hasLineup  = $match->match_lineup !== null;

            if (! $force && $hasCards && $hasStats && $hasLineup) {
                $this->line("{$label} pominięto (dane już istnieją)");
                $skipped++;
                continue;
            }

            $this->line("{$label} synchronizuję…");

            if ($force || ! $hasCards) {
                $saved = $espnApi->syncCardsForMatch($match, bypassCache: true);
                if ($saved !== null) {
                    $this->info("  kartki: {$saved}");
                    $cards += $saved;
                }
            }

            if ($force || ! $hasStats) {
                $ok = $espnApi->syncMatchStatsForMatch($match, bypassCache: true);
                if ($ok) {
                    $this->info('  statystyki: OK');
                    $stats++;
                }
            }

            if ($force || ! $hasLineup) {
                $ok = $espnApi->syncLineupForMatch($match, bypassCache: true);
                if ($ok) {
                    $this->info('  skład: OK');
                    $lineups++;
                }
            }
        }

        $this->newLine();
        $this->info("Gotowe. Kartki: {$cards}, statystyki: {$stats}, składy: {$lineups}, pominięto: {$skipped}.");

        return self::SUCCESS;
    }
}
