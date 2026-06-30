<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import type { MatchData, OtherBet } from '@/types';

interface Props {
    matchesByDate: Record<string, MatchData[]>;
    tab: 'today' | 'upcoming' | 'past';
    tabCounts: { today: number; upcoming: number; past: number };
    filters: { team: string; bet: string; result: string };
    teams: string[];
    participant: { id: number; name: string; eliminated: boolean; paid_entry: boolean };
}

const props = defineProps<Props>();

const stageLabels: Record<string, string> = {
    group: 'Faza grupowa',
    r32: '1/32 finału',
    r16: '1/16 finału',
    qf: 'Ćwierćfinał',
    sf: 'Półfinał',
    final: 'Finał',
};

function optionLabel(opt: string, match: MatchData): string {
    if (opt === '1') return match.home_team;
    if (opt === '2') return match.away_team;
    return 'Remis';
}

import { teamCode } from '@/utils/teamCode';

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('pl-PL', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        timeZone: 'Europe/Warsaw',
    });
}

function formatTime(iso: string): string {
    return new Date(iso).toLocaleTimeString('pl-PL', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Europe/Warsaw',
    });
}

const allMatches = computed(() =>
    Object.values(props.matchesByDate).flat()
);

const hasLive = computed(() =>
    allMatches.value.some(m => m.status === 'in_play')
);

const hasKnockoutMatches = computed(() =>
    Object.values(props.matchesByDate).flat().some(m => m.stage !== 'group')
);

let liveInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    if (hasLive.value) {
        liveInterval = setInterval(() => {
            router.reload({ only: ['matchesByDate'] });
        }, 30000);
    }
});

onUnmounted(() => {
    if (liveInterval) clearInterval(liveInterval);
});

const betForms = ref<Record<number, ReturnType<typeof useForm>>>({});

function getOrCreateForm(match: MatchData) {
    if (!betForms.value[match.id]) {
        betForms.value[match.id] = useForm({
            match_id: match.id,
            prediction_1x2: match.my_bet?.prediction_1x2 ?? '',
            predicted_home: match.my_bet?.predicted_home ?? null,
            predicted_away: match.my_bet?.predicted_away ?? null,
        });
    }
    return betForms.value[match.id];
}

function submitBet(match: MatchData) {
    const form = getOrCreateForm(match);
    if (match.my_bet) {
        form.put(route('bets.update', match.my_bet.id), { preserveScroll: true });
    } else {
        form.post(route('bets.store'), { preserveScroll: true });
    }
}

function isKnockout(stage: string): boolean {
    return stage !== 'group';
}

function scoreLabel(match: MatchData): { ft90: string; etTotal: string | null; pen: string | null } {
    const h = match.score_home;
    const a = match.score_away;
    if (h === null || a === null) return { ft90: '–:–', etTotal: null, pen: null };

    // score_home/score_away store actual 90-min score; score_home_et stores additional ET goals
    const etH = match.score_home_et ?? 0;
    const etA = match.score_away_et ?? 0;

    const etTotal = (match.result_type === 'AET' || match.result_type === 'PEN') && match.score_home_et !== null
        ? `${h + etH}:${a + etA}`
        : null;

    const pen = match.result_type === 'PEN' && match.score_home_pen !== null
        ? `${match.score_home_pen}:${match.score_away_pen}`
        : null;

    return { ft90: `${h}:${a}`, etTotal, pen };
}

// --- Tabs ---
const tabs: Array<{ key: 'today' | 'upcoming' | 'past'; label: string }> = [
    { key: 'today',    label: 'Dzisiaj' },
    { key: 'upcoming', label: 'Nadchodzące' },
    { key: 'past',     label: 'Poprzednie' },
];

function goToTab(tabKey: 'today' | 'upcoming' | 'past') {
    router.get(route('bets.index'), { tab: tabKey }, { preserveScroll: false });
}

// --- Filters ---
const teamFilter   = ref(props.filters.team);
const betFilter    = ref(props.filters.bet);
const resultFilter = ref(props.filters.result);

function applyFilters() {
    router.get(
        route('bets.index'),
        {
            tab:    props.tab,
            team:   teamFilter.value,
            bet:    betFilter.value,
            result: resultFilter.value,
        },
        { preserveScroll: true },
    );
}

const hasActiveFilters = computed(
    () => teamFilter.value !== '' || betFilter.value !== '' || resultFilter.value !== '',
);

function clearFilters() {
    teamFilter.value   = '';
    betFilter.value    = '';
    resultFilter.value = '';
    router.get(route('bets.index'), { tab: props.tab }, { preserveScroll: false });
}

