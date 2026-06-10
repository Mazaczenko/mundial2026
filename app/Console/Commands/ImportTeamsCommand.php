<?php

namespace App\Console\Commands;

use App\Models\WorldMatch;
use App\Services\FootballApiService;
use Illuminate\Console\Command;

class ImportTeamsCommand extends Command
{
    protected $signature = 'mundial:import-teams';

    protected $description = 'Import team flags from API-Football and update world_matches';

    public function handle(FootballApiService $footballApi): int
    {
        $teams = $footballApi->getTeams();

        if (empty($teams)) {
            $this->error('No teams returned from API.');

            return self::FAILURE;
        }

        $count = 0;

        foreach ($teams as $entry) {
            $teamName = $entry['team']['name'] ?? null;
            $flagUrl = $entry['team']['logo'] ?? null;

            if ($teamName === null) {
                continue;
            }

            // Update home_team_flag where this team plays at home
            $homeUpdated = WorldMatch::query()
                ->where('home_team', $teamName)
                ->update(['home_team_flag' => $flagUrl]);

            // Update away_team_flag where this team plays away
            $awayUpdated = WorldMatch::query()
                ->where('away_team', $teamName)
                ->update(['away_team_flag' => $flagUrl]);

            if ($homeUpdated > 0 || $awayUpdated > 0) {
                $count++;
            }
        }

        $this->info("Updated flags for {$count} teams.");

        return self::SUCCESS;
    }
}
