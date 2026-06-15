<?php

namespace App\Http\Controllers;

use App\Models\WorldMatch;
use Inertia\Inertia;
use Inertia\Response;

class KnockoutController extends Controller
{
    private const STAGE_ORDER = ['r32', 'r16', 'qf', 'sf', 'final'];

    public function index(): Response
    {
        $matches = WorldMatch::query()
            ->whereIn('stage', self::STAGE_ORDER)
            ->orderBy('kickoff_at')
            ->get()
            ->map(fn (WorldMatch $match) => [
                'id'             => $match->id,
                'home_team'      => $match->home_team,
                'away_team'      => $match->away_team,
                'home_team_flag' => $match->home_team_flag,
                'away_team_flag' => $match->away_team_flag,
                'kickoff_at'     => $match->kickoff_at,
                'stage'          => $match->stage,
                'status'         => $match->status,
                'score_home'     => $match->score_home,
                'score_away'     => $match->score_away,
            ])
            ->groupBy('stage');

        $matchesByStage = collect(self::STAGE_ORDER)
            ->mapWithKeys(fn ($stage) => [$stage => $matches->get($stage, collect())->values()])
            ->filter(fn ($stageMatches) => $stageMatches->isNotEmpty());

        return Inertia::render('Knockout/Index', [
            'matchesByStage' => $matchesByStage,
        ]);
    }
}
