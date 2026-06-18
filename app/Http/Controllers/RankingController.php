<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Models\RankingSnapshot;
use App\Models\WorldMatch;
use App\Services\BadgeService;
use App\Services\RankingService;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class RankingController extends Controller
{
    public function __construct(
        private readonly RankingService $rankingService,
        private readonly BadgeService $badgeService,
    ) {}

    public function index(): Response
    {
        $ranking = $this->rankingService->getRanking();
        $ranking = $this->enrichWithPositionChange($ranking);
        $ranking = $this->enrichWithBadges($ranking);

        return Inertia::render('Ranking/Index', [
            'ranking' => $ranking,
            'chartData' => $this->buildChartData(),
            'playedMatchesCount' => WorldMatch::finished()->count(),
            'bettingStats' => $this->buildBettingStats(),
        ]);
    }

    private function enrichWithPositionChange(Collection $ranking): Collection
    {
        $lastMatchId = RankingSnapshot::query()
            ->orderByDesc('world_match_id')
            ->value('world_match_id');

        if ($lastMatchId === null) {
            return $ranking->map(fn ($entry) => array_merge($entry, [
                'position_change' => null,
                'previous_position' => null,
            ]));
        }

        $snapshots = RankingSnapshot::where('world_match_id', $lastMatchId)
            ->get(['participant_id', 'position'])
            ->keyBy('participant_id');

        $active = $ranking->where('eliminated', false)->values();

        return $ranking->map(function (array $entry) use ($snapshots, $active) {
            $currentPosition = $entry['eliminated']
                ? 999
                : ($active->search(fn ($e) => $e['id'] === $entry['id']) + 1);

            $snapshot = $snapshots->get($entry['id']);
            $previousPosition = $snapshot?->position;

            $positionChange = $previousPosition !== null
                ? $previousPosition - $currentPosition
                : null;

            return array_merge($entry, [
                'position_change' => $positionChange,
                'previous_position' => $previousPosition,
            ]);
        });
    }

    private function enrichWithBadges(Collection $ranking): Collection
    {
        $finishedMatches = WorldMatch::finished()
            ->orderBy('kickoff_at')
            ->get(['id', 'stage', 'score_home', 'score_away', 'kickoff_at']);

        $participants = Participant::with([
            'bets' => fn ($q) => $q->whereIn('match_id', $finishedMatches->pluck('id')),
        ])->get()->keyBy('id');

        return $ranking->map(function (array $entry) use ($participants, $finishedMatches) {
            $participant = $participants->get($entry['id']);
            if (! $participant) {
                return array_merge($entry, ['badges' => []]);
            }

            $allBadges = $this->badgeService->getBadges($participant, $finishedMatches);
            $earnedBadges = array_values(array_filter($allBadges, fn ($b) => $b['earned']));
            $miniBadges = array_map(fn ($b) => ['key' => $b['key'], 'label' => $b['label']], $earnedBadges);

            return array_merge($entry, ['badges' => $miniBadges]);
        });
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

            $exactScores = $bets->filter(function ($bet) use ($finishedMap) {
                $match = $finishedMap->get($bet->match_id);
                if (! $match || $bet->predicted_home === null || $bet->predicted_away === null) {
                    return false;
                }

                return (int) $bet->predicted_home === (int) $match->score_home
                    && (int) $bet->predicted_away === (int) $match->score_away;
            })->count();

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

            $currentStreak = 0;
            $bestStreak = 0;
            $running = 0;

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
        $matches = WorldMatch::finished()
            ->orderBy('kickoff_at')
            ->get(['id', 'home_team', 'away_team', 'score_home', 'score_away']);

        if ($matches->isEmpty()) {
            return [];
        }

        $matchIds = $matches->pluck('id')->all();

        $matchScores = $matches->keyBy('id');

        $participants = Participant::with([
            'bets' => fn ($q) => $q->whereIn('match_id', $matchIds),
        ])->orderBy('name')->get(['id', 'name', 'eliminated']);

        $labels = $matches->map(fn ($m) => $m->home_team.' – '.$m->away_team)->values();

        $datasets = $participants->map(function (Participant $participant) use ($matchIds, $matchScores) {
            $betsByMatch = $participant->bets->keyBy('match_id');

            $cumulative = 0;
            $data = array_map(function (int $mid) use ($betsByMatch, $matchScores, &$cumulative) {
                $bet = $betsByMatch->get($mid);
                $match = $matchScores->get($mid);

                if ($bet && $bet->is_correct) {
                    $cumulative++;
                    if (
                        $bet->predicted_home !== null &&
                        $bet->predicted_away !== null &&
                        $match &&
                        (int) $bet->predicted_home === (int) $match->score_home &&
                        (int) $bet->predicted_away === (int) $match->score_away
                    ) {
                        $cumulative++;
                    }
                }

                return $cumulative;
            }, $matchIds);

            return [
                'label' => $participant->name,
                'data' => array_values($data),
            ];
        })->values();

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }
}
