<?php

namespace App\Http\Controllers;

use App\Models\Bet;
use App\Models\Participant;
use App\Models\WorldMatch;
use App\Services\BadgeService;
use App\Services\RankingService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ParticipantController extends Controller
{
    public function __construct(
        private readonly RankingService $rankingService,
        private readonly BadgeService $badgeService,
    ) {}

    public function show(Participant $participant): Response
    {
        $finishedMatches = WorldMatch::finished()
            ->orderBy('kickoff_at')
            ->get(['id', 'stage', 'score_home', 'score_away', 'kickoff_at', 'home_team', 'away_team', 'home_team_flag', 'away_team_flag', 'group_name', 'result_type']);

        $finishedIds = $finishedMatches->pluck('id')->all();
        $totalFinished = count($finishedIds);

        $participant->load([
            'bets' => fn ($q) => $q->whereIn('match_id', $finishedIds)->orderBy('match_id'),
        ]);

        $bets = $participant->bets;
        $betsByMatch = $bets->keyBy('match_id');
        $finishedMap = $finishedMatches->keyBy('id');

        $betsPlaced = $bets->count();
        $correct1x2 = $bets->where('is_correct', true)->count();
        $accuracyPct = $betsPlaced > 0 ? round($correct1x2 / $betsPlaced * 100, 1) : null;

        $exactScores = $bets->filter(function ($bet) use ($finishedMap) {
            $match = $finishedMap->get($bet->match_id);
            if (! $match || $bet->predicted_home === null || $bet->predicted_away === null) {
                return false;
            }

            return (int) $bet->predicted_home === (int) $match->score_home
                && (int) $bet->predicted_away === (int) $match->score_away;
        })->count();

        $currentStreak = 0;
        $bestStreak = 0;
        $running = 0;
        $done = false;

        foreach ($finishedMatches->reverse() as $match) {
            if ($done) {
                break;
            }
            $bet = $betsByMatch->get($match->id);
            if ($bet && $bet->is_correct) {
                $currentStreak++;
            } else {
                $done = true;
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

        $knockoutStages = ['r32', 'r16', 'qf', 'sf', 'final'];
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

        $predCounts = $bets->groupBy('prediction_1x2')->map(fn ($g) => $g->count());
        $favPrediction = $predCounts->isEmpty() ? null : $predCounts->sortDesc()->keys()->first();

        $allBetsPerMatch = $this->loadAllBetsPerMatch($finishedIds);

        $betHistory = $finishedMatches->map(function ($match) use ($betsByMatch, $allBetsPerMatch) {
            $myBet = $betsByMatch->get($match->id);
            $matchBets = $allBetsPerMatch[$match->id] ?? collect();
            $totalBetsOnMatch = $matchBets->count();
            $groupCorrectBets = $matchBets->where('is_correct', true)->count();

            $wasMinority = false;
            if ($myBet && $myBet->is_correct && $totalBetsOnMatch > 0) {
                $samePrediction = $matchBets->where('prediction_1x2', $myBet->prediction_1x2)->count();
                $pct = $samePrediction / $totalBetsOnMatch * 100;
                $wasMinority = $pct <= 50;
            }

            return [
                'id' => $match->id,
                'home_team' => $match->home_team,
                'away_team' => $match->away_team,
                'home_team_flag' => $match->home_team_flag,
                'away_team_flag' => $match->away_team_flag,
                'kickoff_at' => $match->kickoff_at,
                'stage' => $match->stage,
                'group_name' => $match->group_name,
                'score_home' => $match->score_home,
                'score_away' => $match->score_away,
                'result_type' => $match->result_type,
                'my_bet' => $myBet ? [
                    'prediction_1x2' => $myBet->prediction_1x2,
                    'predicted_home' => $myBet->predicted_home,
                    'predicted_away' => $myBet->predicted_away,
                    'is_correct' => $myBet->is_correct,
                ] : null,
                'group_total_bets' => $totalBetsOnMatch,
                'group_correct_bets' => $groupCorrectBets,
                'was_minority' => $wasMinority,
            ];
        })->values();

        $ranking = $this->rankingService->getRanking();
        $active = $ranking->where('eliminated', false)->values();
        $eliminated = $ranking->where('eliminated', true)->values();

        $positionIndex = $active->search(fn ($e) => $e['id'] === $participant->id);
        $participantRanking = null;

        if ($positionIndex !== false) {
            $participantRanking = [
                'position' => $positionIndex + 1,
                'points' => $active[$positionIndex]['points'],
            ];
        } elseif (($elimIndex = $eliminated->search(fn ($e) => $e['id'] === $participant->id)) !== false) {
            $participantRanking = [
                'position' => null,
                'points' => $eliminated[$elimIndex]['points'],
            ];
        }

        $badges = $this->badgeService->getBadges($participant, $finishedMatches);

        $allParticipants = Participant::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])
            ->values();

        return Inertia::render('Participants/Show', [
            'participant' => [
                'id' => $participant->id,
                'name' => $participant->name,
                'eliminated' => $participant->eliminated,
                'paid_entry' => $participant->paid_entry,
            ],
            'isOwn' => Auth::id() === $participant->id,
            'ranking' => $participantRanking,
            'stats' => [
                'bets_placed' => $betsPlaced,
                'correct_1x2' => $correct1x2,
                'accuracy_pct' => $accuracyPct,
                'exact_scores' => $exactScores,
                'missed' => $totalFinished - $betsPlaced,
                'best_streak' => $bestStreak,
                'current_streak' => $currentStreak,
                'group_correct' => $groupCorrect,
                'group_bets' => $groupBets,
                'knockout_correct' => $knockoutCorrect,
                'knockout_bets' => $knockoutBets,
                'fav_prediction' => $favPrediction,
            ],
            'betHistory' => $betHistory,
            'badges' => $badges,
            'allParticipants' => $allParticipants,
        ]);
    }

    private function loadAllBetsPerMatch(array $matchIds): array
    {
        $allBets = Bet::whereIn('match_id', $matchIds)
            ->get(['match_id', 'prediction_1x2', 'is_correct']);

        $grouped = [];
        foreach ($allBets as $bet) {
            $grouped[$bet->match_id][] = $bet;
        }

        return array_map(fn ($items) => collect($items), $grouped);
    }
}