function betPillClass(bet: OtherBet): string {
    if (bet.is_correct === true) return 'bg-green-50 border-green-200 dark:bg-green-900/20 dark:border-green-800';
    if (bet.is_correct === false) return 'bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800';
    return 'bg-gray-50 border-gray-200 dark:bg-gray-800 dark:border-gray-700';
}

function betTextClass(bet: OtherBet): string {
    if (bet.is_correct === true) return 'text-green-700 dark:text-green-400';
    if (bet.is_correct === false) return 'text-red-600 dark:text-red-400';
    return 'text-gray-500 dark:text-gray-400';
}

function truncateName(name: string, max = 12): string {
    return name.length > max ? name.slice(0, max) + '…' : name;
}

function barWidths(stats: Record<'1'|'X'|'2', number>): Record<'1'|'X'|'2', number> {
    const opts = ['1', 'X', '2'] as const;
    const nonZero = opts.filter(o => stats[o] > 0);
    const count = nonZero.length;
    if (count === 0) return stats;
    const minPer = count >= 2 ? 20 : 0;
    const remaining = 100 - minPer * count;
    const result = {} as Record<'1'|'X'|'2', number>;
    for (const o of opts) {
        result[o] = stats[o] === 0 ? 0 : minPer + (stats[o] / 100) * remaining;
    }
    return result;
}

