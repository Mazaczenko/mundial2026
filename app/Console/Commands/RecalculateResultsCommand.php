<?php

namespace App\Console\Commands;

use App\Models\WorldMatch;
use App\Services\BetService;
use App\Services\EliminationService;
use App\Services\FootballApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RecalculateResultsCommand extends Command
{
    protected $signature = 'mundial:recalculate';

    protected $description = 'Pobierz ponownie wyniki wszystkich zakończonych meczów z API i przelicz typy';

    public function handle(FootballApiService $api, BetService $betService, EliminationService $eliminationService): int
    {
        $this->info('Pobieranie wyników zakończonych meczów fazy pucharowej...');

        $matches = WorldMatch::where('status', 'finished')
            ->where('stage', '!=', 'group')
            ->orderBy('kickoff_at')
            ->get();

        if ($matches->isEmpty()) {
            $this->warn('Brak zakończonych meczów fazy pucharowej.');

            return self::SUCCESS;
        }

        $this->info("Znaleziono {$matches->count()} zakończonych meczów fazy pucharowej.");

        // Group by date to minimise API calls (one call per day)
        $byDate = $matches->groupBy(fn (WorldMatch $m) => $m->kickoff_at->format('Y-m-d'));

        $updated = 0;

        foreach ($byDate as $date => $matchesOnDay) {
            $this->line("  Pobieram fixtures dla {$date}...");

            // Clear cache to always get fresh data
            Cache::forget("footballdata.fixtures.{$date}");

            $fixtures = $api->getFixturesByDate($date);

            $fixturesById = collect($fixtures)->keyBy('id');

            foreach ($matchesOnDay as $match) {
                $fixture = $fixturesById->get($match->api_fixture_id);

                if ($fixture === null) {
                    $this->warn("    Brak danych API dla meczu #{$match->id} ({$match->home_team} vs {$match->away_team})");

                    continue;
                }

                if (($fixture['status'] ?? '') !== 'FINISHED') {
                    $this->line("    Mecz #{$match->id} ({$match->home_team} vs {$match->away_team}): status {$fixture['status']} — pomijam.");

                    continue;
                }

                $match->update([
                    'score_home' => $fixture['score']['fullTime']['home'] ?? null,
                    'score_away' => $fixture['score']['fullTime']['away'] ?? null,
                    'result_type' => match ($fixture['score']['duration'] ?? 'REGULAR') {
                        'EXTRA_TIME' => 'AET',
                        'PENALTY_SHOOTOUT' => 'PEN',
                        default => 'FT',
                    },
                    'score_home_et' => $fixture['score']['extraTime']['home'] ?? null,
                    'score_away_et' => $fixture['score']['extraTime']['away'] ?? null,
                    'score_home_pen' => $fixture['score']['penalties']['home'] ?? null,
                    'score_away_pen' => $fixture['score']['penalties']['away'] ?? null,
                ]);

                $match->refresh()->load('bets');
                $betService->resolveBets($match);

                $correct = $match->bets->where('is_correct', true)->count();
                $total = $match->bets->count();
                $resultType = $match->result_type ?? 'FT';

                $this->info("    ✓ {$match->home_team} {$match->score_home}:{$match->score_away} {$match->away_team} [{$resultType}] — {$correct}/{$total} poprawnych");

                $updated++;
            }
        }

        $eliminationService->checkAll();

        $this->info("Zaktualizowano {$updated} meczów.");

        return self::SUCCESS;
    }
}
