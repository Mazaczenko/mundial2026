import { Config } from 'ziggy-js';

export interface Participant {
    id: number;
    name: string;
    is_admin: boolean;
    paid_entry: boolean;
    eliminated: boolean;
}

export interface Bet {
    id: number;
    prediction_1x2: '1' | 'X' | '2';
    predicted_home: number | null;
    predicted_away: number | null;
    is_correct: boolean | null;
}

export interface OtherBet {
    participant_name: string;
    prediction_1x2: '1' | 'X' | '2';
}

export interface MatchData {
    id: number;
    home_team: string;
    away_team: string;
    home_team_flag: string | null;
    away_team_flag: string | null;
    kickoff_at: string;
    stage: 'group' | 'r32' | 'r16' | 'qf' | 'sf' | 'final';
    group_name: string | null;
    status: 'scheduled' | 'finished';
    score_home: number | null;
    score_away: number | null;
    can_bet: boolean;
    my_bet: Bet | null;
    others_bets: OtherBet[];
    goals: Array<{
        player_name: string;
        team_side: 'home' | 'away';
        minute: number | null;
        own_goal: boolean;
    }>;
}

export interface RankingEntry {
    id: number;
    name: string;
    points: number;
    exact_scores: number;
    group_correct: number;
    scorer_correct: boolean;
    paid_entry: boolean;
    eliminated: boolean;
    bets_count: number;
    missed_count: number;
}

export interface GroupStanding {
    id: number;
    group_name: string;
    api_team_id: number;
    team_name: string;
    team_flag: string | null;
    position: number;
    played: number;
    won: number;
    drawn: number;
    lost: number;
    goals_for: number;
    goals_against: number;
    points: number;
}

export interface TiebreakerPick {
    id: number;
    top_scorer_name: string;
    submitted_at: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: Participant | null;
    };
    flash: {
        success: string | null;
        error: string | null;
    };
    ziggy: Config & { location: string };
};
