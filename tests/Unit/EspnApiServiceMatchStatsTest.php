<?php

namespace Tests\Unit;

use App\Models\WorldMatch;
use App\Services\EspnApiService;
use PHPUnit\Framework\TestCase;

class EspnApiServiceMatchStatsTest extends TestCase
{
    private function buildSummary(array $boxscoreTeams, string $homeId = '100', string $awayId = '200'): array
    {
        return [
            'header' => [
                'competitions' => [[
                    'competitors' => [
                        ['homeAway' => 'home', 'id' => $homeId],
                        ['homeAway' => 'away', 'id' => $awayId],
                    ],
                ]],
            ],
            'boxscore' => [
                'teams' => $boxscoreTeams,
            ],
        ];
    }

    private function buildTeamEntry(string $teamId, array $stats): array
    {
        return [
            'team' => ['id' => $teamId],
            'statistics' => $stats,
        ];
    }

    private function makeService(array $summary): EspnApiService
    {
        return new class($summary) extends EspnApiService
        {
            public function __construct(private readonly array $fakeSummary) {}

            protected function getEventSummary(string $eventId): array
            {
                return $this->fakeSummary;
            }

            public function findEventId(string $date, string $homeTeam, string $awayTeam): ?string
            {
                return 'evt123';
            }
        };
    }

    /**
     * Creates a fake WorldMatch stub that tracks update() calls via a captured reference.
     *
     * @param  array<string, mixed>|null  $captureRef  Reference that will receive the array passed to update()
     */
    private function makeMatch(string $espnEventId, ?array &$captureRef = null): WorldMatch
    {
        return new class($espnEventId, $captureRef) extends WorldMatch
        {
            public function __construct(
                public readonly string $espn_event_id,
                private mixed &$captureRef,
            ) {}

            public function update(array $attributes = [], array $options = []): bool
            {
                $this->captureRef = $attributes;

                return true;
            }
        };
    }

    public function test_extracts_home_and_away_stats_correctly(): void
    {
        $summary = $this->buildSummary([
            $this->buildTeamEntry('100', [
                ['name' => 'possessionPct', 'displayValue' => '54.3'],
                ['name' => 'shotsTotalText', 'displayValue' => '12'],
                ['name' => 'shotsOnTarget', 'displayValue' => '5'],
                ['name' => 'cornersTotal', 'displayValue' => '7'],
                ['name' => 'foulsCommitted', 'displayValue' => '14'],
                ['name' => 'offsides', 'displayValue' => '2'],
                ['name' => 'saves', 'displayValue' => '3'],
            ]),
            $this->buildTeamEntry('200', [
                ['name' => 'possessionPct', 'displayValue' => '45.7'],
                ['name' => 'shotsTotalText', 'displayValue' => '8'],
                ['name' => 'shotsOnTarget', 'displayValue' => '3'],
                ['name' => 'cornersTotal', 'displayValue' => '5'],
                ['name' => 'foulsCommitted', 'displayValue' => '11'],
                ['name' => 'offsides', 'displayValue' => '1'],
                ['name' => 'saves', 'displayValue' => '4'],
            ]),
        ]);

        $captured = null;
        $match = $this->makeMatch('evt123', $captured);
        $result = $this->makeService($summary)->syncMatchStatsForMatch($match);

        $this->assertTrue($result);
        $this->assertNotNull($captured);
        $stats = $captured['match_stats'];
        $this->assertSame('54.3', $stats['home']['possessionPct']);
        $this->assertSame('12', $stats['home']['shots']);
        $this->assertSame('5', $stats['home']['shotsOnTarget']);
        $this->assertSame('7', $stats['home']['corners']);
        $this->assertSame('14', $stats['home']['fouls']);
        $this->assertSame('2', $stats['home']['offsides']);
        $this->assertSame('3', $stats['home']['saves']);
        $this->assertSame('45.7', $stats['away']['possessionPct']);
        $this->assertSame('8', $stats['away']['shots']);
    }

    public function test_renames_stat_keys_correctly(): void
    {
        $summary = $this->buildSummary([
            $this->buildTeamEntry('100', [
                ['name' => 'shotsTotalText', 'displayValue' => '10'],
                ['name' => 'cornersTotal', 'displayValue' => '6'],
                ['name' => 'foulsCommitted', 'displayValue' => '12'],
            ]),
            $this->buildTeamEntry('200', []),
        ]);

        $captured = null;
        $match = $this->makeMatch('evt123', $captured);
        $this->makeService($summary)->syncMatchStatsForMatch($match);

        $stats = $captured['match_stats'];
        $this->assertArrayHasKey('shots', $stats['home']);
        $this->assertArrayHasKey('corners', $stats['home']);
        $this->assertArrayHasKey('fouls', $stats['home']);
        $this->assertArrayNotHasKey('shotsTotalText', $stats['home']);
        $this->assertArrayNotHasKey('cornersTotal', $stats['home']);
        $this->assertArrayNotHasKey('foulsCommitted', $stats['home']);
    }

    public function test_returns_false_when_boxscore_teams_empty(): void
    {
        $summary = [
            'header' => [
                'competitions' => [[
                    'competitors' => [
                        ['homeAway' => 'home', 'id' => '100'],
                        ['homeAway' => 'away', 'id' => '200'],
                    ],
                ]],
            ],
            'boxscore' => ['teams' => []],
        ];

        $captured = null;
        $match = $this->makeMatch('evt123', $captured);
        $result = $this->makeService($summary)->syncMatchStatsForMatch($match);

        $this->assertFalse($result);
        $this->assertNull($captured);
    }

    public function test_returns_false_when_no_boxscore_in_summary(): void
    {
        $summary = [
            'header' => [
                'competitions' => [[
                    'competitors' => [
                        ['homeAway' => 'home', 'id' => '100'],
                        ['homeAway' => 'away', 'id' => '200'],
                    ],
                ]],
            ],
        ];

        $captured = null;
        $match = $this->makeMatch('evt123', $captured);
        $result = $this->makeService($summary)->syncMatchStatsForMatch($match);

        $this->assertFalse($result);
        $this->assertNull($captured);
    }

    public function test_ignores_unknown_stat_keys(): void
    {
        $summary = $this->buildSummary([
            $this->buildTeamEntry('100', [
                ['name' => 'shotsTotalText', 'displayValue' => '8'],
                ['name' => 'unknownStat', 'displayValue' => '99'],
                ['name' => 'anotherUnknown', 'displayValue' => '55'],
            ]),
            $this->buildTeamEntry('200', []),
        ]);

        $captured = null;
        $match = $this->makeMatch('evt123', $captured);
        $this->makeService($summary)->syncMatchStatsForMatch($match);

        $stats = $captured['match_stats'];
        $this->assertArrayHasKey('shots', $stats['home']);
        $this->assertArrayNotHasKey('unknownStat', $stats['home']);
        $this->assertArrayNotHasKey('anotherUnknown', $stats['home']);
    }

    public function test_returns_false_when_competitors_missing(): void
    {
        $summary = [
            'header' => ['competitions' => [[]]],
            'boxscore' => [
                'teams' => [
                    $this->buildTeamEntry('100', [
                        ['name' => 'possessionPct', 'displayValue' => '50'],
                    ]),
                ],
            ],
        ];

        $captured = null;
        $match = $this->makeMatch('evt123', $captured);
        $result = $this->makeService($summary)->syncMatchStatsForMatch($match);

        $this->assertFalse($result);
        $this->assertNull($captured);
    }
}
