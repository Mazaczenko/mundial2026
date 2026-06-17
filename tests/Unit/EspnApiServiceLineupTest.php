<?php

namespace Tests\Unit;

use App\Models\WorldMatch;
use App\Services\EspnApiService;
use PHPUnit\Framework\TestCase;

class EspnApiServiceLineupTest extends TestCase
{
    private function buildSummary(array $rosters, string $homeId = '100', string $awayId = '200'): array
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
            'rosters' => $rosters,
        ];
    }

    private function buildRosterEntry(string $teamId, array $players): array
    {
        return [
            'team' => ['id' => $teamId],
            'roster' => $players,
        ];
    }

    private function buildPlayer(string $name, string $jersey, string $position, bool $starter): array
    {
        return [
            'athlete' => [
                'displayName' => $name,
                'jersey' => $jersey,
                'position' => ['abbreviation' => $position],
            ],
            'starter' => $starter,
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
     * @param  array<string, mixed>|null  $captureRef
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

    public function test_saves_home_and_away_lineup(): void
    {
        $summary = $this->buildSummary([
            $this->buildRosterEntry('100', [
                $this->buildPlayer('Lionel Messi', '10', 'FW', true),
                $this->buildPlayer('Cristian Romero', '13', 'CB', true),
                $this->buildPlayer('Lautaro Martinez', '22', 'FW', false),
            ]),
            $this->buildRosterEntry('200', [
                $this->buildPlayer('Kylian Mbappe', '10', 'FW', true),
                $this->buildPlayer('Antoine Griezmann', '7', 'MF', false),
            ]),
        ]);

        $captured = null;
        $match = $this->makeMatch('evt123', $captured);
        $result = $this->makeService($summary)->syncLineupForMatch($match);

        $this->assertTrue($result);
        $this->assertNotNull($captured);

        $lineup = $captured['match_lineup'];
        $this->assertArrayHasKey('home', $lineup);
        $this->assertArrayHasKey('away', $lineup);
        $this->assertCount(3, $lineup['home']);
        $this->assertCount(2, $lineup['away']);
    }

    public function test_starters_appear_before_subs(): void
    {
        $summary = $this->buildSummary([
            $this->buildRosterEntry('100', [
                $this->buildPlayer('Sub Player', '20', 'MF', false),
                $this->buildPlayer('Starter A', '1', 'GK', true),
                $this->buildPlayer('Starter B', '5', 'CB', true),
            ]),
            $this->buildRosterEntry('200', []),
        ]);

        $captured = null;
        $match = $this->makeMatch('evt123', $captured);
        $this->makeService($summary)->syncLineupForMatch($match);

        $home = $captured['match_lineup']['home'];
        $this->assertTrue($home[0]['starter']);
        $this->assertTrue($home[1]['starter']);
        $this->assertFalse($home[2]['starter']);
    }

    public function test_maps_player_fields_correctly(): void
    {
        $summary = $this->buildSummary([
            $this->buildRosterEntry('100', [
                $this->buildPlayer('Lionel Messi', '10', 'FW', true),
            ]),
            $this->buildRosterEntry('200', []),
        ]);

        $captured = null;
        $match = $this->makeMatch('evt123', $captured);
        $this->makeService($summary)->syncLineupForMatch($match);

        $player = $captured['match_lineup']['home'][0];
        $this->assertSame('Lionel Messi', $player['name']);
        $this->assertSame('10', $player['jersey']);
        $this->assertSame('FW', $player['position']);
        $this->assertTrue($player['starter']);
    }

    public function test_handles_null_jersey_and_position(): void
    {
        $summary = $this->buildSummary([
            $this->buildRosterEntry('100', [
                [
                    'athlete' => ['displayName' => 'Unknown Player'],
                    'starter' => true,
                ],
            ]),
            $this->buildRosterEntry('200', []),
        ]);

        $captured = null;
        $match = $this->makeMatch('evt123', $captured);
        $result = $this->makeService($summary)->syncLineupForMatch($match);

        $this->assertTrue($result);
        $player = $captured['match_lineup']['home'][0];
        $this->assertNull($player['jersey']);
        $this->assertNull($player['position']);
    }

    public function test_returns_false_when_rosters_empty(): void
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
            'rosters' => [],
        ];

        $captured = null;
        $match = $this->makeMatch('evt123', $captured);
        $result = $this->makeService($summary)->syncLineupForMatch($match);

        $this->assertFalse($result);
        $this->assertNull($captured);
    }

    public function test_returns_false_when_rosters_key_missing(): void
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
        $result = $this->makeService($summary)->syncLineupForMatch($match);

        $this->assertFalse($result);
        $this->assertNull($captured);
    }

    public function test_returns_false_when_all_players_empty(): void
    {
        $summary = $this->buildSummary([
            $this->buildRosterEntry('100', []),
            $this->buildRosterEntry('200', []),
        ]);

        $captured = null;
        $match = $this->makeMatch('evt123', $captured);
        $result = $this->makeService($summary)->syncLineupForMatch($match);

        $this->assertFalse($result);
        $this->assertNull($captured);
    }

    public function test_ignores_roster_entry_with_unknown_team_id(): void
    {
        $summary = $this->buildSummary([
            $this->buildRosterEntry('100', [
                $this->buildPlayer('Home Player', '9', 'FW', true),
            ]),
            $this->buildRosterEntry('999', [  // unknown team ID
                $this->buildPlayer('Ghost Player', '7', 'MF', true),
            ]),
        ]);

        $captured = null;
        $match = $this->makeMatch('evt123', $captured);
        $result = $this->makeService($summary)->syncLineupForMatch($match);

        // home has players, away has none — should still save
        $this->assertTrue($result);
        $this->assertCount(1, $captured['match_lineup']['home']);
        $this->assertCount(0, $captured['match_lineup']['away']);
    }
}
