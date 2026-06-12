<?php

namespace Tests\Feature;

use App\Models\MatchGoal;
use App\Models\Participant;
use App\Models\WorldMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ScorersControllerTest extends TestCase
{
    use RefreshDatabase;

    private Participant $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = Participant::create([
            'name'     => 'Test User',
            'password' => Hash::make('password'),
            'email'    => 'test@example.com',
        ]);
    }

    private static int $fixtureId = 1000;

    private function makeMatch(array $attributes = []): WorldMatch
    {
        return WorldMatch::create(array_merge([
            'api_fixture_id' => self::$fixtureId++,
            'home_team'      => 'Poland',
            'away_team'      => 'Brazil',
            'home_team_flag' => 'https://example.com/pl.png',
            'away_team_flag' => 'https://example.com/br.png',
            'status'         => 'finished',
            'score_home'     => 1,
            'score_away'     => 1,
            'kickoff_at'     => now()->subHours(3),
            'stage'          => 'group',
        ], $attributes));
    }

    private function makeGoal(WorldMatch $match, array $attributes = []): MatchGoal
    {
        return MatchGoal::create(array_merge([
            'world_match_id' => $match->id,
            'player_name'    => 'Test Player',
            'team_side'      => 'home',
            'minute'         => 30,
            'own_goal'       => false,
        ], $attributes));
    }

    public function test_scorers_page_requires_auth(): void
    {
        $this->get(route('scorers.index'))->assertRedirect(route('login'));
    }

    public function test_scorers_page_renders_with_no_data(): void
    {
        $response = $this->actingAs($this->user)->get(route('scorers.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Scorers/Index')
            ->where('stats.total_goals', 0)
            ->where('stats.finished_matches', 0)
            ->where('topScorers', [])
            ->where('goalsByCountry', [])
        );
    }

    public function test_top_scorers_are_sorted_by_goals_descending(): void
    {
        $match = $this->makeMatch(['score_home' => 3, 'score_away' => 1]);

        $this->makeGoal($match, ['player_name' => 'Messi', 'team_side' => 'away', 'minute' => 10]);
        $this->makeGoal($match, ['player_name' => 'Lewandowski', 'team_side' => 'home', 'minute' => 20]);
        $this->makeGoal($match, ['player_name' => 'Lewandowski', 'team_side' => 'home', 'minute' => 40]);
        $this->makeGoal($match, ['player_name' => 'Lewandowski', 'team_side' => 'home', 'minute' => 60]);

        $response = $this->actingAs($this->user)->get(route('scorers.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('topScorers.0.name', 'Lewandowski')
            ->where('topScorers.0.goals', 3)
            ->where('topScorers.0.rank', 1)
            ->where('topScorers.0.team', 'Poland')
            ->where('topScorers.1.name', 'Messi')
            ->where('topScorers.1.goals', 1)
            ->where('topScorers.1.rank', 2)
            ->where('topScorers.1.team', 'Brazil')
        );
    }

    public function test_own_goals_are_excluded_from_scorer_count_but_counted_for_opposing_team(): void
    {
        $match = $this->makeMatch(['score_home' => 2, 'score_away' => 0]);

        $this->makeGoal($match, ['player_name' => 'Own Goaler', 'team_side' => 'away', 'own_goal' => true, 'minute' => 10]);
        $this->makeGoal($match, ['player_name' => 'Own Goaler', 'team_side' => 'away', 'own_goal' => true, 'minute' => 25]);

        $response = $this->actingAs($this->user)->get(route('scorers.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('topScorers', [])
            ->where('goalsByCountry.0.team', 'Poland')
            ->where('goalsByCountry.0.goals', 2)
        );
    }

    public function test_goals_by_minute_buckets_are_always_7_elements(): void
    {
        $response = $this->actingAs($this->user)->get(route('scorers.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('goalsByMinute', 7)
            ->where('goalsByMinute.0.bucket', '1–15')
            ->where('goalsByMinute.6.bucket', '90+')
        );
    }

    public function test_goals_by_minute_late_drama_counts_80_plus(): void
    {
        $match = $this->makeMatch(['score_home' => 2, 'score_away' => 0]);
        $this->makeGoal($match, ['player_name' => 'Hero', 'team_side' => 'home', 'minute' => 88]);
        $this->makeGoal($match, ['player_name' => 'Hero', 'team_side' => 'home', 'minute' => 95]);
        $this->makeGoal($match, ['player_name' => 'Early', 'team_side' => 'home', 'minute' => 10]);

        $response = $this->actingAs($this->user)->get(route('scorers.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('stats.late_drama', 2)
        );
    }

    public function test_hat_trick_is_detected(): void
    {
        $match = $this->makeMatch(['score_home' => 3, 'score_away' => 0]);
        $this->makeGoal($match, ['player_name' => 'Hattrick Hero', 'team_side' => 'home', 'minute' => 10]);
        $this->makeGoal($match, ['player_name' => 'Hattrick Hero', 'team_side' => 'home', 'minute' => 50]);
        $this->makeGoal($match, ['player_name' => 'Hattrick Hero', 'team_side' => 'home', 'minute' => 80]);

        $response = $this->actingAs($this->user)->get(route('scorers.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('stats.hat_tricks', 1)
            ->where('stats.hat_tricks.0.name', 'Hattrick Hero')
            ->where('stats.hat_tricks.0.goals', 3)
        );
    }

    public function test_goals_from_unfinished_matches_are_excluded(): void
    {
        $scheduled = $this->makeMatch(['status' => 'scheduled', 'score_home' => null, 'score_away' => null]);
        $this->makeGoal($scheduled, ['player_name' => 'Should Not Appear', 'team_side' => 'home']);

        $response = $this->actingAs($this->user)->get(route('scorers.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('topScorers', [])
            ->where('stats.total_goals', 0)
        );
    }

    public function test_avg_per_match_is_calculated_correctly(): void
    {
        $match1 = $this->makeMatch(['score_home' => 2, 'score_away' => 1]);
        $match2 = $this->makeMatch(['score_home' => 1, 'score_away' => 0, 'home_team' => 'Germany', 'away_team' => 'France']);

        $this->makeGoal($match1, ['player_name' => 'A', 'minute' => 10]);
        $this->makeGoal($match1, ['player_name' => 'B', 'team_side' => 'away', 'minute' => 20]);
        $this->makeGoal($match1, ['player_name' => 'A', 'minute' => 70]);
        $this->makeGoal($match2, ['player_name' => 'C', 'minute' => 55]);

        $response = $this->actingAs($this->user)->get(route('scorers.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('stats.total_goals', 4)
            ->where('stats.finished_matches', 2)
            ->where('stats.avg_per_match', 2)
        );
    }
}
