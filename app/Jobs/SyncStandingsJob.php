<?php

namespace App\Jobs;

use App\Models\WorldMatch;
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

        // Build team → group map from our fixtures (source of truth for group assignments)
        $teamGroup = [];
        WorldMatch::where('stage', 'group')
            ->whereNotNull('group_name')
            ->get(['home_team', 'away_team', 'group_name'])
            ->each(function (WorldMatch $m) use (&$teamGroup) {
                $teamGroup[$m->home_team] = $m->group_name;
                $teamGroup[$m->away_team] = $m->group_name;
            });

        $rows = [];
        $now = Carbon::now()->toDateTimeString();

        foreach ($standingsData as $groupData) {
            if (($groupData['type'] ?? '') !== 'TOTAL') {
                continue;
            }

            $table = $groupData['table'] ?? [];

            // API may return per-group (group: GROUP_A) or flat (group: null)
            $apiGroup = $groupData['group'] ?? null;

            foreach ($table as $entry) {
                $teamName = $entry['team']['name'];

                // Prefer API group, fall back to our fixtures map
                $groupName = null;
                if ($apiGroup && preg_match('/GROUP_([A-L])/i', $apiGroup, $m)) {
                    $groupName = strtoupper($m[1]);
                } else {
                    $groupName = $teamGroup[$teamName] ?? null;
                }

                if ($groupName === null) {
                    continue;
                }

                $rows[] = [
                    'group_name'    => $groupName,
                    'api_team_id'   => $entry['team']['id'],
                    'team_name'     => $teamName,
                    'team_flag'     => $entry['team']['crest'] ?? null,
                    'position'      => $entry['position'],
                    'played'        => $entry['playedGames'] ?? 0,
                    'won'           => $entry['won'] ?? 0,
                    'drawn'         => $entry['draw'] ?? 0,
                    'lost'          => $entry['lost'] ?? 0,
                    'goals_for'     => $entry['goalsFor'] ?? 0,
                    'goals_against' => $entry['goalsAgainst'] ?? 0,
                    'points'        => $entry['points'] ?? 0,
                    'synced_at'     => $now,
                ];
            }
        }

        if (empty($rows)) {
            return;
        }

        // Sort by group and position before insert
        usort($rows, fn ($a, $b) => [$a['group_name'], $a['position']] <=> [$b['group_name'], $b['position']]);

        DB::table('group_standings')->truncate();
        DB::table('group_standings')->insert($rows);
    }
}
