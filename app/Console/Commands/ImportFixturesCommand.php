<?php

namespace App\Console\Commands;

use App\Models\WorldMatch;
use App\Services\FootballApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ImportFixturesCommand extends Command
{
    protected $signature = 'mundial:import-fixtures';

    protected $description = 'Import Mundial 2026 fixtures from API-Football';

    private const STAGE_MAP = [
        'Group Stage' => 'group',
        'Round of 32' => 'r32',
        'Round of 16' => 'r16',
        'Quarter-finals' => 'qf',
        'Semi-finals' => 'sf',
        'Final' => 'final',
    ];

    private const FINISHED_STATUSES = ['FT', 'AET', 'PEN'];

    public function handle(FootballApiService $footballApi): int
    {
        $fixtures = $footballApi->getAllFixtures();

        if (empty($fixtures)) {
            $this->error('No fixtures returned from API.');

            return self::FAILURE;
        }

        $count = 0;

        foreach ($fixtures as $fixture) {
            $round = $fixture['league']['round'] ?? '';
            $stage = $this->mapStage($round);

            if ($stage === null) {
                $this->warn("Unknown stage for round: {$round}");

                continue;
            }

            $groupName = $this->extractGroupName($round, $stage);
            $shortStatus = $fixture['fixture']['status']['short'] ?? '';
            $isFinished = in_array($shortStatus, self::FINISHED_STATUSES, strict: true);

            WorldMatch::updateOrCreate(
                ['api_fixture_id' => $fixture['fixture']['id']],
                [
                    'home_team' => $fixture['teams']['home']['name'],
                    'away_team' => $fixture['teams']['away']['name'],
                    'kickoff_at' => Carbon::parse($fixture['fixture']['date']),
                    'stage' => $stage,
                    'group_name' => $groupName,
                    'status' => $isFinished ? 'finished' : 'scheduled',
                    'score_home' => $isFinished ? ($fixture['score']['fulltime']['home'] ?? null) : null,
                    'score_away' => $isFinished ? ($fixture['score']['fulltime']['away'] ?? null) : null,
                ]
            );

            $count++;
        }

        $this->info("Imported/updated {$count} fixtures.");

        return self::SUCCESS;
    }

    private function mapStage(string $round): ?string
    {
        foreach (self::STAGE_MAP as $apiStage => $dbStage) {
            if (str_starts_with($round, $apiStage)) {
                return $dbStage;
            }
        }

        return null;
    }

    private function extractGroupName(string $round, string $stage): ?string
    {
        if ($stage !== 'group') {
            return null;
        }

        if (preg_match('/Group ([A-L])/i', $round, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }
}
