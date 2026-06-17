<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { teamCode } from '@/utils/teamCode';

interface Goal {
    player_name: string;
    team_side: 'home' | 'away';
    minute: string | null;
    own_goal: boolean;
}

interface MyBet {
    prediction_1x2: '1' | 'X' | '2';
    is_correct: boolean | null;
}

interface MatchResult {
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
    correct_bets: number;
    total_participants: number;
    my_bet: MyBet | null;
    goals: Goal[];
}

interface Stats {
    total_matches: number;
    my_correct: number;
    my_total: number;
    my_missed: number;
    my_accuracy: number | null;
    streak: number;
}

interface TopScorer {
    name: string;
    goals: number;
}

interface Props {
    matches: MatchResult[];
    stats: Stats;
    teams: string[];
    topScorers: TopScorer[];
    filters: { team: string; stage: string; result: string };
}

const props = defineProps<Props>();

const stageLabels: Record<string, string> = {
    group: 'Grupy',
    r32: '1/32 finału',
    r16: '1/16 finału',
    qf: 'Ćwierćfinał',
    sf: 'Półfinał',
    final: 'Finał',
};

const stageBadgeClass: Record<string, string> = {
    group:  'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
    r32:    'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400',
    r16:    'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400',
    qf:     'bg-purple-50 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400',
    sf:     'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400',
    final:  'bg-yellow-50 text-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-400',
};

function resultTypeSuffix(type: string | null): string {
    if (type === 'AET') return 'po dogryw.';
    if (type === 'PEN') return 'po kar.';
    return '';
}

function accuracyPercent(match: MatchResult): number {
    return match.total_participants > 0
        ? Math.round((match.correct_bets / match.total_participants) * 100)
        : 0;
}

