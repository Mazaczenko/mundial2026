<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { teamCode } from '@/utils/teamCode';

interface ParticipantData {
    id: number;
    name: string;
    eliminated: boolean;
    paid_entry: boolean;
}

interface RankingData {
    position: number | null;
    points: number;
}

interface Stats {
    bets_placed: number;
    correct_1x2: number;
    accuracy_pct: number | null;
    exact_scores: number;
    missed: number;
    best_streak: number;
    current_streak: number;
    group_correct: number;
    group_bets: number;
    knockout_correct: number;
    knockout_bets: number;
    fav_prediction: '1' | 'X' | '2' | null;
}

interface MyBetHistory {
    prediction_1x2: '1' | 'X' | '2';
    predicted_home: number | null;
    predicted_away: number | null;
    is_correct: boolean | null;
}

interface BetHistoryEntry {
    id: number;
    home_team: string;
    away_team: string;
    home_team_flag: string | null;
    away_team_flag: string | null;
    kickoff_at: string;
    stage: 'group' | 'r32' | 'r16' | 'qf' | 'sf' | 'final';
    group_name: string | null;
    score_home: number | null;
    score_away: number | null;
    result_type: 'FT' | 'AET' | 'PEN' | null;
    my_bet: MyBetHistory | null;
    group_total_bets: number;
    group_correct_bets: number;
    was_minority: boolean;
}

interface Badge {
    key: string;
    label: string;
    description: string;
    earned: boolean;
}

interface SimpleParticipant {
    id: number;
    name: string;
}

interface Props {
    participant: ParticipantData;
    isOwn: boolean;
    ranking: RankingData | null;
    stats: Stats;
    betHistory: BetHistoryEntry[];
    badges: Badge[];
    allParticipants: SimpleParticipant[];
}

const props = defineProps<Props>();

const compareWith = ref<number | null>(null);

function goCompare() {
    if (!compareWith.value) return;
    router.get(route('compare.index'), { a: props.participant.id, b: compareWith.value });
}

const stageLabels: Record<string, string> = {
    group: 'Grupy',
    r32: '1/32',
    r16: '1/16',
    qf: 'Ćwierćf.',
    sf: 'Półf.',
    final: 'Finał',
};

const BADGE_EMOJIS: Record<string, string> = {
    sharp_shooter: '🎯',
    hat_trick: '🔥',
    on_fire: '⚡',
    reliable: '📋',
    black_horse: '🐴',
    contrarian: '🤔',
    group_expert: '📊',
};

function badgeEmoji(key: string): string {
    return BADGE_EMOJIS[key] ?? '🏅';
}

const earnedBadges = computed(() => props.badges.filter(b => b.earned));

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('pl-PL', {
        day: 'numeric',
        month: 'short',
        timeZone: 'Europe/Warsaw',
    });
}

function resultTypeSuffix(type: string | null): string {
    if (type === 'AET') return 'po dogryw.';
    if (type === 'PEN') return 'po kar.';
    return '';
}

function betClass(bet: MyBetHistory | null): string {
    if (!bet) return 'text-gray-300 dark:text-gray-600';
    if (bet.is_correct === true) return 'text-green-600 dark:text-green-400';
    if (bet.is_correct === false) return 'text-red-500 dark:text-red-400';
    return 'text-gray-500 dark:text-gray-400';
}

function accuracyClass(pct: number | null): string {
    if (pct === null) return 'text-gray-400';
    if (pct >= 70) return 'text-green-600 dark:text-green-400';
    if (pct >= 40) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-500 dark:text-red-400';
}

function accuracyBarClass(pct: number | null): string {
    if (pct === null) return 'bg-gray-200 dark:bg-gray-600';
    if (pct >= 70) return 'bg-green-500';
    if (pct >= 40) return 'bg-yellow-500';
    return 'bg-red-500';
}

const otherParticipants = computed(() =>
    props.allParticipants.filter(p => p.id !== props.participant.id)
);

