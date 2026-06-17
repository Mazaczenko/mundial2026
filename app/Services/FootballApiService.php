<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FootballApiService
{
    private const BASE_URL = 'https://api.football-data.org/v4';

    private const COMPETITION = 'WC';

    private const SEASON = 2026;

    public function getAllFixtures(): array
    {
        return Cache::remember('footballdata.fixtures.all', now()->addDays(7), function () {
            return $this->request('/competitions/' . self::COMPETITION . '/matches', [
                'season' => self::SEASON,
            ])['matches'] ?? [];
        });
    }

    public function getFixturesByDate(string $date): array
    {
        return Cache::remember("footballdata.fixtures.{$date}", now()->addMinutes(15), function () use ($date) {
            return $this->request('/competitions/' . self::COMPETITION . '/matches', [
                'season'   => self::SEASON,
                'dateFrom' => $date,
                'dateTo'   => $date,
            ])['matches'] ?? [];
        });
    }

    public function getStandings(): array
    {
        return Cache::remember('footballdata.standings', now()->addMinutes(60), function () {
            return $this->request('/competitions/' . self::COMPETITION . '/standings', [
                'season' => self::SEASON,
            ])['standings'] ?? [];
        });
    }

    public function getTopScorers(): array
    {
        return Cache::remember('footballdata.topscorers', now()->addHours(6), function () {
            return $this->request('/competitions/' . self::COMPETITION . '/scorers', [
                'season' => self::SEASON,
            ])['scorers'] ?? [];
        });
    }

    public function getTeams(): array
    {
        return Cache::remember('footballdata.teams', now()->addDays(7), function () {
            return $this->request('/competitions/' . self::COMPETITION . '/teams', [
                'season' => self::SEASON,
            ])['teams'] ?? [];
        });
    }

    private function request(string $endpoint, array $params = []): array
    {
        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => config('services.footballdata.key'),
            ])->get(self::BASE_URL . $endpoint, $params);
        } catch (ConnectionException $e) {
            Log::error("football-data.org connection error: {$e->getMessage()}", [
                'endpoint' => $endpoint,
                'params'   => $params,
            ]);
            return [];
        }

        if ($response->failed()) {
            $message = $response->json('message') ?? $response->body();
            Log::error("football-data.org error [{$response->status()}]: {$message}", [
                'endpoint' => $endpoint,
                'params'   => $params,
            ]);
            return [];
        }

        return $response->json() ?? [];
    }
}
