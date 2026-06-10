<?php

namespace App\Jobs;

use App\Services\FootballApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SyncStandingsJob implements ShouldQueue
{
    use Queueable;

    public function handle(FootballApiService $footballApi): void
    {
        $standingsData = $footballApi->getStandings();

        if (empty($standingsData)) {
            return;
        }

        $rows = [];
        $now = Carbon::now()->toDateTimeString();

        foreach ($standingsData as $leagueData) {
            $standings = $leagueData['league']['standings'] ?? [];

            foreach ($standings as $groupStandings) {
                foreach ($groupStandings as $entry) {
                    $rows[] = [
                        'group_name' => $entry['group'] ?? 'A',
                        'api_team_id' => $entry['team']['id'],
                        'team_name' => $entry['team']['name'],
                        'team_flag' => $entry['team']['logo'] ?? null,
                        'position' => $entry['rank'],
                        'played' => $entry['all']['played'] ?? 0,
                        'won' => $entry['all']['win'] ?? 0,
                        'drawn' => $entry['all']['draw'] ?? 0,
                        'lost' => $entry['all']['lose'] ?? 0,
                        'goals_for' => $entry['all']['goals']['for'] ?? 0,
                        'goals_against' => $entry['all']['goals']['against'] ?? 0,
                        'points' => $entry['points'] ?? 0,
                        'synced_at' => $now,
                    ];
                }
            }
        }

        DB::table('group_standings')->truncate();

        if (! empty($rows)) {
            DB::table('group_standings')->insert($rows);
        }
    }
}