function accuracyBarClass(pct: number): string {
    if (pct >= 70) return 'bg-green-500';
    if (pct >= 40) return 'bg-yellow-500';
    return 'bg-red-400';
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('pl-PL', {
        day: 'numeric',
        month: 'short',
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

const expandedGoals = ref<Record<number, boolean>>({});
function toggleGoals(id: number) {
    expandedGoals.value = { ...expandedGoals.value, [id]: !expandedGoals.value[id] };
}

function homeGoals(match: MatchResult) {
    return match.goals.filter(g => g.team_side === 'home');
}
function awayGoals(match: MatchResult) {
    return match.goals.filter(g => g.team_side === 'away');
}

const teamFilter   = ref(props.filters.team);
const stageFilter  = ref(props.filters.stage);
const resultFilter = ref(props.filters.result);

const hasActiveFilters = computed(
    () => teamFilter.value !== '' || stageFilter.value !== '' || resultFilter.value !== '',
);

function applyFilters() {
    router.get(route('results.index'), {
        team:   teamFilter.value,
        stage:  stageFilter.value,
        result: resultFilter.value,
    }, { preserveScroll: true });
}

function clearFilters() {
    teamFilter.value   = '';
    stageFilter.value  = '';
    resultFilter.value = '';
    router.get(route('results.index'), {}, { preserveScroll: false });
}

const positionColor = (i: number) => {
    if (i === 0) return 'text-yellow-500';
    if (i === 1) return 'text-gray-400 dark:text-gray-500';
    if (i === 2) return 'text-amber-600 dark:text-amber-500';
    return 'text-gray-300 dark:text-gray-600';
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Wyniki meczów" />

        <div class="py-6">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">

                <!-- Stats cards -->
                <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <!-- Rozegrane -->
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Rozegrane</p>
                        <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-white">{{ stats.total_matches }}</p>
                        <p class="mt-0.5 text-xs text-gray-400">meczów</p>
                    </div>

                    <!-- Trafienia -->
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Trafienia</p>
                        <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-white">
                            {{ stats.my_correct }}<span class="ml-0.5 text-lg font-normal text-gray-400">/{{ stats.my_total }}</span>
                        </p>
                        <p class="mt-0.5 text-xs text-gray-400">
                            {{ stats.my_accuracy !== null ? stats.my_accuracy + '% skuteczności' : 'brak typów' }}
                        </p>
                    </div>

                    <!-- Pominięte -->
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Pominięte</p>
                        <p
                            class="mt-1 text-3xl font-bold"
                            :class="stats.my_missed > 0 ? 'text-orange-500' : 'text-gray-900 dark:text-white'"
                        >
                            {{ stats.my_missed }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-400">nieobstawionych</p>
                    </div>

                    <!-- Seria -->
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Seria ✓</p>
                        <p
                            class="mt-1 text-3xl font-bold"
                            :class="stats.streak >= 3 ? 'text-green-600 dark:text-green-400' : stats.streak > 0 ? 'text-emerald-500 dark:text-emerald-400' : 'text-gray-900 dark:text-white'"
                        >
                            {{ stats.streak }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-400">z rzędu trafień</p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="mb-4 flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:flex-row sm:items-center">
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
                    <div class="flex-1">
                        <select
                            v-model="stageFilter"
                            @change="applyFilters"
                            class="w-full rounded-md border border-gray-300 bg-white py-1.5 pl-3 pr-8 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <option value="">Wszystkie fazy</option>
                            <option value="group">Faza grupowa</option>
                            <option value="knockout">Faza pucharowa</option>
                        </select>
                    </div>
                    <div class="flex-1">
                        <select
                            v-model="resultFilter"
                            @change="applyFilters"
                            class="w-full rounded-md border border-gray-300 bg-white py-1.5 pl-3 pr-8 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <option value="">Wszystkie wyniki</option>
                            <option value="correct">Trafione ✓</option>
                            <option value="wrong">Chybione ✗</option>
                            <option value="missed">Pominięte –</option>
                        </select>
                    </div>
                    <button
                        v-if="hasActiveFilters"
                        @click="clearFilters"
                        class="shrink-0 rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-500 hover:border-gray-400 hover:text-gray-700 dark:border-gray-600 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        Wyczyść
                    </button>
                </div>

                <!-- Desktop table -->
                <div v-if="matches.length > 0" class="hidden overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800 sm:block">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-700/50">
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Data</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Mecz</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Wynik</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Twój typ</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Trafialność</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Gole</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="match in matches" :key="match.id">
                                <!-- Match row -->
                                <tr class="border-b border-gray-100 transition-colors hover:bg-gray-50/60 dark:border-gray-700/50 dark:hover:bg-gray-700/20">
                                    <!-- Date -->
                                    <td class="px-4 py-3 align-top">
                                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ formatDate(match.kickoff_at) }}</div>
                                        <div class="text-xs text-gray-400">{{ formatTime(match.kickoff_at) }}</div>
                                    </td>

                                    <!-- Teams -->
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-1.5">
                                            <span v-if="match.home_team_flag && match.home_team_flag.length <= 4" class="text-lg leading-none">{{ match.home_team_flag }}</span>
                                            <img v-else-if="match.home_team_flag" :src="match.home_team_flag" class="h-4 w-6 object-contain" :alt="match.home_team" />
                                            <span class="font-semibold text-gray-900 dark:text-white">{{ teamCode(match.home_team) }}</span>
                                            <span class="text-xs text-gray-300 dark:text-gray-600">–</span>
                                            <span class="font-semibold text-gray-900 dark:text-white">{{ teamCode(match.away_team) }}</span>
                                            <span v-if="match.away_team_flag && match.away_team_flag.length <= 4" class="text-lg leading-none">{{ match.away_team_flag }}</span>
                                            <img v-else-if="match.away_team_flag" :src="match.away_team_flag" class="h-4 w-6 object-contain" :alt="match.away_team" />
                                        </div>
                                        <div class="mt-0.5 flex items-center gap-1">
                                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium" :class="stageBadgeClass[match.stage]">
                                                {{ stageLabels[match.stage] }}
                                            </span>
                                            <span v-if="match.group_name" class="text-xs text-gray-400">Gr. {{ match.group_name }}</span>
                                        </div>
                                    </td>

                                    <!-- Score -->
                                    <td class="px-4 py-3 text-center">
                                        <span class="text-lg font-bold tabular-nums text-gray-900 dark:text-white">
                                            {{ match.score_home }}:{{ match.score_away }}
                                        </span>
                                        <div v-if="resultTypeSuffix(match.result_type)" class="text-xs text-gray-400">
                                            {{ resultTypeSuffix(match.result_type) }}
                                        </div>
                                    </td>

                                    <!-- My bet -->
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            v-if="match.my_bet"
                                            class="inline-flex items-center gap-0.5 text-sm font-bold"
                                            :class="{
                                                'text-green-600 dark:text-green-400': match.my_bet.is_correct === true,
                                                'text-red-500 dark:text-red-400': match.my_bet.is_correct === false,
                                                'text-gray-500 dark:text-gray-400': match.my_bet.is_correct === null,
                                            }"
                                        >
                                            {{ match.my_bet.prediction_1x2 }}
                                            <span v-if="match.my_bet.is_correct === true">✓</span>
                                            <span v-else-if="match.my_bet.is_correct === false">✗</span>
                                        </span>
                                        <span v-else class="text-gray-300 dark:text-gray-600">–</span>
                                    </td>

                                    <!-- Accuracy -->
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="flex-1">
                                                <div class="h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                                    <div
                                                        class="h-full rounded-full transition-all"
                                                        :class="accuracyBarClass(accuracyPercent(match))"
                                                        :style="{ width: accuracyPercent(match) + '%' }"
                                                    ></div>
                                                </div>
                                            </div>
                                            <span class="w-10 text-right text-xs tabular-nums text-gray-500 dark:text-gray-400">
                                                {{ match.correct_bets }}/{{ match.total_participants }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Goals toggle -->
                                    <td class="px-4 py-3 text-center">
                                        <button
                                            v-if="match.goals.length > 0"
                                            @click="toggleGoals(match.id)"
                                            class="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                                            :title="expandedGoals[match.id] ? 'Ukryj strzelców' : 'Pokaż strzelców'"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform duration-150" :class="{ 'rotate-180': expandedGoals[match.id] }" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                        <span v-else class="text-xs text-gray-200 dark:text-gray-700">–</span>
                                    </td>
                                </tr>

                                <!-- Goals expanded row -->
                                <tr v-if="expandedGoals[match.id] && match.goals.length > 0" class="border-b border-gray-100 bg-gray-50/60 dark:border-gray-700/50 dark:bg-gray-900/20">
                                    <td colspan="6" class="px-6 py-2">
                                        <div class="flex flex-wrap gap-x-10 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                            <!-- Home goals -->
                                            <div class="flex flex-wrap items-baseline gap-x-1">
                                                <span v-if="match.home_team_flag && match.home_team_flag.length <= 4" class="text-sm">{{ match.home_team_flag }}</span>
                                                <img v-else-if="match.home_team_flag" :src="match.home_team_flag" class="h-3 w-4 object-contain" />
                                                <span class="font-medium text-gray-600 dark:text-gray-300">{{ teamCode(match.home_team) }}:</span>
                                                <template v-if="homeGoals(match).length > 0">
                                                    <span v-for="(goal, i) in homeGoals(match)" :key="i">
                                                        <template v-if="i > 0">, </template>
                                                        {{ goal.player_name }}<template v-if="goal.own_goal"> (og)</template><template v-if="goal.minute"> {{ goal.minute }}'</template>
                                                    </span>
                                                </template>
                                                <span v-else class="text-gray-300 dark:text-gray-600">–</span>
                                            </div>
                                            <!-- Away goals -->
                                            <div class="flex flex-wrap items-baseline gap-x-1">
                                                <span v-if="match.away_team_flag && match.away_team_flag.length <= 4" class="text-sm">{{ match.away_team_flag }}</span>
                                                <img v-else-if="match.away_team_flag" :src="match.away_team_flag" class="h-3 w-4 object-contain" />
                                                <span class="font-medium text-gray-600 dark:text-gray-300">{{ teamCode(match.away_team) }}:</span>
                                                <template v-if="awayGoals(match).length > 0">
                                                    <span v-for="(goal, i) in awayGoals(match)" :key="i">
                                                        <template v-if="i > 0">, </template>
                                                        {{ goal.player_name }}<template v-if="goal.own_goal"> (og)</template><template v-if="goal.minute"> {{ goal.minute }}'</template>
                                                    </span>
                                                </template>
                                                <span v-else class="text-gray-300 dark:text-gray-600">–</span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile cards -->
                <div v-if="matches.length > 0" class="space-y-3 sm:hidden">
                    <div v-for="match in matches" :key="match.id" class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                        <!-- Card header -->
                        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2 dark:border-gray-700">
                            <span class="text-xs text-gray-400">{{ formatDate(match.kickoff_at) }} · {{ formatTime(match.kickoff_at) }}</span>
                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium" :class="stageBadgeClass[match.stage]">
                                {{ stageLabels[match.stage] }}{{ match.group_name ? ' · Gr. ' + match.group_name : '' }}
                            </span>
                        </div>

                        <!-- Teams + score -->
                        <div class="flex items-center justify-between px-4 py-3">
                            <div class="flex min-w-0 flex-1 flex-col items-center">
                                <span v-if="match.home_team_flag && match.home_team_flag.length <= 4" class="text-2xl">{{ match.home_team_flag }}</span>
                                <img v-else-if="match.home_team_flag" :src="match.home_team_flag" class="h-6 w-9 object-contain" />
                                <span class="mt-1 text-xs font-bold text-gray-800 dark:text-white">{{ teamCode(match.home_team) }}</span>
                            </div>
                            <div class="mx-3 text-center">
                                <div class="text-2xl font-bold tabular-nums text-gray-900 dark:text-white">{{ match.score_home }}:{{ match.score_away }}</div>
                                <div v-if="resultTypeSuffix(match.result_type)" class="text-xs text-gray-400">{{ resultTypeSuffix(match.result_type) }}</div>
                            </div>
                            <div class="flex min-w-0 flex-1 flex-col items-center">
                                <span v-if="match.away_team_flag && match.away_team_flag.length <= 4" class="text-2xl">{{ match.away_team_flag }}</span>
                                <img v-else-if="match.away_team_flag" :src="match.away_team_flag" class="h-6 w-9 object-contain" />
                                <span class="mt-1 text-xs font-bold text-gray-800 dark:text-white">{{ teamCode(match.away_team) }}</span>
                            </div>
                        </div>

                        <!-- My bet + accuracy + goals toggle -->
                        <div class="flex items-center justify-between border-t border-gray-100 px-4 py-2 dark:border-gray-700">
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs text-gray-400">Twój typ:</span>
                                <span
                                    v-if="match.my_bet"
                                    class="text-sm font-bold"
                                    :class="{
                                        'text-green-600 dark:text-green-400': match.my_bet.is_correct === true,
                                        'text-red-500 dark:text-red-400': match.my_bet.is_correct === false,
                                        'text-gray-500 dark:text-gray-400': match.my_bet.is_correct === null,
                                    }"
                                >
                                    {{ match.my_bet.prediction_1x2 }}
                                    <span v-if="match.my_bet.is_correct === true">✓</span>
                                    <span v-else-if="match.my_bet.is_correct === false">✗</span>
                                </span>
                                <span v-else class="text-sm font-bold text-gray-300 dark:text-gray-600">–</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-16">
                                    <div class="h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                        <div class="h-full rounded-full" :class="accuracyBarClass(accuracyPercent(match))" :style="{ width: accuracyPercent(match) + '%' }"></div>
                                    </div>
                                </div>
                                <span class="text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ match.correct_bets }}/{{ match.total_participants }}</span>
                                <button
                                    v-if="match.goals.length > 0"
                                    @click="toggleGoals(match.id)"
                                    class="ml-1 rounded p-0.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 transition-transform duration-150" :class="{ 'rotate-180': expandedGoals[match.id] }" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Goals expanded (mobile) -->
                        <div v-if="expandedGoals[match.id] && match.goals.length > 0" class="border-t border-gray-100 bg-gray-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900/30">
                            <div class="space-y-1 text-xs text-gray-500 dark:text-gray-400">
                                <div v-if="homeGoals(match).length > 0" class="flex flex-wrap items-baseline gap-x-1">
                                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ teamCode(match.home_team) }}:</span>
                                    <span v-for="(goal, i) in homeGoals(match)" :key="i">
                                        <template v-if="i > 0">, </template>
                                        {{ goal.player_name }}<template v-if="goal.own_goal"> (og)</template><template v-if="goal.minute"> {{ goal.minute }}'</template>
                                    </span>
                                </div>
                                <div v-if="awayGoals(match).length > 0" class="flex flex-wrap items-baseline gap-x-1">
                                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ teamCode(match.away_team) }}:</span>
                                    <span v-for="(goal, i) in awayGoals(match)" :key="i">
                                        <template v-if="i > 0">, </template>
                                        {{ goal.player_name }}<template v-if="goal.own_goal"> (og)</template><template v-if="goal.minute"> {{ goal.minute }}'</template>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="matches.length === 0" class="rounded-lg bg-white p-10 text-center text-gray-500 shadow dark:bg-gray-800">
                    <p class="text-sm">Brak meczów pasujących do wybranych filtrów.</p>
                </div>

                <!-- Top scorers -->
                <div v-if="topScorers.length > 0" class="mt-8">
                    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        Top strzelcy turnieju
                    </h2>
                    <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                            <div v-for="(scorer, index) in topScorers" :key="scorer.name" class="flex items-center gap-3 px-4 py-2.5">
                                <span class="w-5 text-center text-sm font-bold tabular-nums" :class="positionColor(index)">
                                    {{ index + 1 }}
                                </span>
                                <span class="flex-1 text-sm text-gray-800 dark:text-gray-200">{{ scorer.name }}</span>
                                <div class="flex items-center gap-2">
                                    <div class="h-1.5 w-24 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                        <div
                                            class="h-full rounded-full bg-indigo-500 dark:bg-indigo-400"
                                            :style="{ width: (scorer.goals / topScorers[0].goals * 100) + '%' }"
                                        ></div>
                                    </div>
                                    <span class="w-6 text-right text-sm font-bold tabular-nums text-gray-900 dark:text-white">{{ scorer.goals }}</span>
                                    <span class="text-xs text-gray-400">{{ scorer.goals === 1 ? 'gol' : scorer.goals < 5 ? 'gole' : 'goli' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