function pointsEarned(match: BetHistoryEntry): number | null {
    const bet = match.my_bet;
    if (!bet || bet.is_correct === null || match.score_home === null) return null;
    if (!bet.is_correct) return 0;
    if (bet.predicted_home !== null && bet.predicted_away !== null
        && match.score_home === bet.predicted_home
        && match.score_away === bet.predicted_away) return 2;
    return 1;
}
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`${participant.name} — Profil`" />

        <div class="py-6">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">

                <!-- Header -->
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                    <div class="px-6 py-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-3">
                                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ participant.name }}</h1>
                                    <span v-if="participant.eliminated" class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                        Wyeliminowany
                                    </span>
                                    <span v-if="participant.paid_entry" title="Wpłacił 10 zł" class="text-lg">💰</span>
                                </div>
                                <div v-if="ranking" class="mt-1 flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                                    <span v-if="ranking.position">
                                        Miejsce <span class="font-semibold text-gray-900 dark:text-white">{{ ranking.position }}</span>
                                    </span>
                                    <span>
                                        <span class="font-semibold text-gray-900 dark:text-white">{{ ranking.points }}</span> pkt
                                    </span>
                                </div>
                            </div>

                            <!-- Earned badges -->
                            <div v-if="earnedBadges.length > 0" class="flex flex-wrap justify-end gap-1">
                                <span
                                    v-for="badge in earnedBadges"
                                    :key="badge.key"
                                    :title="`${badge.label}: ${badge.description}`"
                                    class="cursor-default text-xl"
                                >{{ badgeEmoji(badge.key) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats cards -->
                <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Trafność</p>
                        <p class="mt-1 text-2xl font-bold" :class="accuracyClass(stats.accuracy_pct)">
                            {{ stats.accuracy_pct !== null ? stats.accuracy_pct + '%' : '—' }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-400">{{ stats.correct_1x2 }}/{{ stats.bets_placed }} typów</p>
                    </div>

                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Dokładne</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.exact_scores }}</p>
                        <p class="mt-0.5 text-xs text-gray-400">dokładnych wyników</p>
                    </div>

                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Seria</p>
                        <p class="mt-1 text-2xl font-bold" :class="stats.current_streak >= 3 ? 'text-orange-500' : 'text-gray-900 dark:text-white'">
                            <span v-if="stats.current_streak > 0">🔥 </span>{{ stats.current_streak }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-400">z rzędu</p>
                    </div>

                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Najlepsza</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.best_streak }}</p>
                        <p class="mt-0.5 text-xs text-gray-400">rekord serii</p>
                    </div>
                </div>

                <!-- Badges section (all badges) -->
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                    <div class="border-b border-gray-100 px-5 py-3 dark:border-gray-700">
                        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Odznaki</h2>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        <div
                            v-for="badge in badges"
                            :key="badge.key"
                            class="flex items-center gap-3 px-5 py-3"
                            :class="badge.earned ? '' : 'opacity-40'"
                        >
                            <span class="text-2xl">{{ badgeEmoji(badge.key) }}</span>
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ badge.label }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ badge.description }}</p>
                            </div>
                            <span v-if="badge.earned" class="ml-auto text-xs font-medium text-green-600 dark:text-green-400">Zdobyta</span>
                        </div>
                    </div>
                </div>

                <!-- Extra stats: groups/knockout + missed -->
                <div class="mb-6 grid grid-cols-3 gap-3">
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800 text-center">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Grupy</p>
                        <p class="mt-1 text-lg font-bold text-gray-900 dark:text-white">{{ stats.group_correct }}/{{ stats.group_bets }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800 text-center">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Puchar</p>
                        <p class="mt-1 text-lg font-bold text-gray-900 dark:text-white">
                            <span v-if="stats.knockout_bets > 0">{{ stats.knockout_correct }}/{{ stats.knockout_bets }}</span>
                            <span v-else class="text-gray-400">—</span>
                        </p>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800 text-center">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Pominięte</p>
                        <p class="mt-1 text-lg font-bold" :class="stats.missed > 0 ? 'text-orange-500' : 'text-gray-900 dark:text-white'">{{ stats.missed }}</p>
                    </div>
                </div>

                <!-- Compare widget -->
                <div class="mb-6 rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                    <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Porównaj z…</h2>
                    <div class="flex gap-2">
                        <select
                            v-model="compareWith"
                            class="flex-1 rounded-md border border-gray-300 bg-white py-1.5 pl-3 pr-8 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <option :value="null">Wybierz uczestnika…</option>
                            <option v-for="p in otherParticipants" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <button
                            @click="goCompare"
                            :disabled="!compareWith"
                            class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-40 dark:bg-indigo-500 dark:hover:bg-indigo-600"
                        >Porównaj</button>
                    </div>
                </div>

                <!-- Bet history -->
                <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                    <div class="border-b border-gray-100 px-5 py-3 dark:border-gray-700">
                        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Historia meczów</h2>
                    </div>

                    <div v-if="betHistory.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                        Brak rozegranych meczów.
                    </div>

                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        <div
                            v-for="match in betHistory"
                            :key="match.id"
                            class="flex items-center gap-3 px-5 py-3"
                        >
                            <!-- Teams + score -->
                            <div class="flex flex-1 items-center gap-1.5 min-w-0">
                                <span v-if="match.home_team_flag && match.home_team_flag.length <= 4" class="text-base leading-none">{{ match.home_team_flag }}</span>
                                <img v-else-if="match.home_team_flag" :src="match.home_team_flag" class="h-3.5 w-5 object-contain" :alt="match.home_team" />
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ teamCode(match.home_team) }}</span>
                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 tabular-nums">{{ match.score_home }}:{{ match.score_away }}</span>
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ teamCode(match.away_team) }}</span>
                                <span v-if="match.away_team_flag && match.away_team_flag.length <= 4" class="text-base leading-none">{{ match.away_team_flag }}</span>
                                <img v-else-if="match.away_team_flag" :src="match.away_team_flag" class="h-3.5 w-5 object-contain" :alt="match.away_team" />
                            </div>

                            <!-- Stage + date -->
                            <div class="hidden text-right sm:block">
                                <p class="text-xs text-gray-400">{{ stageLabels[match.stage] }}</p>
                                <p class="text-xs text-gray-400">{{ formatDate(match.kickoff_at) }}</p>
                            </div>

                            <!-- My bet -->
                            <div class="w-20 text-right">
                                <div v-if="match.my_bet">
                                    <span class="text-sm font-bold" :class="betClass(match.my_bet)">
                                        {{ match.my_bet.prediction_1x2 }}
                                        <span v-if="match.my_bet.is_correct === true">✓</span>
                                        <span v-else-if="match.my_bet.is_correct === false">✗</span>
                                    </span>
                                    <!-- Dla fazy pucharowej: obstawiony wynik -->
                                    <span v-if="match.stage !== 'group' && match.my_bet.predicted_home !== null"
                                          class="block text-xs text-gray-400 dark:text-gray-500 tabular-nums">
                                        {{ match.my_bet.predicted_home }}:{{ match.my_bet.predicted_away }}
                                    </span>
                                    <span
                                        v-if="match.stage !== 'group' && pointsEarned(match) !== null"
                                        class="mt-0.5 inline-block rounded-full px-1.5 py-0.5 text-xs font-bold"
                                        :class="pointsEarned(match) === 2
                                            ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                                            : pointsEarned(match)! > 0
                                                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                : 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400'"
                                    >
                                        +{{ pointsEarned(match) }}p
                                    </span>
                                    <span
                                        v-if="match.was_minority && match.my_bet.is_correct === true"
                                        class="block text-xs text-purple-600 dark:text-purple-400"
                                        title="Trafił będąc w mniejszości"
                                    >🐴 mniejszość</span>
                                </div>
                                <span v-else class="text-sm text-gray-300 dark:text-gray-600">—</span>
                            </div>

                            <!-- Group accuracy -->
                            <div class="hidden w-16 text-right text-xs text-gray-400 dark:text-gray-500 sm:block">
                                {{ match.group_correct_bets }}/{{ match.group_total_bets }}
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
