<?php

namespace App\Console\Commands;

use App\Models\WorldMatch;
use App\Services\FootballApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ImportFixturesCommand extends Command
{
    protected $signature = 'mundial:import-fixtures';

    protected $description = 'Import Mundial 2026 fixtures from football-data.org';

    private const STAGE_MAP = [
        'GROUP_STAGE'    => 'group',
        'LAST_32'        => 'r32',
        'ROUND_OF_32'    => 'r32',
        'LAST_16'        => 'r16',
        'ROUND_OF_16'    => 'r16',
        'QUARTER_FINALS' => 'qf',
        'SEMI_FINALS'    => 'sf',
        'FINAL'          => 'final',
        'THIRD_PLACE'    => '3rd_place',
    ];

    public function handle(FootballApiService $footballApi): int
    {
        $this->info('Fetching fixtures from football-data.org...');

        Cache::forget('footballdata.fixtures.all');
        $fixtures = $footballApi->getAllFixtures();

        if (empty($fixtures)) {
            $this->error('No fixtures returned. Check FOOTBALLDATA_KEY in .env');
            return self::FAILURE;
        }

        $count = 0;
        $skipped = 0;
        $tbd = 0;

        foreach ($fixtures as $fixture) {
            $stage = self::STAGE_MAP[$fixture['stage'] ?? ''] ?? null;

            if ($stage === null) {
                $this->warn("Unknown stage: " . ($fixture['stage'] ?? 'null'));
                $skipped++;
                continue;
            }

            // Knockout matches without teams yet — save as placeholders, update later
            $homeTeam = $fixture['homeTeam']['name'] ?? null;
            $awayTeam = $fixture['awayTeam']['name'] ?? null;

            if ($homeTeam === null || $awayTeam === null) {
                $tbd++;
                continue; // skip TBD knockout matches, they'll be importable after groups finish
            }

            $groupName = $this->extractGroupName($fixture['group'] ?? null, $stage);
            $isFinished = ($fixture['status'] ?? '') === 'FINISHED';

            $scoreData = [];
            if ($isFinished) {
                $ftHome  = $fixture['score']['fullTime']['home'] ?? null;
                $ftAway  = $fixture['score']['fullTime']['away'] ?? null;
                $rtHome  = $fixture['score']['regularTime']['home'] ?? null;
                $rtAway  = $fixture['score']['regularTime']['away'] ?? null;
                $etHome  = $fixture['score']['extraTime']['home'] ?? 0;
                $etAway  = $fixture['score']['extraTime']['away'] ?? 0;
                $penHome = $fixture['score']['penalties']['home'] ?? 0;
                $penAway = $fixture['score']['penalties']['away'] ?? 0;
                $rt      = match ($fixture['score']['duration'] ?? 'REGULAR') {
                    'EXTRA_TIME'       => 'AET',
                    'PENALTY_SHOOTOUT' => 'PEN',
                    default            => 'FT',
                };

                $scoreData = [
                    'score_home'     => $rtHome ?? ($ftHome !== null ? $ftHome - $etHome - ($rt === 'PEN' ? $penHome : 0) : null),
                    'score_away'     => $rtAway ?? ($ftAway !== null ? $ftAway - $etAway - ($rt === 'PEN' ? $penAway : 0) : null),
                    'result_type'    => $rt,
                    'score_home_et'  => $rt !== 'FT' ? $etHome : null,
                    'score_away_et'  => $rt !== 'FT' ? $etAway : null,
                    'score_home_pen' => $rt === 'PEN' ? $penHome : null,
                    'score_away_pen' => $rt === 'PEN' ? $penAway : null,
                ];
            }

            WorldMatch::updateOrCreate(
                ['api_fixture_id' => $fixture['id']],
                array_merge([
                    'home_team'      => $homeTeam,
                    'away_team'      => $awayTeam,
                    'home_team_flag' => $fixture['homeTeam']['crest'] ?? null,
                    'away_team_flag' => $fixture['awayTeam']['crest'] ?? null,
                    'kickoff_at'     => Carbon::parse($fixture['utcDate']),
                    'stage'          => $stage,
                    'group_name'     => $groupName,
                    'status'         => $isFinished ? 'finished' : 'scheduled',
                ], $scoreData)
            );

            $count++;
        }

        $this->info("Imported/updated: {$count} fixtures.");

        if ($tbd > 0) {
            $this->line("Skipped {$tbd} knockout fixtures (teams TBD — rerun after group stage).");
        }
        if ($skipped > 0) {
            $this->warn("Skipped {$skipped} unknown stages.");
        }

        return self::SUCCESS;
    }

    private function extractGroupName(?string $group, string $stage): ?string
    {
        if ($stage !== 'group' || $group === null) {
            return null;
        }

        // "GROUP_A" → "A"
        if (preg_match('/GROUP_([A-L])/i', $group, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }
}
