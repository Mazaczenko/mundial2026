<?php

namespace App\Http\Controllers;

use App\Models\MatchGoal;
use App\Models\WorldMatch;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ScorersController extends Controller
{
    public function index(): Response
    {
        $goals = MatchGoal::with('worldMatch')
            ->whereHas('worldMatch', fn ($q) => $q->finished())
            ->get();

        $finishedMatches = WorldMatch::finished()->count();

        return Inertia::render('Scorers/Index', [
            'topScorers' => $this->buildTopScorers($goals),
            'goalsByCountry' => $this->buildGoalsByCountry($goals),
            'goalsByMinute' => $this->buildGoalsByMinute($goals),
            'stats' => $this->buildStats($goals, $finishedMatches),
        ]);
    }

    private function buildTopScorers(Collection $goals): array
    {
        return $goals
            ->groupBy('player_name')
            ->map(function (Collection $playerGoals, string $name) {
                $regular = $playerGoals->where('own_goal', false);
                $ownGoals = $playerGoals->where('own_goal', true);

                if ($regular->isEmpty()) {
                    return null;
                }

                $first = $regular->first();
                $match = $first->worldMatch;

                if ($first->team_side === 'home') {
                    $team = $match?->home_team ?? '';
                    $flag = $match?->home_team_flag;
                } else {
                    $team = $match?->away_team ?? '';
                    $flag = $match?->away_team_flag;
                }

                return [
                    'name' => $name,
                    'team' => $team,
                    'flag' => $flag,
                    'goals' => $regular->count(),
                    'own_goals' => $ownGoals->count(),
                ];
            })
            ->filter()
            ->sortByDesc('goals')
            ->values()
            ->map(function (array $entry, int $index) {
                return ['rank' => $index + 1, ...$entry];
            })
            ->toArray();
    }

    private function buildGoalsByCountry(Collection $goals): array
    {
        $teamGoals = [];

        foreach ($goals as $goal) {
            $match = $goal->worldMatch;
            if (! $match) {
                continue;
            }

            if ($goal->own_goal) {
                // Own goal counts for the opposing team
                if ($goal->team_side === 'home') {
                    $team = $match->away_team;
                    $flag = $match->away_team_flag;
                } else {
                    $team = $match->home_team;
                    $flag = $match->home_team_flag;
                }
            } else {
                if ($goal->team_side === 'home') {
                    $team = $match->home_team;
                    $flag = $match->home_team_flag;
                } else {
                    $team = $match->away_team;
                    $flag = $match->away_team_flag;
                }
            }

            if (! isset($teamGoals[$team])) {
                $teamGoals[$team] = ['team' => $team, 'flag' => $flag, 'goals' => 0];
            }

            $teamGoals[$team]['goals']++;
        }

        return collect($teamGoals)
            ->filter(fn ($row) => $row['goals'] >= 1)
            ->sortByDesc('goals')
            ->values()
            ->toArray();
    }

    private function buildGoalsByMinute(Collection $goals): array
    {
        $withMinute = $goals->filter(fn ($g) => $g->minute !== null && $g->minute !== '');

        $buckets = [
            ['bucket' => '1–15',  'min' => 1,  'max' => 15,  'count' => 0],
            ['bucket' => '16–30', 'min' => 16, 'max' => 30,  'count' => 0],
            ['bucket' => '31–45', 'min' => 31, 'max' => 45,  'count' => 0],
            ['bucket' => '46–60', 'min' => 46, 'max' => 60,  'count' => 0],
            ['bucket' => '61–75', 'min' => 61, 'max' => 75,  'count' => 0],
            ['bucket' => '76–90', 'min' => 76, 'max' => 90,  'count' => 0],
            ['bucket' => '90+',   'min' => 91, 'max' => PHP_INT_MAX, 'count' => 0],
        ];

        foreach ($withMinute as $goal) {
            $minute = $this->resolveMinuteInt((string) $goal->minute);
            if ($minute === null) {
                continue;
            }
            foreach ($buckets as &$bucket) {
                if ($minute >= $bucket['min'] && $minute <= $bucket['max']) {
                    $bucket['count']++;
                    break;
                }
            }
            unset($bucket);
        }

        return array_map(
            fn ($b) => ['bucket' => $b['bucket'], 'count' => $b['count']],
            $buckets,
        );
    }

    /**
     * Converts a minute string (e.g. "45", "45+5", "90+3") to an integer for bucketing.
     * Stoppage-time notation "X+Y" keeps the base minute, except at the 90+ boundary
     * where it returns 91 so the goal lands in the "90+" bucket.
     */
    private function resolveMinuteInt(string $raw): ?int
    {
        // Strip apostrophes defensively (old DB records may have "90'+3" from ESPN)
        $trimmed = str_replace("'", '', trim($raw));

        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^(\d+)\+(\d+)$/', $trimmed, $m)) {
            $base = (int) $m[1];
            // 45+X → 45 (stays in 31-45), 90+X → 91 (hits the "90+" bucket)
            return $base >= 90 ? 91 : $base;
        }

        return is_numeric($trimmed) ? (int) $trimmed : null;
    }

    private function buildStats(Collection $goals, int $finishedMatches): array
    {
        $totalGoals = $goals->count();
        $avgPerMatch = $finishedMatches > 0
            ? round($totalGoals / $finishedMatches, 1)
            : 0.0;

        $hatTricks = $goals
            ->where('own_goal', false)
            ->groupBy(fn ($g) => $g->player_name.'||'.$g->world_match_id)
            ->filter(fn ($group) => $group->count() >= 3)
            ->map(function (Collection $group) {
                $first = $group->first();
                $match = $first->worldMatch;

                return [
                    'name' => $first->player_name,
                    'match' => ($match?->home_team ?? '?').' vs '.($match?->away_team ?? '?'),
                    'goals' => $group->count(),
                ];
            })
            ->values()
            ->toArray();

        $mostGoalsMatch = WorldMatch::finished()
            ->whereNotNull('score_home')
            ->whereNotNull('score_away')
            ->get(['home_team', 'away_team', 'score_home', 'score_away'])
            ->map(fn ($m) => [
                'match' => $m->home_team.' vs '.$m->away_team,
                'score' => $m->score_home.':'.$m->score_away,
                'goals' => (int) $m->score_home + (int) $m->score_away,
            ])
            ->sortByDesc('goals')
            ->first();

        $lateDrama = $goals
            ->filter(fn ($g) => $g->minute !== null && $g->minute !== '')
            ->filter(fn ($g) => ($this->resolveMinuteInt((string) $g->minute) ?? 0) >= 80)
            ->count();

        return [
            'total_goals' => $totalGoals,
            'finished_matches' => $finishedMatches,
            'avg_per_match' => $avgPerMatch,
            'hat_tricks' => $hatTricks,
            'most_goals_match' => $mostGoalsMatch ? [
                'match' => $mostGoalsMatch['match'],
                'score' => $mostGoalsMatch['score'],
                'goals' => $mostGoalsMatch['goals'],
            ] : null,
            'late_drama' => $lateDrama,
        ];
    }
}
