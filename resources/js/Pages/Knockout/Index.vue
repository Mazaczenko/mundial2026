<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface MyBet {
    prediction_1x2: '1' | 'X' | '2'
    predicted_home: number | null
    predicted_away: number | null
    is_correct: boolean | null
}

interface BetStats {
    '1': number
    'X': number
    '2': number
    total: number
}

interface KnockoutMatch {
    id: number
    home_team: string
    away_team: string
    home_team_flag: string | null
    away_team_flag: string | null
    kickoff_at: string
    stage: string
    status: 'scheduled' | 'in_play' | 'finished'
    score_home: number | null
    score_away: number | null
    score_home_et: number | null
    score_away_et: number | null
    score_home_pen: number | null
    score_away_pen: number | null
    result_type: 'FT' | 'AET' | 'PEN' | null
    my_bet: MyBet | null
    bet_stats: BetStats | null
}

interface Props {
    matchesByStage: Record<string, KnockoutMatch[]>
    participant: { id: number; name: string }
}

const props = defineProps<Props>();

const STAGE_ORDER = ['r32', 'r16', 'qf', 'sf', 'final'] as const;

const STAGE_LABELS: Record<string, string> = {
    r32: '1/32 finału',
    r16: '1/16 finału',
    qf: 'Ćwierćfinał',
    sf: 'Półfinał',
    final: 'Finał',
};

const STAGE_MATCH_COUNTS: Record<string, number> = {
    r32: 16, r16: 8, qf: 4, sf: 2, final: 1,
};

const stages = computed(() => [...STAGE_ORDER]);

const hasMatches = computed(() =>
    STAGE_ORDER.some(s => (props.matchesByStage[s]?.length ?? 0) > 0)
);

const filter = ref<'all' | 'bet' | 'nobet'>('all');

const CARD_HEIGHT = 72;

const maxMatchCount = computed(() => 16);
const columnHeight = computed(() => `${maxMatchCount.value * CARD_HEIGHT}px`);

const now = ref(new Date());
let countdownTimer: ReturnType<typeof setInterval> | null = null;
onMounted(() => { countdownTimer = setInterval(() => { now.value = new Date(); }, 1000); });
onUnmounted(() => { if (countdownTimer) clearInterval(countdownTimer); });

const allKnockoutMatches = computed(() =>
    STAGE_ORDER.flatMap(s => props.matchesByStage[s] ?? [])
);

const nextMatch = computed(() => {
    const scheduled = allKnockoutMatches.value.filter(m => m.status === 'scheduled');
    return scheduled.sort((a, b) => new Date(a.kickoff_at).getTime() - new Date(b.kickoff_at).getTime())[0] ?? null;
});

const myBetStats = computed(() => {
    const all = allKnockoutMatches.value;
    const placed = all.filter(m => m.my_bet !== null).length;
    const total = all.length;
    const finished = all.filter(m => m.status === 'finished');
    const correct = finished.filter(m => m.my_bet?.is_correct === true).length;
    const finishedWithBet = finished.filter(m => m.my_bet !== null).length;
    const hasUnbet = all.some(m => m.status === 'scheduled' && m.my_bet === null);
    return { placed, total, correct, finishedWithBet, hasUnbet };
});

function getPaddedMatches(stage: string): (KnockoutMatch | null)[] {
    const real = props.matchesByStage[stage] ?? [];
    const needed = STAGE_MATCH_COUNTS[stage] ?? 0;
    const padded: (KnockoutMatch | null)[] = [...real];
    while (padded.length < needed) padded.push(null);
    return padded;
}

function stageMatches(stage: string): (KnockoutMatch | null)[] {
    const all = getPaddedMatches(stage);
    if (filter.value === 'bet') return all.filter(m => m !== null && m.my_bet !== null);
    if (filter.value === 'nobet') return all.filter(m => m !== null && m.my_bet === null && m.status === 'scheduled');
    return all;
}

function isWinner(match: KnockoutMatch, side: 'home' | 'away'): boolean {
    if (match.status !== 'finished') return false;
    if (match.result_type === 'PEN') {
        if (match.score_home_pen === null || match.score_away_pen === null) return false;
        return side === 'home' ? match.score_home_pen > match.score_away_pen : match.score_away_pen > match.score_home_pen;
    }
    if (match.score_home === null || match.score_away === null) return false;
    if (match.result_type === 'AET') {
        const homeTotal = match.score_home + (match.score_home_et ?? 0);
        const awayTotal = match.score_away + (match.score_away_et ?? 0);
        return side === 'home' ? homeTotal > awayTotal : awayTotal > homeTotal;
    }
    return side === 'home' ? match.score_home > match.score_away : match.score_away > match.score_home;
}

