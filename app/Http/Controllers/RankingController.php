<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Models\RankingSnapshot;
use App\Models\WorldMatch;
use App\Services\RankingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class RankingController extends Controller
{
    public function __construct(
        private readonly RankingService $rankingService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Ranking/Index', [
            'ranking' => $this->rankingService->getRanking(),
            'chartData' => $this->buildChartData(),
            'playedMatchesCount' => WorldMatch::finished()->count(),
            'bettingStats' => $this->buildBettingStats(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function buildBettingStats(): array
    {
        $finishedMatches = WorldMatch::finished()
            ->orderBy('kickoff_at')
            ->get(['id', 'stage', 'score_home', 'score_away', 'kickoff_at']);

        $finishedIds = $finishedMatches->pluck('id')->all();
        $totalFinished = count($finishedIds);

        /** @var Collection<int, Model> $finishedMap */
        $finishedMap = $finishedMatches->keyBy('id');

        $knockoutStages = ['r32', 'r16', 'qf', 'sf', 'final'];

        $participants = Participant::with([
            'bets' => fn ($q) => $q->whereIn('match_id', $finishedIds)
                ->orderBy('match_id'),
        ])->orderBy('name')->get();

        $stats = [];

        foreach ($participants as $participant) {
            $bets = $participant->bets;
            $betsPlaced = $bets->count();
            $correct1x2 = $bets->where('is_correct', true)->count();

            // Exact scores
            $exactScores = $bets->filter(function ($bet) use ($finishedMap) {
                $match = $finishedMap->get($bet->match_id);
                if (! $match || $bet->predicted_home === null || $bet->predicted_away === null) {
                    return false;
                }

                return (int) $bet->predicted_home === (int) $match->score_home
                    && (int) $bet->predicted_away === (int) $match->score_away;
            })->count();

            // Stage breakdown — index bets by match_id for O(1) lookup
            $betsByMatch = $bets->keyBy('match_id');

            $groupBets = $groupCorrect = $knockoutBets = $knockoutCorrect = 0;
            foreach ($finishedMatches as $match) {
                $bet = $betsByMatch->get($match->id);
                if ($match->stage === 'group') {
                    if ($bet) {
                        $groupBets++;
                        if ($bet->is_correct) {
                            $groupCorrect++;
                        }
                    }
                } elseif (in_array($match->stage, $knockoutStages, true)) {
                    if ($bet) {
                        $knockoutBets++;
                        if ($bet->is_correct) {
                            $knockoutCorrect++;
                        }
                    }
                }
            }

            // Streaks — iterate matches in chronological order
            $currentStreak = 0;
            $bestStreak = 0;
            $running = 0;

            // Walk finished matches newest-first for current streak
            $currentStreakDone = false;
            foreach ($finishedMatches->reverse() as $match) {
                if ($currentStreakDone) {
                    break;
                }
                $bet = $betsByMatch->get($match->id);
                if ($bet && $bet->is_correct) {
                    $currentStreak++;
                } else {
                    $currentStreakDone = true;
                }
            }

            // Walk oldest-first for best streak
            foreach ($finishedMatches as $match) {
                $bet = $betsByMatch->get($match->id);
                if ($bet && $bet->is_correct) {
                    $running++;
                    if ($running > $bestStreak) {
                        $bestStreak = $running;
                    }
                } else {
                    $running = 0;
                }
            }

            // Favourite prediction
            $predCounts = $bets->groupBy('prediction_1x2')
                ->map(fn ($g) => $g->count());
            $favPrediction = $predCounts->isEmpty()
                ? null
                : $predCounts->sortDesc()->keys()->first();

            $accuracyPct = $betsPlaced > 0
                ? round($correct1x2 / $betsPlaced * 100, 1)
                : null;

            $stats[] = [
                'id' => $participant->id,
                'name' => $participant->name,
                'eliminated' => $participant->eliminated,
                'total_finished' => $totalFinished,
                'bets_placed' => $betsPlaced,
                'correct_1x2' => $correct1x2,
                'missed' => $totalFinished - $betsPlaced,
                'accuracy_pct' => $accuracyPct,
                'exact_scores' => $exactScores,
                'current_streak' => $currentStreak,
                'best_streak' => $bestStreak,
                'group_bets' => $groupBets,
                'group_correct' => $groupCorrect,
                'knockout_bets' => $knockoutBets,
                'knockout_correct' => $knockoutCorrect,
                'fav_prediction' => $favPrediction,
            ];
        }

        return $stats;
    }

    private function buildChartData(): array
    {
        $snapshots = RankingSnapshot::with('participant:id,name,eliminated')
            ->orderBy('world_match_id')
            ->get();

        if ($snapshots->isEmpty()) {
            return [];
        }

        // X-axis labels: "Mecz 1", "Mecz 2", ...
        $matchIds = $snapshots->pluck('world_match_id')->unique()->sort()->values();

        $labels = WorldMatch::whereIn('id', $matchIds)
            ->orderBy('kickoff_at')
            ->get(['id', 'home_team', 'away_team'])
            ->mapWithKeys(fn ($m) => [
                $m->id => $m->home_team.' – '.$m->away_team,
            ]);

        $byParticipant = $snapshots->groupBy('participant_id');

        $datasets = $byParticipant->map(function ($rows) use ($matchIds) {
            $participant = $rows->first()->participant;
            $pointsMap = $rows->pluck('points', 'world_match_id');

            $data = $matchIds->map(fn ($mid) => $pointsMap[$mid] ?? null)->values();

            return [
                'label' => $participant->name,
                'data' => $data,
            ];
        })->values();

        return [
            'labels' => $matchIds->map(fn ($mid) => $labels[$mid] ?? "Mecz {$mid}")->values(),
            'datasets' => $datasets,
        ];
    }
}
