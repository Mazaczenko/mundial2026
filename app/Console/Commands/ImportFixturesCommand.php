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
        'THIRD_PLACE'    => 'final',
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

            WorldMatch::updateOrCreate(
                ['api_fixture_id' => $fixture['id']],
                [
                    'home_team'      => $homeTeam,
                    'away_team'      => $awayTeam,
                    'home_team_flag' => $fixture['homeTeam']['crest'] ?? null,
                    'away_team_flag' => $fixture['awayTeam']['crest'] ?? null,
                    'kickoff_at'     => Carbon::parse($fixture['utcDate']),
                    'stage'          => $stage,
                    'group_name'     => $groupName,
                    'status'         => $isFinished ? 'finished' : 'scheduled',
                    'score_home'     => $isFinished ? ($fixture['score']['fullTime']['home'] ?? null) : null,
                    'score_away'     => $isFinished ? ($fixture['score']['fullTime']['away'] ?? null) : null,
                ]
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
