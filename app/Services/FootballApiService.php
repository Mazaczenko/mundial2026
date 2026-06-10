<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FootballApiService
{
    private const BASE_URL = 'https://v3.football.api-sports.io';

    private const LEAGUE = 1;

    private const SEASON = 2026;

    public function getAllFixtures(): array
    {
        return Cache::remember('apifootball.fixtures.all', now()->addDays(7), function () {
            return $this->request('/fixtures', [
                'league' => self::LEAGUE,
                'season' => self::SEASON,
            ]);
        });
    }

    public function getFixturesByDate(string $date): array
    {
        return Cache::remember("apifootball.fixtures.{$date}", now()->addMinutes(15), function () use ($date) {
            return $this->request('/fixtures', [
                'league' => self::LEAGUE,
                'season' => self::SEASON,
                'date' => $date,
            ]);
        });
    }

    public function getStandings(): array
    {
        return Cache::remember('apifootball.standings', now()->addMinutes(60), function () {
            return $this->request('/standings', [
                'league' => self::LEAGUE,
                'season' => self::SEASON,
            ]);
        });
    }

    public function getTopScorers(): array
    {
        return Cache::remember('apifootball.topscorers', now()->addHours(6), function () {
            return $this->request('/players/topscorers', [
                'league' => self::LEAGUE,
                'season' => self::SEASON,
            ]);
        });
    }

    public function getTeams(): array
    {
        return Cache::remember('apifootball.teams', now()->addDays(7), function () {
            return $this->request('/teams', [
                'league' => self::LEAGUE,
                'season' => self::SEASON,
            ]);
        });
    }

    private function request(string $endpoint, array $params = []): array
    {
        $response = Http::withHeaders([
            'x-apisports-key' => config('services.apifootball.key'),
        ])->get(self::BASE_URL.$endpoint, $params);

        $remaining = (int) ($response->header('x-ratelimit-requests-remaining') ?? 100);

        Log::info("API-Football rate limit remaining: {$remaining}", ['endpoint' => $endpoint]);

        if ($remaining < 10) {
            Log::warning("API-Football rate limit critical: {$remaining} requests remaining", [
                'endpoint' => $endpoint,
            ]);
        }

        return $response->json('response', []);
    }
}
