<?php

namespace Tests\Feature;

use App\Http\Controllers\KnockoutController;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Tests for KnockoutController::reorderForBracket() and getWinnerTeam().
 *
 * Both methods are private, so we invoke them via reflection.
 */
class KnockoutBracketReorderTest extends TestCase
{
    private KnockoutController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new KnockoutController;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function callReorder(Collection $matchesByStage): Collection
    {
        $method = new ReflectionMethod(KnockoutController::class, 'reorderForBracket');
        $method->setAccessible(true);

        return $method->invoke($this->controller, $matchesByStage);
    }

    private function callGetWinner(array $match): ?string
    {
        $method = new ReflectionMethod(KnockoutController::class, 'getWinnerTeam');
        $method->setAccessible(true);

        return $method->invoke($this->controller, $match);
    }

    private function match(string $home, string $away, array $overrides = []): array
    {
        return array_merge([
            'id' => rand(1, 9999),
            'home_team' => $home,
            'away_team' => $away,
            'status' => 'finished',
            'result_type' => null,
            'score_home' => 1,
            'score_away' => 0,
            'score_home_et' => null,
            'score_away_et' => null,
            'score_home_pen' => null,
            'score_away_pen' => null,
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // getWinnerTeam tests
    // -------------------------------------------------------------------------

    public function test_get_winner_returns_null_for_scheduled_match(): void
    {
        $match = $this->match('Canada', 'Morocco', ['status' => 'scheduled']);
        $this->assertNull($this->callGetWinner($match));
    }

    public function test_get_winner_home_wins_regular_time(): void
    {
        $match = $this->match('Canada', 'Morocco', ['score_home' => 2, 'score_away' => 0]);
        $this->assertSame('Canada', $this->callGetWinner($match));
    }

    public function test_get_winner_away_wins_regular_time(): void
    {
        $match = $this->match('Canada', 'Morocco', ['score_home' => 0, 'score_away' => 1]);
        $this->assertSame('Morocco', $this->callGetWinner($match));
    }

    public function test_get_winner_draw_without_aet_returns_null(): void
    {
        $match = $this->match('Canada', 'Morocco', ['score_home' => 1, 'score_away' => 1]);
        $this->assertNull($this->callGetWinner($match));
    }

    public function test_get_winner_aet_home_wins(): void
    {
        $match = $this->match('Canada', 'Morocco', [
            'result_type' => 'AET',
            'score_home' => 1,
            'score_away' => 1,
            'score_home_et' => 1,
            'score_away_et' => 0,
        ]);
        $this->assertSame('Canada', $this->callGetWinner($match));
    }

    public function test_get_winner_aet_away_wins(): void
    {
        $match = $this->match('Canada', 'Morocco', [
            'result_type' => 'AET',
            'score_home' => 0,
            'score_away' => 0,
            'score_home_et' => 0,
            'score_away_et' => 1,
        ]);
        $this->assertSame('Morocco', $this->callGetWinner($match));
    }

    public function test_get_winner_pen_home_wins(): void
    {
        $match = $this->match('Canada', 'Morocco', [
            'result_type' => 'PEN',
            'score_home' => 1,
            'score_away' => 1,
            'score_home_et' => 0,
            'score_away_et' => 0,
            'score_home_pen' => 5,
            'score_away_pen' => 4,
        ]);
        $this->assertSame('Canada', $this->callGetWinner($match));
    }

    public function test_get_winner_pen_away_wins(): void
    {
        $match = $this->match('Canada', 'Morocco', [
            'result_type' => 'PEN',
            'score_home' => 1,
            'score_away' => 1,
            'score_home_pen' => 3,
            'score_away_pen' => 4,
        ]);
        $this->assertSame('Morocco', $this->callGetWinner($match));
    }

    public function test_get_winner_pen_returns_null_when_pen_scores_missing(): void
    {
        $match = $this->match('Canada', 'Morocco', [
            'result_type' => 'PEN',
            'score_home_pen' => null,
            'score_away_pen' => null,
        ]);
        $this->assertNull($this->callGetWinner($match));
    }

    // -------------------------------------------------------------------------
    // reorderForBracket tests
    // -------------------------------------------------------------------------

    /**
     * Exact production scenario from the bug report.
     * r32 pairs must map to r16 matches by winner, not by chronological order.
     */
    public function test_reorder_maps_r32_pairs_to_r16_by_winner(): void
    {
        // r32 – deliberately in "wrong" order (chronological, not bracket order)
        $r32 = collect([
            $this->match('South Africa', 'Canada', ['score_home' => 0, 'score_away' => 1]), // winner: Canada
            $this->match('Brazil', 'Japan', ['score_home' => 2, 'score_away' => 0]), // winner: Brazil
            $this->match('Netherlands', 'Morocco', ['score_home' => 0, 'score_away' => 1]), // winner: Morocco
            $this->match('Ivory Coast', 'Norway', ['score_home' => 0, 'score_away' => 1]), // winner: Norway
            $this->match('Germany', 'Paraguay', ['score_home' => 0, 'score_away' => 1]), // winner: Paraguay
            $this->match('Mexico', 'Ecuador', ['score_home' => 1, 'score_away' => 0]), // winner: Mexico
            $this->match('France', 'Sweden', ['score_home' => 2, 'score_away' => 0]), // winner: France
            $this->match('England', 'Congo DR', ['score_home' => 1, 'score_away' => 0]), // winner: England
        ]);

        // r16 – authoritative display order
        $r16 = collect([
            $this->match('Canada', 'Morocco'),   // index 0 – fed by South Africa/Canada + Netherlands/Morocco
            $this->match('Paraguay', 'France'),    // index 1 – fed by Germany/Paraguay + France/Sweden
            $this->match('Brazil', 'Norway'),    // index 2 – fed by Brazil/Japan + Ivory Coast/Norway
            $this->match('Mexico', 'England'),   // index 3 – fed by Mexico/Ecuador + England/Congo DR
        ]);

        $input = collect([
            'r32' => $r32,
            'r16' => $r16,
        ]);

        $result = $this->callReorder($input);
        $reorderedR32 = $result->get('r32')->values();

        // Pair [0,1] feeds r16[0] (Canada vs Morocco)
        $this->assertSame('Canada', $reorderedR32[0]['away_team'], 'r32[0] should produce winner Canada (away)');
        $this->assertSame('Morocco', $reorderedR32[1]['away_team'], 'r32[1] should produce winner Morocco (away)');

        // Pair [2,3] feeds r16[1] (Paraguay vs France)
        $this->assertSame('Paraguay', $reorderedR32[2]['away_team'], 'r32[2] should produce winner Paraguay');
        $this->assertSame('France', $reorderedR32[3]['home_team'], 'r32[3] should produce winner France');

        // Pair [4,5] feeds r16[2] (Brazil vs Norway)
        $this->assertSame('Brazil', $reorderedR32[4]['home_team'], 'r32[4] should produce winner Brazil');
        $this->assertSame('Norway', $reorderedR32[5]['away_team'], 'r32[5] should produce winner Norway');

        // Pair [6,7] feeds r16[3] (Mexico vs England)
        $this->assertSame('Mexico', $reorderedR32[6]['home_team'], 'r32[6] should produce winner Mexico');
        $this->assertSame('England', $reorderedR32[7]['home_team'], 'r32[7] should produce winner England');
    }

    /**
     * When earlier-stage matches have no determined winner yet (scheduled / TBD),
     * they should be appended after all matched pairs.
     */
    public function test_reorder_appends_unresolved_matches_at_end(): void
    {
        $r32 = collect([
            $this->match('South Africa', 'Canada', ['score_home' => 0, 'score_away' => 1]),  // winner: Canada
            $this->match('Netherlands', 'Morocco', ['status' => 'scheduled']),               // no winner yet
            $this->match('Netherlands', 'Morocco', ['status' => 'scheduled']),               // no winner yet
        ]);

        $r16 = collect([
            $this->match('Canada', 'Morocco'), // only Canada is resolvable
        ]);

        $input = collect(['r32' => $r32, 'r16' => $r16]);

        $result = $this->callReorder($input);
        $reorderedR32 = $result->get('r32')->values();

        // The resolved Canada feeder comes first
        $this->assertSame('Canada', $reorderedR32[0]['away_team']);
        // The two unresolved matches follow
        $this->assertSame('scheduled', $reorderedR32[1]['status']);
        $this->assertSame('scheduled', $reorderedR32[2]['status']);
    }

    /**
     * If a stage is missing entirely (e.g. final hasn't started yet),
     * reorderForBracket should not throw and should leave other stages intact.
     */
    public function test_reorder_skips_missing_stages_gracefully(): void
    {
        $sf = collect([
            $this->match('Canada', 'Brazil', ['score_home' => 1, 'score_away' => 0]),
            $this->match('France', 'England', ['score_home' => 2, 'score_away' => 1]),
        ]);

        // No 'final' stage present
        $input = collect(['sf' => $sf]);

        $result = $this->callReorder($input);

        // sf should be unchanged
        $this->assertCount(2, $result->get('sf'));
    }

    /**
     * r16 order must not be modified — only the earlier stage (r32) is reordered.
     */
    public function test_reorder_does_not_change_later_stage_order(): void
    {
        $r32 = collect([
            $this->match('A', 'B', ['score_home' => 1, 'score_away' => 0]),
            $this->match('C', 'D', ['score_home' => 1, 'score_away' => 0]),
        ]);

        $r16Original = [
            $this->match('A', 'C'),
        ];
        $r16 = collect($r16Original);

        $input = collect(['r32' => $r32, 'r16' => $r16]);

        $result = $this->callReorder($input);

        $this->assertSame($r16Original[0]['id'], $result->get('r16')[0]['id']);
    }
}
