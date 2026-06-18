<?php

namespace App\Http\Controllers;

use App\Models\Bet;
use App\Models\Participant;
use App\Models\WorldMatch;
use App\Services\RankingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class CompareController extends Controller
{
    public function __construct(
        private readonly RankingService $rankingService,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'a' => ['required', 'integer', 'exists:participants,id'],
            'b' => ['required', 'integer', 'exists:participants,id', 'different:a'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('ranking.index')
                ->with('error', 'Nieprawidłowi uczestnicy do porównania.');
        }

        $validated = $validator->validated();

        $participantA = Participant::findOrFail($validated['a']);
        $participantB = Participant::findOrFail($validated['b']);

        $finishedMatches = WorldMatch::finished()
            ->orderBy('kickoff_at')
            ->get(['id', 'stage', 'score_home', 'score_away', 'kickoff_at', 'home_team', 'away_team', 'home_team_flag', 'away_team_flag', 'group_name', 'result_type']);

        $finishedIds = $finishedMatches->pluck('id')->all();

        $betsA = Bet::where('participant_id', $participantA->id)
            ->whereIn('match_id', $finishedIds)
            ->get()
            ->keyBy('match_id');

        $betsB = Bet::where('participant_id', $participantB->id)
            ->whereIn('match_id', $finishedIds)
            ->get()
            ->keyBy('match_id');

        $aCorrect = $bWins = $aWins = $draws = $aExact = $bExact = 0;
        $bCorrect = 0;

        $finishedMap = $finishedMatches->keyBy('id');

        $matches = $finishedMatches->map(function ($match) use ($betsA, $betsB, &$aCorrect, &$bCorrect, &$aWins, &$bWins, &$draws, &$aExact, &$bExact, $finishedMap) {
            $betA = $betsA->get($match->id);
            $betB = $betsB->get($match->id);

            $aIsCorrect = $betA?->is_correct;
            $bIsCorrect = $betB?->is_correct;

            if ($aIsCorrect === true) {
                $aCorrect++;
            }
            if ($bIsCorrect === true) {
                $bCorrect++;
            }

            if ($aIsCorrect === true && $betA?->predicted_home !== null && $betA?->predicted_away !== null) {
                $m = $finishedMap->get($match->id);
                if ($m && (int) $betA->predicted_home === (int) $m->score_home && (int) $betA->predicted_away === (int) $m->score_away) {
                    $aExact++;
                }
            }

            if ($bIsCorrect === true && $betB?->predicted_home !== null && $betB?->predicted_away !== null) {
                $m = $finishedMap->get($match->id);
                if ($m && (int) $betB->predicted_home === (int) $m->score_home && (int) $betB->predicted_away === (int) $m->score_away) {
                    $bExact++;
                }
            }

            if ($aIsCorrect === true && $bIsCorrect !== true) {
                $aWins++;
            } elseif ($bIsCorrect === true && $aIsCorrect !== true) {
                $bWins++;
            } else {
                $draws++;
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
                'betA' => $betA ? [
                    'prediction_1x2' => $betA->prediction_1x2,
                    'predicted_home' => $betA->predicted_home,
                    'predicted_away' => $betA->predicted_away,
                    'is_correct' => $betA->is_correct,
                ] : null,
                'betB' => $betB ? [
                    'prediction_1x2' => $betB->prediction_1x2,
                    'predicted_home' => $betB->predicted_home,
                    'predicted_away' => $betB->predicted_away,
                    'is_correct' => $betB->is_correct,
                ] : null,
            ];
        })->values();

        $ranking = $this->rankingService->getRanking();
        $active = $ranking->where('eliminated', false)->values();

        $posA = $active->search(fn ($e) => $e['id'] === $participantA->id);
        $posB = $active->search(fn ($e) => $e['id'] === $participantB->id);

        $rankingA = $ranking->firstWhere('id', $participantA->id);
        $rankingB = $ranking->firstWhere('id', $participantB->id);

        $allParticipants = Participant::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])
            ->values();

        return Inertia::render('Compare/Index', [
            'participantA' => [
                'id' => $participantA->id,
                'name' => $participantA->name,
                'eliminated' => $participantA->eliminated,
                'points' => $rankingA['points'] ?? 0,
                'position' => $posA !== false ? $posA + 1 : null,
            ],
            'participantB' => [
                'id' => $participantB->id,
                'name' => $participantB->name,
                'eliminated' => $participantB->eliminated,
                'points' => $rankingB['points'] ?? 0,
                'position' => $posB !== false ? $posB + 1 : null,
            ],
            'allParticipants' => $allParticipants,
            'matches' => $matches,
            'summary' => [
                'a_correct' => $aCorrect,
                'b_correct' => $bCorrect,
                'a_wins' => $aWins,
                'b_wins' => $bWins,
                'draws' => $draws,
                'a_exact' => $aExact,
                'b_exact' => $bExact,
            ],
        ]);
    }
}
