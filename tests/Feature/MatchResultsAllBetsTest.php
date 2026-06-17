<?php

namespace Tests\Feature;

use App\Models\Bet;
use App\Models\Participant;
use App\Models\WorldMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MatchResultsAllBetsTest extends TestCase
{
    use RefreshDatabase;

    private Participant $user;

    private static int $fixtureId = 2000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = Participant::create([
            'name'     => 'Test User',
            'email'    => 'test@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    private function makeMatch(array $attributes = []): WorldMatch
    {
        return WorldMatch::create(array_merge([
            'api_fixture_id' => self::$fixtureId++,
            'home_team'      => 'Poland',
            'away_team'      => 'Brazil',
            'home_team_flag' => null,
            'away_team_flag' => null,
            'status'         => 'finished',
            'score_home'     => 1,
            'score_away'     => 0,
            'kickoff_at'     => now()->subHours(2),
            'stage'          => 'group',
        ], $attributes));
    }

    private function makeParticipant(string $name, bool $eliminated = false): Participant
    {
        static $i = 0;
        $i++;

        return Participant::create([
            'name'       => $name,
            'email'      => "participant{$i}@example.com",
            'password'   => Hash::make('password'),
            'eliminated' => $eliminated,
        ]);
    }

    private function makeBet(Participant $participant, WorldMatch $match, array $attributes = []): Bet
    {
        return Bet::create(array_merge([
            'participant_id' => $participant->id,
            'match_id'       => $match->id,
            'prediction_1x2' => '1',
            'predicted_home' => null,
            'predicted_away' => null,
            'is_correct'     => true,
        ], $attributes));
    }

    public function test_all_bets_key_is_present_in_match_results_response(): void
    {
        $this->makeMatch();

        $response = $this->actingAs($this->user)->get(route('results.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Results/Index')
            ->has('matches.0.all_bets')
        );
    }

    public function test_all_bets_is_empty_array_when_no_bets_placed(): void
    {
        $this->makeMatch();

        $response = $this->actingAs($this->user)->get(route('results.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('matches.0.all_bets', [])
        );
    }

    public function test_all_bets_contains_correct_participant_data(): void
    {
        $match = $this->makeMatch();
        $alice = $this->makeParticipant('Alice');
        $this->makeBet($alice, $match, [
            'prediction_1x2' => '1',
            'predicted_home' => 2,
            'predicted_away' => 0,
            'is_correct'     => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('results.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('matches.0.all_bets.0.participant_name', 'Alice')
            ->where('matches.0.all_bets.0.prediction_1x2', '1')
            ->where('matches.0.all_bets.0.predicted_home', 2)
            ->where('matches.0.all_bets.0.predicted_away', 0)
            ->where('matches.0.all_bets.0.is_correct', true)
            ->where('matches.0.all_bets.0.eliminated', false)
        );
    }

    public function test_all_bets_are_sorted_alphabetically_by_participant_name(): void
    {
        $match = $this->makeMatch();
        $zara  = $this->makeParticipant('Zara');
        $adam  = $this->makeParticipant('Adam');

        $this->makeBet($zara, $match, ['prediction_1x2' => '2', 'is_correct' => false]);
        $this->makeBet($adam, $match, ['prediction_1x2' => '1', 'is_correct' => true]);

        $response = $this->actingAs($this->user)->get(route('results.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('matches.0.all_bets.0.participant_name', 'Adam')
            ->where('matches.0.all_bets.1.participant_name', 'Zara')
        );
    }

    public function test_eliminated_flag_is_propagated_from_participant(): void
    {
        $match = $this->makeMatch();
        $elim  = $this->makeParticipant('Eliminated', eliminated: true);
        $this->makeBet($elim, $match, ['prediction_1x2' => 'X', 'is_correct' => false]);

        $response = $this->actingAs($this->user)->get(route('results.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('matches.0.all_bets.0.eliminated', true)
        );
    }

    public function test_multiple_participants_bets_are_all_included(): void
    {
        $match = $this->makeMatch();
        $p1    = $this->makeParticipant('Beta');
        $p2    = $this->makeParticipant('Alpha');
        $p3    = $this->makeParticipant('Gamma');

        $this->makeBet($p1, $match, ['prediction_1x2' => '1']);
        $this->makeBet($p2, $match, ['prediction_1x2' => 'X']);
        $this->makeBet($p3, $match, ['prediction_1x2' => '2']);

        $response = $this->actingAs($this->user)->get(route('results.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('matches.0.all_bets', 3)
            ->where('matches.0.total_bets', 3)
        );
    }

    public function test_bets_from_other_matches_do_not_leak_into_wrong_match(): void
    {
        $match1 = $this->makeMatch(['home_team' => 'Poland', 'away_team' => 'Brazil', 'kickoff_at' => now()->subHours(4)]);
        $match2 = $this->makeMatch(['home_team' => 'France', 'away_team' => 'Germany', 'kickoff_at' => now()->subHours(2)]);

        $p1 = $this->makeParticipant('OnlyMatch1');
        $this->makeBet($p1, $match1, ['prediction_1x2' => '1']);

        $response = $this->actingAs($this->user)->get(route('results.index'));

        // Results ordered desc by kickoff; match2 is more recent so it comes first
        $response->assertInertia(fn ($page) => $page
            ->where('matches.0.id', $match2->id)
            ->where('matches.0.all_bets', [])
            ->where('matches.1.id', $match1->id)
            ->has('matches.1.all_bets', 1)
        );
    }
}
