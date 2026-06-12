<?php

namespace App\Console\Commands;

use App\Jobs\FetchFinishedMatchResultsJob;
use App\Jobs\SyncStandingsJob;
use App\Jobs\SyncTopScorersJob;
use App\Models\GroupStanding;
use App\Models\WorldMatch;
use App\Services\BetService;
use App\Services\EliminationService;
use App\Services\EspnApiService;
use App\Services\FootballApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

class SyncDataCommand extends Command
{
    protected $signature = 'mundial:sync
                            {--standings : Synchronizuj tabele grup}
                            {--scorers : Synchronizuj top strzelców}
                            {--players : Synchronizuj składy drużyn}
                            {--results : Pobierz wyniki wszystkich rozegranych meczów}
                            {--goals : Pobierz strzelców goli dla zakończonych meczów}
                            {--all : Synchronizuj wszystko}';

    protected $description = 'Ręczna synchronizacja danych z football-data.org';

    public function handle(FootballApiService $api, EspnApiService $espnApi, BetService $betService, EliminationService $eliminationService): int
    {
        $all = $this->option('all');

        if ($all || $this->option('standings')) {
            $this->info('Synchronizacja tabel grup...');
            $job = new SyncStandingsJob;
            $job->handle($api);
            $count = GroupStanding::count();
            $this->info("Tabele zsynchronizowane ({$count} wpisów).");
        }

        if ($all || $this->option('scorers')) {
            $this->info('Synchronizacja top strzelców...');
            $job = new SyncTopScorersJob;
            $job->handle($api);
            $this->info('Top strzelcy zsynchronizowani.');
        }

        if ($all || $this->option('players')) {
            $this->info('Synchronizacja składów drużyn...');
            Artisan::call('mundial:sync-players', [], $this->getOutput());
        }

        if ($all || $this->option('results')) {
            $this->syncResults($api, $espnApi, $betService, $eliminationService);
        }

        if ($all || $this->option('goals')) {
            $this->syncGoals($espnApi);
        }

        if (! $all && ! $this->option('standings') && ! $this->option('scorers') && ! $this->option('players') && ! $this->option('results') && ! $this->option('goals')) {
            $this->warn('Podaj opcję: --standings, --scorers, --players, --results, --goals lub --all');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function syncResults(FootballApiService $api, EspnApiService $espnApi, BetService $betService, EliminationService $eliminationService): void
    {
        $this->info('Pobieranie wyników rozegranych meczów...');

        $pastMatches = WorldMatch::where(function ($q) {
                $q->where('status', '!=', 'finished')
                    ->orWhereNull('score_home')
                    ->orWhereNull('score_away');
            })
            ->where('kickoff_at', '<=', Carbon::now()->subMinutes(105))
            ->orderBy('kickoff_at')
            ->get();

        if ($pastMatches->isEmpty()) {
            $this->info('Brak meczów do zaktualizowania.');
            return;
        }

        $this->info("Znaleziono {$pastMatches->count()} meczów bez wyniku.");

        // Group by date to minimise API calls (one call per day)
        $byDate = $pastMatches->groupBy(fn (WorldMatch $m) => $m->kickoff_at->format('Y-m-d'));

        $updated = 0;

        foreach ($byDate as $date => $matchesOnDay) {
            $this->line("  Pobieram fixtures dla {$date}...");

            // Clear cache so we always get fresh data
            \Illuminate\Support\Facades\Cache::forget("footballdata.fixtures.{$date}");

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

                $scoreHome = $fixture['score']['fullTime']['home'] ?? null;
                $scoreAway = $fixture['score']['fullTime']['away'] ?? null;

                $match->update([
                    'status'     => 'finished',
                    'score_home' => $scoreHome,
                    'score_away' => $scoreAway,
                ]);

                $job = new FetchFinishedMatchResultsJob;
                $job->syncGoals($espnApi, $match);

                $match->refresh()->load('bets');
                $betService->resolveBets($match);

                $goalCount = $match->goals()->count();
                $this->info("    ✓ {$match->home_team} {$scoreHome}:{$scoreAway} {$match->away_team} ({$goalCount} goli)");
                $updated++;
            }
        }

        $eliminationService->checkAll();

        $this->info("Zaktualizowano {$updated} meczów.");
    }

    private function syncGoals(EspnApiService $espnApi): void
    {
        $this->info('Pobieranie strzelców goli...');

        $matches = WorldMatch::where('status', 'finished')
            ->whereDoesntHave('goals')
            ->orderBy('kickoff_at')
            ->get();

        if ($matches->isEmpty()) {
            $this->info('Brak meczów bez strzelców.');
            return;
        }

        $this->info("Znaleziono {$matches->count()} meczów bez goli.");
        $job = new FetchFinishedMatchResultsJob;
        $updated = 0;

        foreach ($matches as $match) {
            $job->syncGoals($espnApi, $match);
            $count = $match->goals()->count();
            if ($count > 0) {
                $this->info("  ✓ {$match->home_team} vs {$match->away_team}: {$count} goli");
                $updated++;
            } else {
                $this->line("  – {$match->home_team} vs {$match->away_team}: brak danych ESPN");
            }
        }

        $this->info("Zaktualizowano {$updated} meczów.");
    }
}
