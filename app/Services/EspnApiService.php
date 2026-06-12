<?php

namespace App\Services;

use App\Models\MatchGoal;
use App\Models\WorldMatch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EspnApiService
{
    private const BASE_URL = 'https://site.api.espn.com/apis/site/v2/sports/soccer/fifa.world';

    /**
     * Returns ESPN event ID for a given date + home team name, or null if not found.
     */
    public function findEventId(string $date, string $homeTeam, string $awayTeam): ?string
    {
        $events = $this->getEventsByDate($date);

        foreach ($events as $event) {
            $competitors = $event['competitions'][0]['competitors'] ?? [];
            $home = collect($competitors)->firstWhere('homeAway', 'home');
            $away = collect($competitors)->firstWhere('homeAway', 'away');

            if ($home === null || $away === null) {
                continue;
            }

            $espnHome = strtolower($home['team']['displayName'] ?? '');
            $espnAway = strtolower($away['team']['displayName'] ?? '');

            if (
                str_contains($espnHome, strtolower($homeTeam)) ||
                str_contains(strtolower($homeTeam), $espnHome) ||
                str_contains($espnAway, strtolower($awayTeam)) ||
                str_contains(strtolower($awayTeam), $espnAway)
            ) {
                return (string) $event['id'];
            }
        }

        return null;
    }

    /**
     * Returns goals for a given ESPN event ID.
     * Each goal: ['player_name', 'team_side' (home|away), 'minute', 'own_goal']
     *
     * @return list<array{player_name: string, team_side: string, minute: int|null, own_goal: bool}>
     */
    public function getGoals(string $eventId, string $homeTeamName, string $awayTeamName): array
    {
        $summary = $this->getEventSummary($eventId);
        $keyEvents = $summary['keyEvents'] ?? [];

        $competitors = $summary['header']['competitions'][0]['competitors'] ?? [];
        $homeEspnId = null;
        $awayEspnId = null;

        foreach ($competitors as $c) {
            if (($c['homeAway'] ?? '') === 'home') {
                $homeEspnId = (string) ($c['id'] ?? '');
            } elseif (($c['homeAway'] ?? '') === 'away') {
                $awayEspnId = (string) ($c['id'] ?? '');
            }
        }

        $goals = [];

        foreach ($keyEvents as $event) {
            if (! ($event['scoringPlay'] ?? false)) {
                continue;
            }

            $scorer = $event['participants'][0]['athlete']['displayName'] ?? 'Unknown';
            $teamId = (string) ($event['team']['id'] ?? '');
            $teamSide = match (true) {
                $teamId === $homeEspnId => 'home',
                $teamId === $awayEspnId => 'away',
                default => $this->guessTeamSide($event['text'] ?? '', $homeTeamName, $awayTeamName),
            };

            $minuteRaw = $event['clock']['displayValue'] ?? null;
            $minute = $minuteRaw ? (int) preg_replace('/[^0-9]/', '', $minuteRaw) : null;

            $isOwnGoal = str_contains(strtolower($event['type']['text'] ?? ''), 'own goal');

            $goals[] = [
                'player_name' => $scorer,
                'team_side' => $teamSide,
                'minute' => $minute,
                'own_goal' => $isOwnGoal,
            ];
        }

        return $goals;
    }

    /**
     * Fetches goals from ESPN for the given match, replaces existing goal records, and returns the count saved.
     * Returns null when no ESPN event can be found for the match.
     */
    public function syncGoalsForMatch(WorldMatch $match, bool $bypassCache = false): ?int
    {
        $date = $match->kickoff_at->format('Y-m-d');
        $eventId = $this->findEventId($date, $match->home_team, $match->away_team);

        if ($eventId === null) {
            return null;
        }

        if ($bypassCache) {
            Cache::forget("espn.summary.{$eventId}");
        }

        $goals = $this->getGoals($eventId, $match->home_team, $match->away_team);

        $match->goals()->delete();

        foreach ($goals as $goal) {
            MatchGoal::create([
                'world_match_id' => $match->id,
                'player_name' => $goal['player_name'],
                'team_side' => $goal['team_side'],
                'minute' => $goal['minute'],
                'own_goal' => $goal['own_goal'],
            ]);
        }

        return count($goals);
    }

    private function guessTeamSide(string $text, string $homeTeam, string $awayTeam): string
    {
        if (str_contains(strtolower($text), strtolower($homeTeam))) {
            return 'home';
        }
        if (str_contains(strtolower($text), strtolower($awayTeam))) {
            return 'away';
        }

        return 'home';
    }

    public function getEventsByDate(string $date): array
    {
        $key = 'espn.scoreboard.'.str_replace('-', '', $date);

        return Cache::remember($key, now()->addMinutes(30), function () use ($date) {
            $espnDate = str_replace('-', '', $date);
            $response = Http::get(self::BASE_URL.'/scoreboard', ['dates' => $espnDate]);

            if ($response->failed()) {
                Log::error('ESPN scoreboard error', ['date' => $date, 'status' => $response->status()]);

                return [];
            }

            return $response->json('events') ?? [];
        });
    }

    private function getEventSummary(string $eventId): array
    {
        return Cache::remember("espn.summary.{$eventId}", now()->addMinutes(30), function () use ($eventId) {
            $response = Http::get(self::BASE_URL.'/summary', ['event' => $eventId]);

            if ($response->failed()) {
                Log::error('ESPN summary error', ['event' => $eventId, 'status' => $response->status()]);

                return [];
            }

            return $response->json() ?? [];
        });
    }
}