// Empty state message
const emptyMessage = computed(() => {
    if (hasActiveFilters.value) {
        return 'Brak meczów pasujących do wybranych filtrów.';
    }
    if (props.tab === 'today') return 'Brak meczów na dziś.';
    if (props.tab === 'upcoming') return 'Brak nadchodzących meczów.';
    return 'Brak poprzednich meczów.';
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Typy" />

        <div class="py-6">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">

                <!-- Eliminated warning -->
                <div v-if="participant.eliminated" class="mb-4 rounded-md bg-orange-50 border border-orange-200 p-4 text-sm text-orange-800 dark:bg-orange-900/20 dark:text-orange-300">
                    Zostałeś wyeliminowany z oficjalnego rankingu (3 nieoobstawione mecze). Możesz nadal typować dla zabawy.
                </div>

                <!-- Tabs -->
                <div class="mb-4 flex gap-1 rounded-lg bg-gray-100 p-1 dark:bg-gray-800">
                    <button
                        v-for="t in tabs"
                        :key="t.key"
                        @click="goToTab(t.key)"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                        :class="tab === t.key
                            ? 'bg-white text-indigo-600 shadow-sm dark:bg-gray-700 dark:text-indigo-400'
                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                    >
                        {{ t.label }}
                        <span
                            class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full px-1.5 py-0.5 text-xs font-semibold"
                            :class="tab === t.key
                                ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'
                                : 'bg-gray-200 text-gray-500 dark:bg-gray-600 dark:text-gray-400'"
                        >
                            {{ tabCounts[t.key] }}
                        </span>
                    </button>
                </div>

                <!-- Filters -->
                <div class="mb-6 flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:flex-row sm:items-center">
                    <!-- Team -->
                    <div class="flex-1">
                        <select
                            v-model="teamFilter"
                            @change="applyFilters"
                            class="w-full rounded-md border border-gray-300 bg-white py-1.5 pl-3 pr-8 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <option value="">Wszystkie drużyny</option>
                            <option v-for="team in teams" :key="team" :value="team">{{ team }}</option>
                        </select>
                    </div>

                    <!-- Bet type -->
                    <div class="flex-1">
                        <select
                            v-model="betFilter"
                            @change="applyFilters"
                            class="w-full rounded-md border border-gray-300 bg-white py-1.5 pl-3 pr-8 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <option value="">Wszystkie zakłady</option>
                            <option value="1">Typ: 1</option>
                            <option value="X">Typ: X</option>
                            <option value="2">Typ: 2</option>
                            <option value="placed">Obstawione</option>
                            <option value="missing">Nieobstawione</option>
                        </select>
                    </div>

                    <!-- Result -->
                    <div class="flex-1">
                        <select
                            v-model="resultFilter"
                            @change="applyFilters"
                            class="w-full rounded-md border border-gray-300 bg-white py-1.5 pl-3 pr-8 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <option value="">Wszystkie wyniki</option>
                            <option value="correct">Trafione ✓</option>
                            <option value="wrong">Chybione ✗</option>
                            <option value="pending">Oczekujące</option>
                        </select>
                    </div>

                    <!-- Clear filters -->
                    <button
                        v-if="hasActiveFilters"
                        @click="clearFilters"
                        class="shrink-0 rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-500 hover:border-gray-400 hover:text-gray-700 dark:border-gray-600 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        Wyczyść
                    </button>
                </div>

                <!-- Knockout phase banner -->
                <div v-if="hasKnockoutMatches" class="mb-6 flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-800/50 dark:bg-amber-900/20">
                    <span class="shrink-0 text-lg text-amber-500">&#9917;</span>
                    <div>
                        <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Faza pucharowa!</p>
                        <p class="text-xs text-amber-700 dark:text-amber-400">Typuj mecze 1/32 finału — pamiętaj o dokładnym wyniku!</p>
                    </div>
                </div>

                <!-- Match list -->
                <div v-for="(matches, date) in matchesByDate" :key="date" class="mb-8">
                    <h2 class="mb-3 text-lg font-semibold capitalize text-gray-700 dark:text-gray-300">
                        {{ formatDate(matches[0].kickoff_at) }}
                    </h2>

                    <div class="space-y-4">
                        <div
                            v-for="match in matches"
                            :key="match.id"
                            class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800"
                            :class="isKnockout(match.stage) ? 'border-l-4 border-amber-400 dark:border-amber-500' : ''"
                        >
                            <!-- Match header -->
                            <div class="p-4">
                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-xs font-medium uppercase tracking-wide"
                                        :class="isKnockout(match.stage) ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400 dark:text-gray-500'"
                                    >
                                        {{ stageLabels[match.stage] }}
                                        <span v-if="match.group_name"> · Gr. {{ match.group_name }}</span>
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <span v-if="match.status === 'in_play'" class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-600 dark:bg-red-900/30 dark:text-red-400">
                                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-red-500"></span>
                                            LIVE
                                        </span>
                                        <span class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ formatTime(match.kickoff_at) }}
                                        </span>
                                    </div>
                                </div>

                                <div class="mt-3 grid items-start gap-2" style="grid-template-columns: 1fr 5rem 1fr">
                                    <!-- Home team -->
                                    <div class="flex min-w-0 flex-col items-center text-center">
                                        <span class="text-3xl" v-if="match.home_team_flag && match.home_team_flag.length <= 4">{{ match.home_team_flag }}</span>
                                        <img v-else-if="match.home_team_flag" :src="match.home_team_flag" class="h-8 w-12 object-contain" :alt="match.home_team" />
                                        <abbr class="mt-1 cursor-default text-sm font-bold tracking-wide text-gray-900 no-underline dark:text-white" :title="match.home_team">{{ teamCode(match.home_team) }}</abbr>
                                    </div>

                                    <!-- Score / VS -->
                                    <div class="text-center">
                                        <template v-if="match.status === 'finished'">
                                            <div class="text-2xl font-bold text-gray-900 dark:text-white tabular-nums">
                                                {{ scoreLabel(match).ft90 }}
                                            </div>
                                            <div class="mt-0.5 text-xs text-gray-400">90'</div>
                                            <template v-if="scoreLabel(match).etTotal !== null">
                                                <div class="mt-1 text-sm font-semibold tabular-nums text-indigo-600 dark:text-indigo-400">
                                                    {{ scoreLabel(match).etTotal }}
                                                </div>
                                                <div class="text-xs text-gray-400">po dogrywce</div>
                                            </template>
                                            <template v-if="scoreLabel(match).pen !== null">
                                                <div class="mt-1 text-sm font-semibold tabular-nums text-amber-600 dark:text-amber-400">
                                                    {{ scoreLabel(match).pen }}
                                                </div>
                                                <div class="text-xs text-gray-400">karne</div>
                                            </template>
                                        </template>
                                        <template v-else-if="match.status === 'in_play'">
                                            <div class="text-2xl font-bold text-red-600 dark:text-red-400">
                                                {{ match.score_home }} : {{ match.score_away }}
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div class="text-xl font-light text-gray-400">vs</div>
                                            <div v-if="!match.can_bet" class="mt-1 text-xs text-red-500">Deadline minął</div>
                                        </template>
                                    </div>

                                    <!-- Away team -->
                                    <div class="flex min-w-0 flex-col items-center text-center">
                                        <span class="text-3xl" v-if="match.away_team_flag && match.away_team_flag.length <= 4">{{ match.away_team_flag }}</span>
                                        <img v-else-if="match.away_team_flag" :src="match.away_team_flag" class="h-8 w-12 object-contain" :alt="match.away_team" />
                                        <abbr class="mt-1 cursor-default text-sm font-bold tracking-wide text-gray-900 no-underline dark:text-white" :title="match.away_team">{{ teamCode(match.away_team) }}</abbr>
                                    </div>
                                </div>

                                <!-- Goals row -->
                                <div
                                    v-if="match.status !== 'scheduled' && match.goals.length > 0"
                                    class="mt-2 grid gap-x-2 text-xs text-gray-400 dark:text-gray-500"
                                    style="grid-template-columns: 1fr 5rem 1fr"
                                >
                                    <div class="space-y-0.5 text-right">
                                        <div v-for="(goal, i) in match.goals.filter(g => g.team_side === 'home')" :key="i">
                                            {{ goal.player_name }}<span v-if="goal.own_goal"> (og)</span>
                                            <span v-if="goal.minute" class="ml-1 tabular-nums text-gray-300 dark:text-gray-600">{{ goal.minute }}'</span>
                                        </div>
                                    </div>
                                    <div></div>
                                    <div class="space-y-0.5 text-left">
                                        <div v-for="(goal, i) in match.goals.filter(g => g.team_side === 'away')" :key="i">
                                            <span v-if="goal.minute" class="mr-1 tabular-nums text-gray-300 dark:text-gray-600">{{ goal.minute }}'</span>
                                            {{ goal.player_name }}<span v-if="goal.own_goal"> (og)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bet form (can still bet) -->
                            <div v-if="match.can_bet" class="border-t border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50">
                                <form @submit.prevent="submitBet(match)">
                                    <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Twój typ:</p>
                                    <div class="grid grid-cols-3 gap-2">
                                        <!-- 1 — home -->
                                        <button
                                            type="button"
                                            @click="getOrCreateForm(match).prediction_1x2 = '1'"
                                            class="flex flex-col items-center gap-1 rounded-lg border-2 px-2 py-3 transition-colors"
                                            :class="getOrCreateForm(match).prediction_1x2 === '1'
                                                ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30'
                                                : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-600 dark:bg-gray-800'"
                                        >
                                            <span class="text-xs font-bold text-gray-400 dark:text-gray-500">1</span>
                                            <span v-if="match.home_team_flag && match.home_team_flag.length <= 4" class="text-3xl leading-none">{{ match.home_team_flag }}</span>
                                            <img v-else-if="match.home_team_flag" :src="match.home_team_flag" :alt="match.home_team" class="h-7 w-10 object-contain" />
                                            <span class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ teamCode(match.home_team) }}</span>
                                        </button>

                                        <!-- X — remis -->
                                        <button
                                            type="button"
                                            @click="getOrCreateForm(match).prediction_1x2 = 'X'"
                                            class="flex flex-col items-center justify-center gap-1 rounded-lg border-2 px-2 py-3 transition-colors"
                                            :class="getOrCreateForm(match).prediction_1x2 === 'X'
                                                ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30'
                                                : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-600 dark:bg-gray-800'"
                                        >
                                            <span class="text-xs font-bold text-gray-400 dark:text-gray-500">X</span>
                                            <span class="text-2xl font-black leading-none text-gray-500 dark:text-gray-400">=</span>
                                            <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Remis</span>
                                        </button>

                                        <!-- 2 — away -->
                                        <button
                                            type="button"
                                            @click="getOrCreateForm(match).prediction_1x2 = '2'"
                                            class="flex flex-col items-center gap-1 rounded-lg border-2 px-2 py-3 transition-colors"
                                            :class="getOrCreateForm(match).prediction_1x2 === '2'
                                                ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30'
                                                : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-600 dark:bg-gray-800'"
                                        >
                                            <span class="text-xs font-bold text-gray-400 dark:text-gray-500">2</span>
                                            <span v-if="match.away_team_flag && match.away_team_flag.length <= 4" class="text-3xl leading-none">{{ match.away_team_flag }}</span>
                                            <img v-else-if="match.away_team_flag" :src="match.away_team_flag" :alt="match.away_team" class="h-7 w-10 object-contain" />
                                            <span class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ teamCode(match.away_team) }}</span>
                                        </button>
                                    </div>

                                    <!-- Exact score (knockout only) -->
                                    <div v-if="isKnockout(match.stage)" class="mt-3 flex items-center gap-2">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Dokładny wynik:</span>
                                        <input
                                            type="number"
                                            min="0"
                                            max="20"
                                            v-model.number="getOrCreateForm(match).predicted_home"
                                            class="w-14 rounded border border-gray-300 px-2 py-1 text-center text-sm focus:border-indigo-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                            placeholder="–"
                                        />
                                        <span class="text-gray-500">:</span>
                                        <input
                                            type="number"
                                            min="0"
                                            max="20"
                                            v-model.number="getOrCreateForm(match).predicted_away"
                                            class="w-14 rounded border border-gray-300 px-2 py-1 text-center text-sm focus:border-indigo-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                            placeholder="–"
                                        />
                                        <span class="text-xs text-gray-400">na koniec drugiej połowy</span>
                                    </div>

                                    <div class="mt-3">
                                        <button
                                            type="submit"
                                            :disabled="!getOrCreateForm(match).prediction_1x2 || getOrCreateForm(match).processing"
                                            class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                                        >
                                            {{ match.my_bet ? 'Aktualizuj' : 'Zapisz typ' }}
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Result display (after deadline or finished) -->
                            <div v-else class="border-t border-gray-100 px-4 py-3 dark:border-gray-700">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Twój typ: </span>
                                        <template v-if="match.my_bet">
                                            <span
                                                class="font-semibold"
                                                :class="{
                                                    'text-green-600': match.my_bet.is_correct === true,
                                                    'text-red-500': match.my_bet.is_correct === false,
                                                    'text-gray-800 dark:text-gray-200': match.my_bet.is_correct === null,
                                                }"
                                            >
                                                {{ optionLabel(match.my_bet.prediction_1x2, match) }}
                                                <span v-if="match.my_bet.is_correct === true"> ✓</span>
                                                <span v-else-if="match.my_bet.is_correct === false"> ✗</span>
                                            </span>
                                            <template v-if="isKnockout(match.stage) && match.my_bet.predicted_home !== null && match.my_bet.predicted_away !== null">
                                                <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">
                                                    wynik: <span class="font-semibold tabular-nums text-gray-700 dark:text-gray-300">{{ match.my_bet.predicted_home }}:{{ match.my_bet.predicted_away }}</span>
                                                </span>
                                                <template v-if="match.status === 'finished' && match.score_home !== null">
                                                    <span
                                                        v-if="match.my_bet.predicted_home === match.score_home && match.my_bet.predicted_away === match.score_away"
                                                        class="ml-1 text-xs font-semibold text-amber-600 dark:text-amber-400"
                                                    >+1 pkt</span>
                                                </template>
                                            </template>
                                        </template>
                                        <span v-else class="text-gray-400">–</span>
                                    </div>
                                </div>

                                <!-- Bet stats bar -->
                                <div v-if="match.bet_stats" class="mt-3">
                                    <div class="flex overflow-hidden rounded-md text-xs font-semibold">
                                        <div
                                            v-for="opt in (['1', 'X', '2'] as const)"
                                            :key="opt"
                                            class="flex items-center justify-center gap-1 overflow-hidden py-1.5 transition-all"
                                            :class="{
                                                'bg-indigo-600 text-white': match.my_bet?.prediction_1x2 === opt,
                                                'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300': match.my_bet?.prediction_1x2 !== opt,
                                            }"
                                            :style="{ width: barWidths(match.bet_stats)[opt] + '%' }"
                                        >
                                            <template v-if="match.bet_stats[opt] > 0">
                                                <span>{{ opt }}</span>
                                                <span class="opacity-75">{{ match.bet_stats[opt] }}%</span>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="mt-1 text-right text-xs text-gray-400 dark:text-gray-500">
                                        {{ match.bet_stats.total }} {{ match.bet_stats.total === 1 ? 'typ' : match.bet_stats.total < 5 ? 'typy' : 'typów' }}
                                    </div>
                                </div>

                                <!-- Others' bets (after deadline) -->
                                <div v-if="match.others_bets.length > 0" class="mt-2">
                                    <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-3 lg:grid-cols-4">
                                        <div
                                            v-for="ob in match.others_bets"
                                            :key="ob.participant_name"
                                            class="flex items-center justify-between rounded border px-2 py-1 text-xs"
                                            :class="[betPillClass(ob), ob.eliminated ? 'opacity-50' : '']"
                                        >
                                            <span class="truncate text-gray-700 dark:text-gray-300" :title="ob.participant_name">
                                                {{ truncateName(ob.participant_name) }}
                                            </span>
                                            <span class="ml-1.5 shrink-0 font-bold tabular-nums" :class="betTextClass(ob)">
                                                {{ optionLabel(ob.prediction_1x2, match) }}
                                                <template v-if="ob.predicted_home !== null && ob.predicted_away !== null">
                                                    <span class="font-normal opacity-70"> {{ ob.predicted_home }}:{{ ob.predicted_away }}</span>
                                                </template>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="Object.keys(matchesByDate).length === 0" class="rounded-lg bg-white p-8 text-center text-gray-500 shadow dark:bg-gray-800">
                    {{ emptyMessage }}
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
