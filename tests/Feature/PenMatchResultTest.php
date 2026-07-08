<?php

namespace Tests\Feature;

use App\Models\Bet;
use App\Models\Participant;
use App\Models\WorldMatch;
use App\Services\BetService;
use App\Services\EliminationService;
use App\Services\RankingSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PenMatchResultTest extends TestCase
{
    use RefreshDatabase;

    private static int $fixtureId = 9000;

    private function makeMatch(array $attributes = []): WorldMatch
    {
        return WorldMatch::create(array_merge([
            'api_fixture_id' => self::$fixtureId++,
            'home_team' => 'Argentina',
            'away_team' => 'France',
            'home_team_flag' => null,
            'away_team_flag' => null,
            'status' => 'finished',
            'stage' => 'final',
            'kickoff_at' => now()->subHours(3),
        ], $attributes));
    }

    private function makeParticipant(string $name): Participant
    {
        static $i = 0;
        $i++;

        return Participant::create([
            'name' => $name,
            'email' => "pen_test_{$i}@example.com",
            'password' => Hash::make('password'),
        ]);
    }

    // --- result1x2() unit-level tests ---

    public function test_result1x2_returns_null_when_not_finished(): void
    {
        $match = $this->makeMatch([
            'status' => 'scheduled',
            'result_type' => 'PEN',
            'score_home' => null,
            'score_away' => null,
        ]);

        $this->assertNull($match->result1x2());
    }

    public function test_result1x2_returns_null_for_pen_when_90min_score_missing(): void
    {
        $match = $this->makeMatch([
            'result_type' => 'PEN',
            'score_home' => null,
            'score_away' => null,
            'score_home_pen' => 4,
            'score_away_pen' => 3,
        ]);

        $this->assertNull($match->result1x2());
    }

    public function test_result1x2_returns_x_for_pen_when_90min_is_draw(): void
    {
        // Most common PEN scenario: 1:1 after 90 min
        $match = $this->makeMatch([
            'result_type' => 'PEN',
            'score_home' => 1,
            'score_away' => 1,
            'score_home_pen' => 4,
            'score_away_pen' => 3,
        ]);

        $this->assertSame('X', $match->result1x2());
    }

    public function test_result1x2_returns_x_for_pen_when_90min_is_0_0(): void
    {
        $match = $this->makeMatch([
            'result_type' => 'PEN',
            'score_home' => 0,
            'score_away' => 0,
            'score_home_pen' => 5,
            'score_away_pen' => 4,
        ]);

        $this->assertSame('X', $match->result1x2());
    }

    public function test_result1x2_returns_1_for_pen_when_90min_home_leads(): void
    {
        // Edge case: if data were ever stored inconsistently
        $match = $this->makeMatch([
            'result_type' => 'PEN',
            'score_home' => 2,
            'score_away' => 1,
            'score_home_pen' => 3,
            'score_away_pen' => 4,
        ]);

        $this->assertSame('1', $match->result1x2());
    }

    public function test_result1x2_returns_2_for_pen_when_90min_away_leads(): void
    {
        $match = $this->makeMatch([
            'result_type' => 'PEN',
            'score_home' => 0,
            'score_away' => 1,
            'score_home_pen' => 5,
            'score_away_pen' => 3,
        ]);

        $this->assertSame('2', $match->result1x2());
    }

    // --- BetService::resolveBets() integration tests for PEN ---

    public function test_pen_bet_is_correct_when_predicted_x(): void
    {
        $match = $this->makeMatch([
            'result_type' => 'PEN',
            'score_home' => 1,
            'score_away' => 1,
            'score_home_pen' => 4,
            'score_away_pen' => 3,
        ]);
        $participant = $this->makeParticipant('Alice');
        $bet = Bet::create([
            'participant_id' => $participant->id,
            'match_id' => $match->id,
            'prediction_1x2' => 'X',
            'predicted_home' => null,
            'predicted_away' => null,
            'is_correct' => null,
        ]);

        $this->mock(EliminationService::class)->shouldReceive('checkAll')->once();
        $this->mock(RankingSnapshotService::class)->shouldReceive('takeSnapshot')->once();

        $match->load('bets');
        app(BetService::class)->resolveBets($match);

        $this->assertTrue($bet->fresh()->is_correct);
    }

    public function test_pen_bet_is_incorrect_when_predicted_winner_by_penalties(): void
    {
        // User predicted '1' (home wins) — but after 90 min it's a draw, so wrong
        $match = $this->makeMatch([
            'result_type' => 'PEN',
            'score_home' => 1,
            'score_away' => 1,
            'score_home_pen' => 4,
            'score_away_pen' => 3,
        ]);
        $participant = $this->makeParticipant('Bob');
        $bet = Bet::create([
            'participant_id' => $participant->id,
            'match_id' => $match->id,
            'prediction_1x2' => '1',
            'predicted_home' => null,
            'predicted_away' => null,
            'is_correct' => null,
        ]);

        $this->mock(EliminationService::class)->shouldReceive('checkAll')->once();
        $this->mock(RankingSnapshotService::class)->shouldReceive('takeSnapshot')->once();

        $match->load('bets');
        app(BetService::class)->resolveBets($match);

        $this->assertFalse($bet->fresh()->is_correct);
    }

    public function test_pen_knockout_bet_is_correct_via_exact_score_even_with_wrong_1x2(): void
    {
        // User predicted 1:1 score (correct 90-min score) but typed '1' as 1x2 (wrong)
        // correctScore should still make the bet correct
        $match = $this->makeMatch([
            'result_type' => 'PEN',
            'score_home' => 1,
            'score_away' => 1,
            'score_home_pen' => 5,
            'score_away_pen' => 4,
        ]);
        $participant = $this->makeParticipant('Carol');
        $bet = Bet::create([
            'participant_id' => $participant->id,
            'match_id' => $match->id,
            'prediction_1x2' => '1',  // wrong 1x2 (should be X)
            'predicted_home' => 1,
            'predicted_away' => 1,    // correct 90-min score
            'is_correct' => null,
        ]);

        $this->mock(EliminationService::class)->shouldReceive('checkAll')->once();
        $this->mock(RankingSnapshotService::class)->shouldReceive('takeSnapshot')->once();

        $match->load('bets');
        app(BetService::class)->resolveBets($match);

        $this->assertTrue($bet->fresh()->is_correct);
    }
}
