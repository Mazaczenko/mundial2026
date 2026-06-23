<?php

namespace App\Services;

use App\Models\Bet;
use App\Models\Participant;
use Illuminate\Support\Collection;

class BadgeService
{
    private ?array $allBetsPerMatchCache = null;

    public function getBadges(Participant $participant, Collection $finishedMatches): array
    {
        $finishedIds = $finishedMatches->pluck('id')->all();
        $bets = $participant->bets->whereIn('match_id', $finishedIds);
        $betsByMatch = $bets->keyBy('match_id');
        $finishedMap = $finishedMatches->keyBy('id');
        $totalFinished = count($finishedIds);
        $betsPlaced = $bets->count();

        return [
            $this->sharpShooter($bets, $finishedMap),
            $this->hatTrick($bets, $betsByMatch, $finishedMatches),
            $this->onFire($betsByMatch, $finishedMatches),
            $this->reliable($betsPlaced, $totalFinished),
            $this->blackHorse($bets, $betsByMatch, $finishedMatches),
            $this->contrarian($bets, $betsByMatch, $finishedMatches),
            $this->groupExpert($bets, $betsByMatch, $finishedMatches),
        ];
    }

    private function sharpShooter(Collection $bets, Collection $finishedMap): array
    {
        $count = $bets->filter(function ($bet) use ($finishedMap) {
            if (! $bet->is_correct || $bet->predicted_home === null || $bet->predicted_away === null) {
                return false;
            }
            $match = $finishedMap->get($bet->match_id);
            if (! $match) {
                return false;
            }

            return (int) $bet->predicted_home === (int) $match->score_home
                && (int) $bet->predicted_away === (int) $match->score_away;
        })->count();

        return [
            'key' => 'sharp_shooter',
            'label' => 'Snajper',
            'description' => '3+ dokładne wyniki',
            'earned' => $count >= 3,
        ];
    }

    private function hatTrick(Collection $bets, Collection $betsByMatch, Collection $finishedMatches): array
    {
        $bestStreak = 0;
        $running = 0;

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

        return [
            'key' => 'hat_trick',
            'label' => 'Hat-trick',
            'description' => 'Trzy trafienia z rzędu',
            'earned' => $bestStreak >= 3,
        ];
    }

    private function onFire(Collection $betsByMatch, Collection $finishedMatches): array
    {
        $currentStreak = 0;
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

        return [
            'key' => 'on_fire',
            'label' => 'W ogniu',
            'description' => 'Aktualnie 5 trafień z rzędu',
            'earned' => $currentStreak >= 5,
        ];
    }

    private function reliable(int $betsPlaced, int $totalFinished): array
    {
        return [
            'key' => 'reliable',
            'label' => 'Sumienność',
            'description' => '0 pominiętych meczów',
            'earned' => $totalFinished > 0 && $betsPlaced === $totalFinished,
        ];
    }

    private function blackHorse(Collection $bets, Collection $betsByMatch, Collection $finishedMatches): array
    {
        $minorityCorrect = 0;

        $allBetsPerMatch = $this->loadAllBetsPerMatch($finishedMatches);

        foreach ($finishedMatches as $match) {
            $myBet = $betsByMatch->get($match->id);
            if (! $myBet || ! $myBet->is_correct) {
                continue;
            }

            $matchBets = $allBetsPerMatch[$match->id] ?? collect();
            $total = $matchBets->count();
            if ($total === 0) {
                continue;
            }

            $samePrediction = $matchBets->where('prediction_1x2', $myBet->prediction_1x2)->count();
            $pct = $samePrediction / $total * 100;

            if ($pct <= 30) {
                $minorityCorrect++;
            }
        }

        return [
            'key' => 'black_horse',
            'label' => 'Czarny koń',
            'description' => 'Przynajmniej 2 razy trafił gdy <30% grupy typowało tak samo',
            'earned' => $minorityCorrect >= 2,
        ];
    }

    private function contrarian(Collection $bets, Collection $betsByMatch, Collection $finishedMatches): array
    {
        $allBetsPerMatch = $this->loadAllBetsPerMatch($finishedMatches);
        $count = 0;

        foreach ($finishedMatches as $match) {
            $myBet = $betsByMatch->get($match->id);
            if (! $myBet || ! $myBet->is_correct || $myBet->prediction_1x2 !== 'X') {
                continue;
            }

            $matchBets = $allBetsPerMatch[$match->id] ?? collect();
            $total = $matchBets->count();
            if ($total === 0) {
                continue;
            }

            $drawBets = $matchBets->where('prediction_1x2', 'X')->count();
            $pct = $drawBets / $total * 100;

            if ($pct < 20) {
                $count++;
            }
        }

        return [
            'key' => 'contrarian',
            'label' => 'Outsider',
            'description' => 'Trafił remis gdy <20% grupy typowało remis',
            'earned' => $count >= 1,
        ];
    }

    private function groupExpert(Collection $bets, Collection $betsByMatch, Collection $finishedMatches): array
    {
        $groupMatches = $finishedMatches->where('stage', 'group');
        $groupTotal = $groupMatches->count();

        if ($groupTotal < 5) {
            return [
                'key' => 'group_expert',
                'label' => 'Ekspert fazy grupowej',
                'description' => '≥80% trafień w meczach grupowych (min. 5 meczów)',
                'earned' => false,
            ];
        }

        $groupCorrect = 0;
        $groupBets = 0;

        foreach ($groupMatches as $match) {
            $bet = $betsByMatch->get($match->id);
            if ($bet) {
                $groupBets++;
                if ($bet->is_correct) {
                    $groupCorrect++;
                }
            }
        }

        $pct = $groupBets > 0 ? $groupCorrect / $groupBets * 100 : 0;

        return [
            'key' => 'group_expert',
            'label' => 'Ekspert fazy grupowej',
            'description' => '≥80% trafień w meczach grupowych (min. 5 meczów)',
            'earned' => $pct >= 80,
        ];
    }

    private function loadAllBetsPerMatch(Collection $finishedMatches): array
    {
        if ($this->allBetsPerMatchCache !== null) {
            return $this->allBetsPerMatchCache;
        }

        $matchIds = $finishedMatches->pluck('id')->all();

        $allBets = Bet::whereIn('match_id', $matchIds)
            ->whereNotNull('prediction_1x2')
            ->get(['match_id', 'prediction_1x2']);

        $grouped = [];
        foreach ($allBets as $bet) {
            $grouped[$bet->match_id][] = $bet;
        }

        return $this->allBetsPerMatchCache = array_map(fn ($items) => collect($items), $grouped);
    }
}
