<?php

namespace App\Services;

use App\Models\RankingSnapshot;
use App\Models\WorldMatch;

class RankingSnapshotService
{
    public function __construct(
        private readonly RankingService $rankingService,
    ) {}

    public function takeSnapshot(WorldMatch $match): void
    {
        $ranking = $this->rankingService->getRanking();

        $position = 1;
        foreach ($ranking as $entry) {
            RankingSnapshot::updateOrCreate(
                [
                    'world_match_id' => $match->id,
                    'participant_id' => $entry['id'],
                ],
                [
                    'points'   => $entry['points'],
                    'position' => $entry['eliminated'] ? 999 : $position,
                ]
            );

            if (! $entry['eliminated']) {
                $position++;
            }
        }
    }
}
