<?php

namespace App\Http\Controllers;

use App\Models\RankingSnapshot;
use App\Models\WorldMatch;
use App\Services\RankingService;
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
            'ranking'      => $this->rankingService->getRanking(),
            'chartData'    => $this->buildChartData(),
        ]);
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
                $m->id => $m->home_team . ' – ' . $m->away_team,
            ]);

        $byParticipant = $snapshots->groupBy('participant_id');

        $datasets = $byParticipant->map(function ($rows) use ($matchIds, $labels) {
            $participant = $rows->first()->participant;
            $pointsMap   = $rows->pluck('points', 'world_match_id');

            $data = $matchIds->map(fn ($mid) => $pointsMap[$mid] ?? null)->values();

            return [
                'label' => $participant->name,
                'data'  => $data,
            ];
        })->values();

        return [
            'labels'   => $matchIds->map(fn ($mid) => $labels[$mid] ?? "Mecz {$mid}")->values(),
            'datasets' => $datasets,
        ];
    }
}
