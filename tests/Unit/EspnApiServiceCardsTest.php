<?php

namespace Tests\Unit;

use App\Services\EspnApiService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class EspnApiServiceCardsTest extends TestCase
{
    private function buildSummary(array $keyEvents, string $homeId = '100', string $awayId = '200'): array
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
            'keyEvents' => $keyEvents,
        ];
    }

    private function makeServiceWithSummary(array $summary): EspnApiService
    {
        return new class($summary) extends EspnApiService
        {
            public function __construct(private readonly array $fakeSummary) {}

            protected function getEventSummary(string $eventId): array
            {
                return $this->fakeSummary;
            }
        };
    }

    private function invokeGetCards(EspnApiService $service, string $home, string $away): array
    {
        $method = new ReflectionMethod(EspnApiService::class, 'getCards');
        $method->setAccessible(true);

        return $method->invoke($service, 'evt1', $home, $away);
    }

    public function test_parses_yellow_card_for_home_team(): void
    {
        $summary = $this->buildSummary([
            [
                'type' => ['text' => 'Yellow Card'],
                'participants' => [['athlete' => ['displayName' => 'João Silva']]],
                'team' => ['id' => '100'],
                'clock' => ['displayValue' => "34'"],
            ],
        ]);

        $cards = $this->invokeGetCards($this->makeServiceWithSummary($summary), 'Brazil', 'Argentina');

        $this->assertCount(1, $cards);
        $this->assertSame('yellow', $cards[0]['card_type']);
        $this->assertSame('home', $cards[0]['team_side']);
        $this->assertSame('João Silva', $cards[0]['player_name']);
        $this->assertSame('34', $cards[0]['minute']);
    }

    public function test_parses_red_card_for_away_team(): void
    {
        $summary = $this->buildSummary([
            [
                'type' => ['text' => 'Red Card'],
                'participants' => [['athlete' => ['displayName' => 'Carlos Ruiz']]],
                'team' => ['id' => '200'],
                'clock' => ['displayValue' => "67'"],
            ],
        ]);

        $cards = $this->invokeGetCards($this->makeServiceWithSummary($summary), 'Brazil', 'Argentina');

        $this->assertCount(1, $cards);
        $this->assertSame('red', $cards[0]['card_type']);
        $this->assertSame('away', $cards[0]['team_side']);
    }

    public function test_parses_yellow_red_card(): void
    {
        $summary = $this->buildSummary([
            [
                'type' => ['text' => 'Yellow-Red Card'],
                'participants' => [['athlete' => ['displayName' => 'Pedro Alves']]],
                'team' => ['id' => '100'],
                'clock' => ['displayValue' => '78'],
            ],
        ]);

        $cards = $this->invokeGetCards($this->makeServiceWithSummary($summary), 'Brazil', 'Argentina');

        $this->assertCount(1, $cards);
        $this->assertSame('yellow_red', $cards[0]['card_type']);
    }

    public function test_parses_second_yellow_as_yellow_red(): void
    {
        $summary = $this->buildSummary([
            [
                'type' => ['text' => 'Second Yellow'],
                'participants' => [['athlete' => ['displayName' => 'Marco Rossi']]],
                'team' => ['id' => '200'],
                'clock' => ['displayValue' => "55'"],
            ],
        ]);

        $cards = $this->invokeGetCards($this->makeServiceWithSummary($summary), 'Brazil', 'Argentina');

        $this->assertCount(1, $cards);
        $this->assertSame('yellow_red', $cards[0]['card_type']);
    }

    public function test_ignores_non_card_events(): void
    {
        $summary = $this->buildSummary([
            [
                'type' => ['text' => 'Goal'],
                'scoringPlay' => true,
                'participants' => [['athlete' => ['displayName' => 'Neymar']]],
                'team' => ['id' => '100'],
                'clock' => ['displayValue' => "10'"],
            ],
            [
                'type' => ['text' => 'Substitution'],
                'participants' => [['athlete' => ['displayName' => 'Someone']]],
                'team' => ['id' => '100'],
                'clock' => ['displayValue' => "60'"],
            ],
        ]);

        $cards = $this->invokeGetCards($this->makeServiceWithSummary($summary), 'Brazil', 'Argentina');

        $this->assertCount(0, $cards);
    }

    public function test_handles_empty_key_events(): void
    {
        $cards = $this->invokeGetCards($this->makeServiceWithSummary($this->buildSummary([])), 'Brazil', 'Argentina');

        $this->assertCount(0, $cards);
    }

    public function test_is_case_insensitive_for_card_type_detection(): void
    {
        $summary = $this->buildSummary([
            [
                'type' => ['text' => 'YELLOW CARD'],
                'participants' => [['athlete' => ['displayName' => 'Test Player']]],
                'team' => ['id' => '100'],
                'clock' => ['displayValue' => "20'"],
            ],
        ]);

        $cards = $this->invokeGetCards($this->makeServiceWithSummary($summary), 'Brazil', 'Argentina');

        $this->assertCount(1, $cards);
        $this->assertSame('yellow', $cards[0]['card_type']);
    }

    public function test_returns_null_minute_when_clock_missing(): void
    {
        $summary = $this->buildSummary([
            [
                'type' => ['text' => 'Yellow Card'],
                'participants' => [['athlete' => ['displayName' => 'Player X']]],
                'team' => ['id' => '100'],
            ],
        ]);

        $cards = $this->invokeGetCards($this->makeServiceWithSummary($summary), 'Brazil', 'Argentina');

        $this->assertCount(1, $cards);
        $this->assertNull($cards[0]['minute']);
    }
}
