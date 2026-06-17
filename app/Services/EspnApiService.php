<?php

namespace App\Services;

use App\Models\MatchCard;
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
        // ESPN uses US Eastern time — matches at 00:00–05:00 UTC appear under the previous day
        $dates = [$date, date('Y-m-d', strtotime($date.' -1 day'))];
        $events = array_merge($this->getEventsByDate($dates[0]), $this->getEventsByDate($dates[1]));

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
     * @return list<array{player_name: string, team_side: string, minute: string|null, own_goal: bool}>
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
            $minute = $minuteRaw ? $this->parseMinute($minuteRaw) : null;

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

    /**
     * Returns cards for a given ESPN event ID.
     * Each card: ['player_name', 'team_side' (home|away), 'minute', 'card_type' (yellow|red|yellow_red)]
     *
     * @return list<array{player_name: string, team_side: string, minute: string|null, card_type: string}>
     */
    public function getCards(string $eventId, string $homeTeamName, string $awayTeamName): array
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

        $cards = [];

        foreach ($keyEvents as $event) {
            $typeText = strtolower($event['type']['text'] ?? '');

            $cardType = match (true) {
                str_contains($typeText, 'yellow-red') || str_contains($typeText, 'second yellow') => 'yellow_red',
                str_contains($typeText, 'red card') => 'red',
                str_contains($typeText, 'yellow card') => 'yellow',
                default => null,
            };

            if ($cardType === null) {
                continue;
            }

            $playerName = $event['participants'][0]['athlete']['displayName'] ?? 'Unknown';
            $teamId = (string) ($event['team']['id'] ?? '');
            $teamSide = match (true) {
                $teamId === $homeEspnId => 'home',
                $teamId === $awayEspnId => 'away',
                default => $this->guessTeamSide($event['text'] ?? '', $homeTeamName, $awayTeamName),
            };

            $minuteRaw = $event['clock']['displayValue'] ?? null;
            $minute = $minuteRaw ? $this->parseMinute($minuteRaw) : null;

            $cards[] = [
                'player_name' => $playerName,
                'team_side' => $teamSide,
                'minute' => $minute,
                'card_type' => $cardType,
            ];
        }

        return $cards;
    }

    /**
     * Fetches cards from ESPN for the given match, replaces existing card records, and returns the count saved.
     * Returns null when no ESPN event can be found for the match.
     */
    public function syncCardsForMatch(WorldMatch $match, bool $bypassCache = false): ?int
    {
        $date = $match->kickoff_at->format('Y-m-d');
        $eventId = $this->findEventId($date, $match->home_team, $match->away_team);

        if ($eventId === null) {
            return null;
        }

        if ($bypassCache) {
            Cache::forget("espn.summary.{$eventId}");
        }

        $cards = $this->getCards($eventId, $match->home_team, $match->away_team);

        $match->cards()->delete();

        foreach ($cards as $card) {
            MatchCard::create([
                'world_match_id' => $match->id,
                'player_name' => $card['player_name'],
                'team_side' => $card['team_side'],
                'minute' => $card['minute'],
                'card_type' => $card['card_type'],
            ]);
        }

        return count($cards);
    }

    /**
     * Fetches boxscore statistics from ESPN for the given match and saves them on the model.
     * Returns true on success, false if no data could be retrieved.
     */
    public function syncMatchStatsForMatch(WorldMatch $match, bool $bypassCache = false): bool
    {
        $eventId = $match->espn_event_id;

        if ($eventId === null) {
            $date = $match->kickoff_at->format('Y-m-d');
            $eventId = $this->findEventId($date, $match->home_team, $match->away_team);

            if ($eventId === null) {
                return false;
            }

            $match->update(['espn_event_id' => $eventId]);
        }

        if ($bypassCache) {
            Cache::forget("espn.summary.{$eventId}");
        }

        $summary = $this->getEventSummary($eventId);

        $boxscoreTeams = $summary['boxscore']['teams'] ?? [];

        if (empty($boxscoreTeams)) {
            return false;
        }

        // Resolve home/away ESPN IDs from competitors
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

        if ($homeEspnId === null || $awayEspnId === null) {
            return false;
        }

        // Map ESPN stat names to our internal keys (rename where needed)
        $statKeyMap = [
            'possessionPct' => 'possessionPct',
            'shotsTotalText' => 'shots',
            'shotsOnTarget' => 'shotsOnTarget',
            'cornersTotal' => 'corners',
            'foulsCommitted' => 'fouls',
            'offsides' => 'offsides',
            'saves' => 'saves',
            'yellowCards' => 'yellowCards',
            'redCards' => 'redCards',
        ];

        $result = ['home' => [], 'away' => []];

        foreach ($boxscoreTeams as $teamEntry) {
            $teamId = (string) ($teamEntry['team']['id'] ?? '');

            $side = match (true) {
                $teamId === $homeEspnId => 'home',
                $teamId === $awayEspnId => 'away',
                default => null,
            };

            if ($side === null) {
                continue;
            }

            foreach ($teamEntry['statistics'] ?? [] as $stat) {
                $espnName = $stat['name'] ?? '';

                if (! array_key_exists($espnName, $statKeyMap)) {
                    continue;
                }

                $internalKey = $statKeyMap[$espnName];
                $result[$side][$internalKey] = $stat['displayValue'] ?? null;
            }
        }

        // Require at least one side to have data before saving
        if (empty($result['home']) && empty($result['away'])) {
            return false;
        }

        $match->update(['match_stats' => $result]);

        return true;
    }

    /**
     * Fetches roster data from ESPN for the given match and saves the lineup on the model.
     * Returns true on success, false if rosters are empty or event cannot be found.
     */
    public function syncLineupForMatch(WorldMatch $match, bool $bypassCache = false): bool
    {
        $eventId = $match->espn_event_id;

        if ($eventId === null) {
            $date = $match->kickoff_at->format('Y-m-d');
            $eventId = $this->findEventId($date, $match->home_team, $match->away_team);

            if ($eventId === null) {
                return false;
            }

            $match->update(['espn_event_id' => $eventId]);
        }

        if ($bypassCache) {
            Cache::forget("espn.summary.{$eventId}");
        }

        $summary = $this->getEventSummary($eventId);
        $rosters = $summary['rosters'] ?? [];

        if (empty($rosters)) {
            return false;
        }

        // Determine home/away ESPN team IDs from competitors
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

        $result = ['home' => [], 'away' => []];

        foreach ($rosters as $rosterEntry) {
            $teamId = (string) ($rosterEntry['team']['id'] ?? '');

            $side = match (true) {
                $teamId === $homeEspnId => 'home',
                $teamId === $awayEspnId => 'away',
                default => null,
            };

            if ($side === null) {
                continue;
            }

            $players = [];

            foreach ($rosterEntry['roster'] ?? [] as $entry) {
                $players[] = [
                    'name' => $entry['athlete']['displayName'] ?? 'Unknown',
                    'jersey' => $entry['athlete']['jersey'] ?? null,
                    'position' => $entry['athlete']['position']['abbreviation'] ?? null,
                    'starter' => (bool) ($entry['starter'] ?? false),
                ];
            }

            // Starters first, then subs
            usort($players, fn (array $a, array $b) => $b['starter'] <=> $a['starter']);

            $result[$side] = $players;
        }

        if (empty($result['home']) && empty($result['away'])) {
            return false;
        }

        $match->update(['match_lineup' => $result]);

        return true;
    }

    /**
     * Parses ESPN's clock displayValue into a clean minute string.
     *
     * ESPN formats observed:
     *  - "59'"       → "59"
     *  - "45'+4'"    → "45+4"
     *  - "90'+3'"    → "90+3"
     *  - "45:00"     → "45"
     *  - "90+3:00"   → "90+3"
     */
    private function parseMinute(string $raw): string
    {
        // Remove all apostrophes (ESPN uses them as minute markers: "90'+3'")
        $value = str_replace("'", '', trim($raw));

        // If ESPN used MM:SS format (e.g. "45:00", "90+3:00"), drop the seconds part
        if (preg_match('/^(\d+(?:\+\d+)?):/', $value, $m)) {
            return $m[1];
        }

        return $value;
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

    protected function getEventSummary(string $eventId): array
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
