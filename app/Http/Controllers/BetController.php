<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBetRequest;
use App\Http\Requests\UpdateBetRequest;
use App\Models\Bet;
use App\Models\Participant;
use App\Models\WorldMatch;
use App\Services\BetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BetController extends Controller
{
    public function __construct(
        private readonly BetService $betService,
    ) {}

    public function index(): Response
    {
        /** @var Participant $user */
        $user = Auth::user();

        $paginator = WorldMatch::query()
            ->with([
                'bets' => fn ($q) => $q->with('participant:id,name'),
                'goals' => fn ($q) => $q->with('player')->orderBy('minute'),
            ])
            ->orderBy('kickoff_at')
            ->paginate(15);

        $matchesByDate = $paginator->getCollection()->map(function (WorldMatch $match) use ($user) {
            $myBet = $match->bets->firstWhere('participant_id', $user->id);
            $canBet = $match->canBet();
            $isVisible = ! $canBet; // others' bets visible after betting deadline

            $othersBets = $isVisible
                ? $match->bets
                    ->where('participant_id', '!=', $user->id)
                    ->map(fn ($bet) => [
                        'participant_name' => $bet->participant->name,
                        'prediction_1x2' => $bet->prediction_1x2,
                    ])
                    ->values()
                : collect();

            return [
                'id' => $match->id,
                'home_team' => $match->home_team,
                'away_team' => $match->away_team,
                'home_team_flag' => $match->home_team_flag,
                'away_team_flag' => $match->away_team_flag,
                'kickoff_at' => $match->kickoff_at->toIso8601String(),
                'stage' => $match->stage,
                'group_name' => $match->group_name,
                'status' => $match->status,
                'score_home' => $match->score_home,
                'score_away' => $match->score_away,
                'can_bet' => $canBet,
                'my_bet' => $myBet ? [
                    'id' => $myBet->id,
                    'prediction_1x2' => $myBet->prediction_1x2,
                    'predicted_home' => $myBet->predicted_home,
                    'predicted_away' => $myBet->predicted_away,
                    'is_correct' => $myBet->is_correct,
                ] : null,
                'others_bets' => $othersBets,
                'goals' => $match->goals->map(fn ($g) => [
                    'player_name' => $g->player_name,
                    'team_side' => $g->team_side,
                    'minute' => $g->minute,
                    'own_goal' => $g->own_goal,
                ])->values(),
            ];
        })->groupBy(function ($match) {
            return Carbon::parse($match['kickoff_at'])
                ->setTimezone('Europe/Warsaw')
                ->format('Y-m-d');
        });

        return Inertia::render('Bets/Index', [
            'matchesByDate' => $matchesByDate,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'participant' => $user,
        ]);
    }

    public function store(StoreBetRequest $request): RedirectResponse
    {
        /** @var Participant $user */
        $user = Auth::user();

        $this->betService->placeBet($user, $request->validated());

        return redirect()->back()->with('success', 'Typ zapisany!');
    }

    public function update(UpdateBetRequest $request, Bet $bet): RedirectResponse
    {
        $this->betService->updateBet($bet, $request->validated());

        return redirect()->back()->with('success', 'Typ zaktualizowany!');
    }
}