function displayScore(match: KnockoutMatch, side: 'home' | 'away'): number | null {
    const base = side === 'home' ? match.score_home : match.score_away;
    if (base === null) return null;
    if (match.result_type === 'AET' || match.result_type === 'PEN') {
        const et = side === 'home' ? (match.score_home_et ?? 0) : (match.score_away_et ?? 0);
        return base + et;
    }
    return base;
}

function teamName(name: string): string {
    return name === 'TBD' ? '?' : name;
}

function formatKickoff(kickoffAt: string): string {
    const date = new Date(kickoffAt);
    const d = date.toLocaleDateString('pl-PL', { day: 'numeric', month: 'short' });
    const t = date.toLocaleTimeString('pl-PL', { hour: '2-digit', minute: '2-digit' });
    return `${d}, ${t}`;
}

function formatCountdown(kickoffAt: string): string {
    const diff = new Date(kickoffAt).getTime() - now.value.getTime();
    if (diff <= 0) return 'Zaraz';
    const h = Math.floor(diff / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    if (h > 0) return `za ${h}h ${m}m`;
    if (m > 0) return `za ${m}m ${s}s`;
    return `za ${s}s`;
}

function pointsEarned(match: KnockoutMatch): number | null {
    if (!match.my_bet || match.status !== 'finished' || match.my_bet.is_correct === null) return null;
    if (!match.my_bet.is_correct) return 0;
    const bet = match.my_bet;
    if (
        bet.predicted_home !== null &&
        bet.predicted_away !== null &&
        match.score_home === bet.predicted_home &&
        match.score_away === bet.predicted_away
    ) {
        const implied = bet.predicted_home > bet.predicted_away ? '1' : bet.predicted_home < bet.predicted_away ? '2' : 'X';
        if (implied === bet.prediction_1x2) return 2;
    }
    return 1;
}

function pct(stats: BetStats, key: '1' | 'X' | '2'): number {
    if (!stats.total) return 0;
    return Math.round((stats[key] / stats.total) * 100);
}

function cardCenterY(stageCount: number, index: number, totalHeight: number): number {
    const gap = (totalHeight - stageCount * CARD_HEIGHT) / (stageCount + 1);
    return (index + 1) * gap + index * CARD_HEIGHT + CARD_HEIGHT / 2;
}

function connectorPaths(leftStage: string, rightStage: string): string[] {
    const leftMatches = getPaddedMatches(leftStage);
    const rightMatches = getPaddedMatches(rightStage);
    if (!leftMatches.length || !rightMatches.length) return [];

    const H = maxMatchCount.value * CARD_HEIGHT;
    const paths: string[] = [];

    rightMatches.forEach((_, j) => {
        const leftIdx1 = j * 2;
        const leftIdx2 = j * 2 + 1;
        if (leftIdx2 >= leftMatches.length) return;
        const y1 = cardCenterY(leftMatches.length, leftIdx1, H);
        const y2 = cardCenterY(leftMatches.length, leftIdx2, H);
        const yR = cardCenterY(rightMatches.length, j, H);
        const mx = 24;
        const half = mx / 2;
        paths.push(`M 0 ${y1} H ${half} V ${yR} H ${mx}`);
        paths.push(`M 0 ${y2} H ${half} V ${yR} H ${mx}`);
    });

    return paths;
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Drabinka pucharowa" />

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Drabinka pucharowa</h1>

                    <!-- Filter toggle -->
                    <div v-if="hasMatches" class="flex rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <button
                            v-for="opt in [{ key: 'all', label: 'Wszystkie' }, { key: 'bet', label: 'Obstawione' }, { key: 'nobet', label: 'Bez typu' }]"
                            :key="opt.key"
                            @click="filter = opt.key as any"
                            class="px-3 py-1.5 text-xs font-medium transition-colors first:rounded-l-lg last:rounded-r-lg"
                            :class="filter === opt.key
                                ? 'bg-indigo-600 text-white'
                                : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700'"
                        >
                            {{ opt.label }}
                        </button>
                    </div>
                </div>

                <div v-if="hasMatches" class="mb-4 flex flex-wrap items-center gap-4 rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3 dark:border-indigo-900/40 dark:bg-indigo-900/20">
                    <div class="flex items-center gap-1.5 text-sm">
                        <span class="font-semibold text-indigo-700 dark:text-indigo-300">{{ myBetStats.placed }}/{{ myBetStats.total }}</span>
                        <span class="text-indigo-500 dark:text-indigo-400">obstawionych</span>
                    </div>
                    <div v-if="myBetStats.finishedWithBet > 0" class="flex items-center gap-1.5 text-sm">
                        <span class="font-semibold text-green-700 dark:text-green-400">{{ myBetStats.correct }}/{{ myBetStats.finishedWithBet }}</span>
                        <span class="text-gray-500 dark:text-gray-400">trafnych</span>
                    </div>
                    <a v-if="myBetStats.hasUnbet" href="/bets?tab=upcoming&bet=missing" class="ml-auto text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-200">
                        Obstaw brakujące →
                    </a>
                </div>

                <div v-if="!hasMatches" class="rounded-lg bg-white p-8 text-center text-gray-500 shadow dark:bg-gray-800">
                    Faza pucharowa jeszcze się nie rozpoczęła.
                </div>

                <div v-else class="overflow-x-auto overflow-y-hidden pb-4">
                    <div class="flex items-start" :style="{ minWidth: `${stages.length * 216}px` }">

                        <template v-for="(stage, stageIdx) in stages" :key="stage">
                            <!-- Round column -->
                            <div class="flex w-48 shrink-0 flex-col">
                                <!-- Round label -->
                                <div class="mb-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    {{ STAGE_LABELS[stage] }}
                                </div>

                                <!-- Matches -->
                                <div class="flex flex-col justify-evenly" :style="{ height: columnHeight }">
                                    <div
                                        v-for="(match, matchIdx) in stageMatches(stage)"
                                        :key="match ? match.id : `${stage}-tbd-${matchIdx}`"
                                    >
                                        <!-- TBD placeholder -->
                                        <div
                                            v-if="match === null"
                                            class="overflow-hidden rounded-lg border border-dashed border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/40"
                                        >
                                            <div class="flex h-[calc(72px-1px)] flex-col justify-between p-2">
                                                <div class="flex items-center gap-1.5 text-gray-300 dark:text-gray-600">
                                                    <span class="h-3.5 w-5 shrink-0 rounded bg-gray-200 dark:bg-gray-700" />
                                                    <span class="text-xs">TBD</span>
                                                </div>
                                                <div class="mx-0 border-t border-dashed border-gray-100 dark:border-gray-700/50" />
                                                <div class="flex items-center gap-1.5 text-gray-300 dark:text-gray-600">
                                                    <span class="h-3.5 w-5 shrink-0 rounded bg-gray-200 dark:bg-gray-700" />
                                                    <span class="text-xs">TBD</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Real match card -->
                                        <div
                                            v-else
                                            class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                                        >
                                            <!-- Status bar -->
                                            <div class="flex items-center justify-between px-2 pt-1 text-xs">
                                                <span v-if="match.my_bet" class="font-medium text-indigo-600 dark:text-indigo-400">
                                                    {{ match.my_bet.prediction_1x2 }}
                                                    <span v-if="match.my_bet.predicted_home !== null" class="text-gray-400">
                                                        ({{ match.my_bet.predicted_home }}:{{ match.my_bet.predicted_away }})
                                                    </span>
                                                </span>
                                                <span v-else class="text-gray-300 dark:text-gray-600">–</span>

                                                <span class="flex items-center gap-1">
                                                    <span
                                                        v-if="pointsEarned(match) !== null"
                                                        class="rounded-full px-1.5 py-0.5 text-xs font-bold"
                                                        :class="pointsEarned(match)! > 0 ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400'"
                                                    >
                                                        {{ pointsEarned(match) }}p
                                                    </span>
                                                    <span v-if="match.status === 'in_play'" class="font-bold text-red-500">LIVE</span>
                                                    <span v-else-if="match.status === 'finished'" class="text-gray-400">
                                                        <template v-if="match.result_type === 'PEN' && match.score_home_pen !== null">
                                                            k. {{ match.score_home_pen }}:{{ match.score_away_pen }}
                                                        </template>
                                                        <template v-else>{{ match.result_type ?? 'FT' }}</template>
                                                    </span>
                                                    <span v-else class="text-gray-400">
                                                        <span v-if="nextMatch && match.id === nextMatch.id" class="font-medium text-indigo-500 dark:text-indigo-400">
                                                            {{ formatCountdown(match.kickoff_at) }}
                                                        </span>
                                                        <span v-else>{{ formatKickoff(match.kickoff_at) }}</span>
                                                    </span>
                                                </span>
                                            </div>

                                            <!-- Home team -->
                                            <div
                                                class="flex items-center gap-1.5 px-2 py-1"
                                                :class="[
                                                    isWinner(match, 'home') ? 'bg-green-100 dark:bg-green-900/30 border-l-2 border-green-500' : '',
                                                    match.status === 'finished' && !isWinner(match, 'home') && isWinner(match, 'away') ? 'opacity-50' : '',
                                                ]"
                                            >
                                                <img
                                                    v-if="match.home_team_flag && match.home_team !== 'TBD'"
                                                    :src="match.home_team_flag"
                                                    class="h-3.5 w-5 shrink-0 object-contain"
                                                    :alt="match.home_team"
                                                />
                                                <span v-else class="h-3.5 w-5 shrink-0" />
                                                <span
                                                    class="flex-1 truncate text-xs"
                                                    :class="isWinner(match, 'home') ? 'font-bold text-gray-900 dark:text-white' : 'font-medium text-gray-600 dark:text-gray-400'"
                                                >{{ teamName(match.home_team) }}</span>
                                                <span
                                                    v-if="displayScore(match, 'home') !== null"
                                                    class="text-xs font-bold"
                                                    :class="isWinner(match, 'home') ? 'text-green-700 dark:text-green-400' : 'text-gray-600 dark:text-gray-400'"
                                                >{{ displayScore(match, 'home') }}</span>
                                                <span v-if="isWinner(match, 'home')" class="text-sm font-bold text-green-600 dark:text-green-400">›</span>
                                                <span v-else-if="match.status === 'finished'" class="w-[14px]" />
                                            </div>

                                            <!-- Divider -->
                                            <div class="mx-2 border-t border-gray-100 dark:border-gray-700" />

                                            <!-- Away team -->
                                            <div
                                                class="flex items-center gap-1.5 px-2 py-1"
                                                :class="[
                                                    isWinner(match, 'away') ? 'bg-green-100 dark:bg-green-900/30 border-l-2 border-green-500' : '',
                                                    match.status === 'finished' && !isWinner(match, 'away') && isWinner(match, 'home') ? 'opacity-50' : '',
                                                ]"
                                            >
                                                <img
                                                    v-if="match.away_team_flag && match.away_team !== 'TBD'"
                                                    :src="match.away_team_flag"
                                                    class="h-3.5 w-5 shrink-0 object-contain"
                                                    :alt="match.away_team"
                                                />
                                                <span v-else class="h-3.5 w-5 shrink-0" />
                                                <span
                                                    class="flex-1 truncate text-xs"
                                                    :class="isWinner(match, 'away') ? 'font-bold text-gray-900 dark:text-white' : 'font-medium text-gray-600 dark:text-gray-400'"
                                                >{{ teamName(match.away_team) }}</span>
                                                <span
                                                    v-if="displayScore(match, 'away') !== null"
                                                    class="text-xs font-bold"
                                                    :class="isWinner(match, 'away') ? 'text-green-700 dark:text-green-400' : 'text-gray-600 dark:text-gray-400'"
                                                >{{ displayScore(match, 'away') }}</span>
                                                <span v-if="isWinner(match, 'away')" class="text-sm font-bold text-green-600 dark:text-green-400">›</span>
                                                <span v-else-if="match.status === 'finished'" class="w-[14px]" />
                                            </div>

                                            <!-- Bet stats bar -->
                                            <div v-if="match.bet_stats && match.bet_stats.total > 0" class="flex h-5 overflow-hidden rounded-b">
                                                <div
                                                    v-for="(key, kIdx) in (['1', 'X', '2'] as const)"
                                                    :key="key"
                                                    class="flex items-center justify-center text-[8px] font-bold text-white transition-all"
                                                    :class="[
                                                        kIdx === 0 ? 'bg-blue-500' : kIdx === 1 ? 'bg-gray-400' : 'bg-orange-500',
                                                        match.my_bet?.prediction_1x2 === key ? 'ring-1 ring-inset ring-white/60' : '',
                                                    ]"
                                                    :style="{ width: pct(match.bet_stats, key) + '%' }"
                                                    :title="`${key === '1' ? match.home_team : key === '2' ? match.away_team : 'Remis'}: ${pct(match.bet_stats, key)}% (${match.bet_stats[key]} typy)`"
                                                >
                                                    <span v-if="pct(match.bet_stats, key) >= 15">{{ pct(match.bet_stats, key) }}%</span>
                                                </div>
                                            </div>
                                            <div v-else class="h-5 rounded-b bg-gray-50 dark:bg-gray-700/30" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SVG connector between columns -->
                            <svg
                                v-if="stageIdx < stages.length - 1"
                                class="shrink-0"
                                width="24"
                                :height="columnHeight"
                                style="margin-top: 22px"
                                overflow="visible"
                            >
                                <path
                                    v-for="(path, pi) in connectorPaths(stage, stages[stageIdx + 1])"
                                    :key="pi"
                                    :d="path"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1"
                                    class="text-gray-200 dark:text-gray-700"
                                />
                            </svg>
                        </template>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
