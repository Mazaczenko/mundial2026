<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed, defineComponent, h } from 'vue';

const SortIcon = defineComponent({
    props: { col: String, activeCol: String, dir: String },
    setup(props) {
        return () => {
            const active = props.col === props.activeCol;
            const asc = props.dir === 'asc';
            return h('span', { class: 'ml-0.5 inline-flex flex-col leading-none' }, [
                h('svg', {
                    class: ['w-2.5 h-2.5 -mb-0.5', active && asc ? 'text-indigo-600' : 'text-gray-300 dark:text-gray-600'],
                    viewBox: '0 0 10 6', fill: 'currentColor',
                }, [h('path', { d: 'M5 0L10 6H0z' })]),
                h('svg', {
                    class: ['w-2.5 h-2.5', active && !asc ? 'text-indigo-600' : 'text-gray-300 dark:text-gray-600'],
                    viewBox: '0 0 10 6', fill: 'currentColor',
                }, [h('path', { d: 'M5 6L0 0h10z' })]),
            ]);
        };
    },
});
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
} from 'chart.js';
import type { RankingEntry } from '@/types';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend);

interface ChartDataset {
    label: string;
    data: (number | null)[];
}

interface BettingStatEntry {
    id: number;
    name: string;
    eliminated: boolean;
    total_finished: number;
    bets_placed: number;
    correct_1x2: number;
    missed: number;
    accuracy_pct: number | null;
    exact_scores: number;
    current_streak: number;
    best_streak: number;
    group_bets: number;
    group_correct: number;
    knockout_bets: number;
    knockout_correct: number;
    fav_prediction: '1' | 'X' | '2' | null;
}

interface Props {
    ranking: RankingEntry[];
    chartData: {
        labels: string[];
        datasets: ChartDataset[];
    } | [];
    playedMatchesCount: number;
    bettingStats: BettingStatEntry[];
}

const props = defineProps<Props>();

const COLORS = [
    '#6366f1','#f59e0b','#10b981','#ef4444','#3b82f6',
    '#8b5cf6','#ec4899','#14b8a6','#f97316','#84cc16',
    '#06b6d4','#a855f7','#fb923c','#22c55e','#e11d48',
];

const hasChartData = computed(() =>
    !Array.isArray(props.chartData) && (props.chartData as any).labels?.length > 0
);

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index' as const, intersect: false },
    plugins: {
        legend: {
            position: 'bottom' as const,
            labels: { boxWidth: 12, padding: 12, font: { size: 11 } },
        },
        tooltip: {
            callbacks: {
                title: (items: any[]) => items[0]?.label ?? '',
                label: (item: any) => ` ${item.dataset.label}: ${item.raw} pkt`,
            },
        },
    },
    scales: {
        x: {
            ticks: { font: { size: 10 }, maxRotation: 45 },
            grid: { display: false },
        },
        y: {
            beginAtZero: true,
            ticks: { stepSize: 1, font: { size: 11 } },
            title: { display: true, text: 'Punkty', font: { size: 11 } },
        },
    },
};

const currentUserName = (usePage().props as any).auth?.user?.name as string | undefined;

const chartDataset = computed(() => {
    if (!hasChartData.value) return { labels: [], datasets: [] };
    const d = props.chartData as { labels: string[]; datasets: ChartDataset[] };
    return {
        labels: d.labels,
        datasets: d.datasets.map((ds, i) => ({
            label: ds.label,
            data: ds.data,
            borderColor: COLORS[i % COLORS.length],
            backgroundColor: COLORS[i % COLORS.length] + '22',
            borderWidth: currentUserName && ds.label === currentUserName ? 3 : 2,
            pointRadius: currentUserName && ds.label === currentUserName ? 5 : 4,
            pointHoverRadius: 6,
            tension: 0.3,
            spanGaps: true,
            hidden: currentUserName ? ds.label !== currentUserName : false,
        })),
    };
});

const showChart = ref(false);

type SortCol = 'rank' | 'name' | 'points' | 'bets_count' | 'missed_count';
type SortDir = 'asc' | 'desc';
type StatSortCol = 'name' | 'bets_placed' | 'accuracy_pct' | 'exact_scores' | 'current_streak' | 'best_streak' | 'group' | 'knockout';

const PER_PAGE = 25;
const search = ref('');
const sortCol = ref<SortCol>('rank');
const sortDir = ref<SortDir>('asc');
const page = ref(1);

const statSortCol = ref<StatSortCol>('accuracy_pct');
const statSortDir = ref<SortDir>('desc');
const STAT_PER_PAGE = 25;
const statSearch = ref('');
const statPage = ref(1);

const active = computed(() => props.ranking.filter((p) => !p.eliminated));
const eliminated = computed(() => props.ranking.filter((p) => p.eliminated));

const filteredActive = computed(() => {
    let list = active.value;

    if (search.value.trim()) {
        const q = search.value.toLowerCase();
        list = list.filter((p) => p.name.toLowerCase().includes(q));
    }

    if (sortCol.value !== 'rank') {
        list = [...list].sort((a, b) => {
            let cmp = 0;
            if (sortCol.value === 'name') {
                cmp = a.name.localeCompare(b.name, 'pl');
            } else if (sortCol.value === 'points') {
                cmp = a.points - b.points;
            } else if (sortCol.value === 'bets_count') {
                cmp = a.bets_count - b.bets_count;
            } else if (sortCol.value === 'missed_count') {
                cmp = a.missed_count - b.missed_count;
            }
            return sortDir.value === 'asc' ? cmp : -cmp;
        });
    } else if (sortDir.value === 'desc') {
        list = [...list].reverse();
    }

    return list;
});

const filteredEliminated = computed(() => {
    if (!search.value.trim()) return eliminated.value;
    const q = search.value.toLowerCase();
    return eliminated.value.filter((p) => p.name.toLowerCase().includes(q));
});

const totalPages = computed(() => Math.ceil(filteredActive.value.length / PER_PAGE));

const paginated = computed(() => {
    const start = (page.value - 1) * PER_PAGE;
    return filteredActive.value.slice(start, start + PER_PAGE);
});

function rankOf(entry: RankingEntry): number {
    return active.value.indexOf(entry) + 1;
}

function setSearch(val: string) {
    search.value = val;
    page.value = 1;
}

const defaultDir: Record<SortCol, SortDir> = {
    rank: 'asc', name: 'asc', points: 'desc', bets_count: 'desc', missed_count: 'asc',
};

function toggleSort(col: SortCol) {
    if (sortCol.value === col) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortCol.value = col;
        sortDir.value = defaultDir[col];
    }
    page.value = 1;
}

const statDefaultDir: Record<StatSortCol, SortDir> = {
    name: 'asc',
    bets_placed: 'desc',
    accuracy_pct: 'desc',
    exact_scores: 'desc',
    current_streak: 'desc',
    best_streak: 'desc',
    group: 'desc',
    knockout: 'desc',
};

function toggleStatSort(col: StatSortCol) {
    if (statSortCol.value === col) {
        statSortDir.value = statSortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        statSortCol.value = col;
        statSortDir.value = statDefaultDir[col];
    }
    statPage.value = 1;
}

function setStatSearch(val: string) {
    statSearch.value = val;
    statPage.value = 1;
}

function compareStatRow(a: BettingStatEntry, b: BettingStatEntry, col: StatSortCol): number {
    switch (col) {
        case 'name':        return a.name.localeCompare(b.name, 'pl');
        case 'bets_placed': return a.bets_placed - b.bets_placed;
        case 'accuracy_pct':
            return (a.accuracy_pct ?? -1) - (b.accuracy_pct ?? -1);
        case 'exact_scores':    return a.exact_scores - b.exact_scores;
        case 'current_streak':  return a.current_streak - b.current_streak;
        case 'best_streak':     return a.best_streak - b.best_streak;
        case 'group':
            return (a.group_bets > 0 ? a.group_correct / a.group_bets : -1) -
                   (b.group_bets > 0 ? b.group_correct / b.group_bets : -1);
        case 'knockout':
            return (a.knockout_bets > 0 ? a.knockout_correct / a.knockout_bets : -1) -
                   (b.knockout_bets > 0 ? b.knockout_correct / b.knockout_bets : -1);
        default: return 0;
    }
}

const sortedActiveStats = computed(() => {
    const active = props.bettingStats.filter((p) => !p.eliminated);
    return [...active].sort((a, b) => {
        const cmp = compareStatRow(a, b, statSortCol.value);
        return statSortDir.value === 'asc' ? cmp : -cmp;
    });
});

const sortedEliminatedStats = computed(() => {
    const eliminated = props.bettingStats.filter((p) => p.eliminated);
    return [...eliminated].sort((a, b) => {
        const cmp = compareStatRow(a, b, statSortCol.value);
        return statSortDir.value === 'asc' ? cmp : -cmp;
    });
});

const filteredSortedActiveStats = computed(() => {
    if (!statSearch.value.trim()) return sortedActiveStats.value;
    const q = statSearch.value.toLowerCase();
    return sortedActiveStats.value.filter((r) => r.name.toLowerCase().includes(q));
});

const filteredSortedEliminatedStats = computed(() => {
    if (!statSearch.value.trim()) return sortedEliminatedStats.value;
    const q = statSearch.value.toLowerCase();
    return sortedEliminatedStats.value.filter((r) => r.name.toLowerCase().includes(q));
});

const statTotalPages = computed(() => Math.ceil(filteredSortedActiveStats.value.length / STAT_PER_PAGE));

const paginatedActiveStats = computed(() => {
    const start = (statPage.value - 1) * STAT_PER_PAGE;
    return filteredSortedActiveStats.value.slice(start, start + STAT_PER_PAGE);
});

function accuracyClass(pct: number | null): string {
    if (pct === null) return 'text-gray-400';
    if (pct >= 70)    return 'text-green-600 dark:text-green-400';
    if (pct >= 40)    return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-500 dark:text-red-400';
}

function accuracyBarClass(pct: number | null): string {
    if (pct === null) return 'bg-gray-200 dark:bg-gray-600';
    if (pct >= 70)    return 'bg-green-500';
    if (pct >= 40)    return 'bg-yellow-500';
    return 'bg-red-500';
}

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

const globalStats = computed(() => {
    const actStats = props.bettingStats.filter(s => !s.eliminated);
    const withAccuracy = actStats.filter(s => s.accuracy_pct !== null);
    return {
        totalBets: actStats.reduce((s, r) => s + r.bets_placed, 0),
        avgAccuracy: withAccuracy.length
            ? Math.round(withAccuracy.reduce((s, r) => s + (r.accuracy_pct ?? 0), 0) / withAccuracy.length * 10) / 10
            : null,
        totalExact: actStats.reduce((s, r) => s + r.exact_scores, 0),
        totalCorrect: actStats.reduce((s, r) => s + r.correct_1x2, 0),
    };
});

const hasKnockoutData = computed(() =>
    props.bettingStats.some(s => s.knockout_bets > 0)
);

const knockoutRanking = computed(() => {
    return [...props.bettingStats]
        .filter(s => !s.eliminated)
        .map(s => ({
            ...s,
            knockout_points: s.knockout_correct + s.exact_scores,
        }))
        .sort((a, b) => b.knockout_points - a.knockout_points || b.knockout_correct - a.knockout_correct);
});

</script>

<template>
    <AuthenticatedLayout>
        <Head title="Ranking" />

        <div class="py-6">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">

                <!-- Chart -->
                <div v-if="hasChartData" class="mb-6 overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                    <button
                        @click="showChart = !showChart"
                        class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-medium text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700/50"
                    >
                        <span>Wykres punktów w czasie</span>
                        <svg class="h-4 w-4 transition-transform" :class="showChart ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div v-show="showChart" class="border-t border-gray-100 px-4 pb-4 pt-3 dark:border-gray-700">
                        <div class="relative h-72">
                            <Line :data="chartDataset" :options="chartOptions" />
                        </div>
                    </div>
                </div>

                <!-- Podium TOP 3 -->
                <div v-if="active.length >= 3" class="mb-6">
                    <div class="flex items-end justify-center gap-3">
                        <!-- 2. miejsce -->
                        <div class="mt-6 flex w-1/3 flex-col items-center">
                            <div class="w-full rounded-xl border-2 border-gray-300 bg-white p-4 text-center shadow dark:bg-gray-800">
                                <div class="mb-1 text-3xl">🥈</div>
                                <Link
                                    :href="route('participants.show', active[1].id)"
                                    class="block truncate text-sm font-semibold text-gray-800 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400"
                                >{{ active[1].name }}</Link>
                                <div class="mt-1 text-lg font-bold text-gray-700 dark:text-gray-200">{{ active[1].points }} pkt</div>
                                <div class="mt-1 flex items-center justify-center gap-1 text-xs">
                                    <span v-if="active[1].position_change !== null && active[1].position_change > 0" class="text-green-600 dark:text-green-400">▲{{ active[1].position_change }}</span>
                                    <span v-else-if="active[1].position_change !== null && active[1].position_change < 0" class="text-red-500 dark:text-red-400">▼{{ Math.abs(active[1].position_change) }}</span>
                                    <span v-else-if="active[1].position_change === 0" class="text-gray-400 dark:text-gray-600">—</span>
                                </div>
                            </div>
                            <div class="mt-2 h-8 w-full rounded-b-sm bg-gray-300 dark:bg-gray-600"></div>
                        </div>

                        <!-- 1. miejsce -->
                        <div class="-mt-6 flex w-1/3 flex-col items-center">
                            <div class="w-full rounded-xl border-2 border-yellow-400 bg-yellow-50 p-4 text-center shadow-lg dark:bg-yellow-900/20">
                                <div class="mb-1 text-3xl">🥇</div>
                                <Link
                                    :href="route('participants.show', active[0].id)"
                                    class="block truncate text-sm font-semibold text-gray-800 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400"
                                >{{ active[0].name }}</Link>
                                <div class="mt-1 text-lg font-bold text-yellow-700 dark:text-yellow-300">{{ active[0].points }} pkt</div>
                                <div class="mt-1 flex items-center justify-center gap-1 text-xs">
                                    <span v-if="active[0].position_change !== null && active[0].position_change > 0" class="text-green-600 dark:text-green-400">▲{{ active[0].position_change }}</span>
                                    <span v-else-if="active[0].position_change !== null && active[0].position_change < 0" class="text-red-500 dark:text-red-400">▼{{ Math.abs(active[0].position_change) }}</span>
                                    <span v-else-if="active[0].position_change === 0" class="text-gray-400 dark:text-gray-600">—</span>
                                </div>
                            </div>
                            <div class="mt-2 h-12 w-full rounded-b-sm bg-yellow-400 dark:bg-yellow-500"></div>
                        </div>

                        <!-- 3. miejsce -->
                        <div class="mt-6 flex w-1/3 flex-col items-center">
                            <div class="w-full rounded-xl border-2 border-amber-600/40 bg-white p-4 text-center shadow dark:bg-gray-800">
                                <div class="mb-1 text-3xl">🥉</div>
                                <Link
                                    :href="route('participants.show', active[2].id)"
                                    class="block truncate text-sm font-semibold text-gray-800 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400"
                                >{{ active[2].name }}</Link>
                                <div class="mt-1 text-lg font-bold text-amber-700 dark:text-amber-400">{{ active[2].points }} pkt</div>
                                <div class="mt-1 flex items-center justify-center gap-1 text-xs">
                                    <span v-if="active[2].position_change !== null && active[2].position_change > 0" class="text-green-600 dark:text-green-400">▲{{ active[2].position_change }}</span>
                                    <span v-else-if="active[2].position_change !== null && active[2].position_change < 0" class="text-red-500 dark:text-red-400">▼{{ Math.abs(active[2].position_change) }}</span>
                                    <span v-else-if="active[2].position_change === 0" class="text-gray-400 dark:text-gray-600">—</span>
                                </div>
                            </div>
                            <div class="mt-2 h-6 w-full rounded-b-sm bg-amber-600/60 dark:bg-amber-700"></div>
                        </div>
                    </div>
                </div>

                <!-- Global stats -->
                <div v-if="bettingStats.length > 0" class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Łączne typy</div>
                        <div class="mt-1 text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ globalStats.totalBets }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Śr. trafność</div>
                        <div
                            class="mt-1 text-2xl font-bold"
                            :class="globalStats.avgAccuracy === null ? 'text-gray-400' : globalStats.avgAccuracy >= 50 ? 'text-green-600 dark:text-green-400' : 'text-orange-500 dark:text-orange-400'"
                        >{{ globalStats.avgAccuracy !== null ? globalStats.avgAccuracy + '%' : '—' }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Trafione</div>
                        <div class="mt-1 text-2xl font-bold text-gray-700 dark:text-gray-200">{{ globalStats.totalCorrect }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Dokładne wyniki</div>
                        <div class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ globalStats.totalExact }}</div>
                    </div>
                </div>

                <!-- Header + controls -->
                <div class="mb-4 flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Ranking</h1>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-100 px-3 py-1 text-sm font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Rozegrano {{ playedMatchesCount }} meczy
                    </span>

                    <div class="flex flex-1 items-center sm:ml-4">
                        <!-- Search -->
                        <div class="relative min-w-[160px] flex-1">
                            <svg class="pointer-events-none absolute inset-y-0 left-2.5 my-auto h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                            </svg>
                            <input
                                :value="search"
                                @input="setSearch(($event.target as HTMLInputElement).value)"
                                type="search"
                                placeholder="Szukaj uczestnika…"
                                class="w-full rounded-lg border border-gray-300 bg-white py-1.5 pl-8 pr-3 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            />
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    <button @click="toggleSort('rank')" class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-300">
                                        #<SortIcon col="rank" :active-col="sortCol" :dir="sortDir" />
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    <button @click="toggleSort('name')" class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-300">
                                        Uczestnik<SortIcon col="name" :active-col="sortCol" :dir="sortDir" />
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500">
                                    <button @click="toggleSort('points')" class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-300">
                                        Pkt<SortIcon col="points" :active-col="sortCol" :dir="sortDir" />
                                    </button>
                                </th>
                                <th class="hidden px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500 sm:table-cell">
                                    <button @click="toggleSort('bets_count')" class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-300">
                                        Obst.<SortIcon col="bets_count" :active-col="sortCol" :dir="sortDir" />
                                    </button>
                                </th>
                                <th class="hidden px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500 sm:table-cell">
                                    <button @click="toggleSort('missed_count')" class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-300">
                                        Pominięte<SortIcon col="missed_count" :active-col="sortCol" :dir="sortDir" />
                                    </button>
                                </th>
                                <th class="hidden px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 lg:table-cell">
                                    Król strzelców
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr
                                v-for="entry in paginated"
                                :key="entry.id"
                                :class="entry.name === currentUserName
                                    ? 'bg-indigo-50 dark:bg-indigo-900/10 hover:bg-indigo-100 dark:hover:bg-indigo-900/20'
                                    : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'"
                            >
                                <td class="px-4 py-3 text-sm font-medium text-gray-500">
                                    <div class="flex items-center gap-1">
                                        <span>{{ rankOf(entry) }}</span>
                                        <span
                                            v-if="entry.position_change !== null && entry.position_change > 0"
                                            class="text-xs text-green-600 dark:text-green-400"
                                            :title="`Awans o ${entry.position_change} miejsc`"
                                        >▲{{ entry.position_change }}</span>
                                        <span
                                            v-else-if="entry.position_change !== null && entry.position_change < 0"
                                            class="text-xs text-red-500 dark:text-red-400"
                                            :title="`Spadek o ${Math.abs(entry.position_change)} miejsc`"
                                        >▼{{ Math.abs(entry.position_change) }}</span>
                                        <span
                                            v-else-if="entry.position_change === 0"
                                            class="text-xs text-gray-400 dark:text-gray-600"
                                            title="Bez zmian"
                                        >—</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="route('participants.show', entry.id)"
                                            class="font-medium text-gray-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400"
                                        >{{ entry.name }}</Link>
                                        <span v-if="entry.paid_entry" title="Wpłacił 10 zł">💰</span>
                                        <span
                                            v-for="badge in entry.badges"
                                            :key="badge.key"
                                            :title="badge.label"
                                            class="cursor-default text-sm leading-none"
                                        >{{ badgeEmoji(badge.key) }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-sm font-semibold text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                        {{ entry.points }}
                                    </span>
                                </td>
                                <td class="hidden px-4 py-3 text-center text-sm text-gray-500 dark:text-gray-400 sm:table-cell">
                                    {{ entry.bets_count }}
                                </td>
                                <td class="hidden px-4 py-3 text-center sm:table-cell">
                                    <span
                                        class="text-sm font-medium"
                                        :class="entry.missed_count >= 2 ? 'text-red-500' : 'text-gray-500 dark:text-gray-400'"
                                    >
                                        {{ entry.missed_count }}
                                    </span>
                                </td>
                                <td class="hidden px-4 py-3 lg:table-cell">
                                    <span v-if="entry.top_scorer" class="inline-flex items-center gap-1 text-sm text-gray-700 dark:text-gray-300">
                                        <svg v-if="entry.scorer_correct" class="h-3.5 w-3.5 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                        {{ entry.top_scorer }}
                                    </span>
                                    <span v-else class="text-sm text-gray-300 dark:text-gray-600">—</span>
                                </td>
                            </tr>

                            <tr v-if="paginated.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">
                                    Brak wyników.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="totalPages > 1" class="mt-4 flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
                    <span>{{ filteredActive.length }} uczestników · strona {{ page }}/{{ totalPages }}</span>
                    <div class="flex gap-1">
                        <button
                            @click="page--"
                            :disabled="page === 1"
                            class="rounded px-3 py-1.5 font-medium transition-colors disabled:opacity-40"
                            :class="page > 1 ? 'hover:bg-gray-100 dark:hover:bg-gray-700' : ''"
                        >← Poprzednia</button>
                        <button
                            @click="page++"
                            :disabled="page === totalPages"
                            class="rounded px-3 py-1.5 font-medium transition-colors disabled:opacity-40"
                            :class="page < totalPages ? 'hover:bg-gray-100 dark:hover:bg-gray-700' : ''"
                        >Następna →</button>
                    </div>
                </div>

                <!-- Betting Stats Section -->
                <div v-if="bettingStats.length > 0" class="mt-10">
                    <div class="mb-4 flex flex-wrap items-center gap-3">
                        <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100">Statystyki typowania</h2>
                        <div class="flex flex-1 items-center sm:ml-4">
                            <div class="relative min-w-[160px] flex-1">
                                <svg class="pointer-events-none absolute inset-y-0 left-2.5 my-auto h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                                </svg>
                                <input
                                    :value="statSearch"
                                    @input="setStatSearch(($event.target as HTMLInputElement).value)"
                                    type="search"
                                    placeholder="Szukaj uczestnika…"
                                    class="w-full rounded-lg border border-gray-300 bg-white py-1.5 pl-8 pr-3 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Desktop table -->
                    <div class="hidden overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800 sm:block">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                            <button @click="toggleStatSort('name')" class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-300">
                                                Uczestnik<SortIcon col="name" :active-col="statSortCol" :dir="statSortDir" />
                                            </button>
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500">
                                            <button @click="toggleStatSort('bets_placed')" class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-300">
                                                Typy<SortIcon col="bets_placed" :active-col="statSortCol" :dir="statSortDir" />
                                            </button>
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500">
                                            <button @click="toggleStatSort('accuracy_pct')" class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-300">
                                                Trafność<SortIcon col="accuracy_pct" :active-col="statSortCol" :dir="statSortDir" />
                                            </button>
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500">
                                            <button @click="toggleStatSort('exact_scores')" class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-300">
                                                Dokładne<SortIcon col="exact_scores" :active-col="statSortCol" :dir="statSortDir" />
                                            </button>
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500">
                                            <button @click="toggleStatSort('current_streak')" class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-300">
                                                Seria<SortIcon col="current_streak" :active-col="statSortCol" :dir="statSortDir" />
                                            </button>
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500">
                                            <button @click="toggleStatSort('best_streak')" class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-300">
                                                Najlepsza<SortIcon col="best_streak" :active-col="statSortCol" :dir="statSortDir" />
                                            </button>
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500">
                                            <button @click="toggleStatSort('group')" class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-300">
                                                Grupy<SortIcon col="group" :active-col="statSortCol" :dir="statSortDir" />
                                            </button>
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500">
                                            <button @click="toggleStatSort('knockout')" class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-300">
                                                Puchar<SortIcon col="knockout" :active-col="statSortCol" :dir="statSortDir" />
                                            </button>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr
                                        v-for="row in paginatedActiveStats"
                                        :key="row.id"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                    >
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                            {{ row.name }}
                                        </td>
                                        <td class="px-4 py-3 text-center text-sm text-gray-600 dark:text-gray-300">
                                            {{ row.bets_placed }}/{{ row.total_finished }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <div class="h-1.5 w-16 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                                    <div
                                                        class="h-full rounded-full transition-all"
                                                        :class="accuracyBarClass(row.accuracy_pct)"
                                                        :style="{ width: (row.accuracy_pct ?? 0) + '%' }"
                                                    />
                                                </div>
                                                <span class="min-w-[2.5rem] text-right text-sm font-semibold" :class="accuracyClass(row.accuracy_pct)">
                                                    {{ row.accuracy_pct !== null ? row.accuracy_pct + '%' : '—' }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center text-sm text-gray-600 dark:text-gray-300">
                                            <span v-if="row.exact_scores > 0" class="inline-flex items-center gap-1">
                                                <span>⚽</span>
                                                <span class="font-medium">{{ row.exact_scores }}</span>
                                            </span>
                                            <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                                        </td>
                                        <td class="px-4 py-3 text-center text-sm text-gray-600 dark:text-gray-300">
                                            <span v-if="row.current_streak > 0" class="inline-flex items-center gap-1 font-medium text-orange-500">
                                                <span>🔥</span>{{ row.current_streak }}
                                            </span>
                                            <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                                        </td>
                                        <td class="px-4 py-3 text-center text-sm font-medium text-gray-600 dark:text-gray-300">
                                            {{ row.best_streak }}
                                        </td>
                                        <td class="px-4 py-3 text-center text-sm text-gray-600 dark:text-gray-300">
                                            {{ row.group_correct }}/{{ row.group_bets }}
                                        </td>
                                        <td class="px-4 py-3 text-center text-sm text-gray-600 dark:text-gray-300">
                                            <span v-if="row.knockout_bets > 0">{{ row.knockout_correct }}/{{ row.knockout_bets }}</span>
                                            <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                                        </td>
                                    </tr>

                                    <!-- Eliminated separator -->
                                    <template v-if="filteredSortedEliminatedStats.length > 0">
                                        <tr>
                                            <td colspan="8" class="bg-gray-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:bg-gray-900/50 dark:text-gray-500">
                                                Wyeliminowani
                                            </td>
                                        </tr>
                                        <tr
                                            v-for="row in filteredSortedEliminatedStats"
                                            :key="row.id"
                                            class="opacity-60"
                                        >
                                            <td class="px-4 py-3 text-sm font-medium text-gray-500 line-through dark:text-gray-400">
                                                {{ row.name }}
                                            </td>
                                            <td class="px-4 py-3 text-center text-sm text-gray-400">
                                                {{ row.bets_placed }}/{{ row.total_finished }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-2">
                                                    <div class="h-1.5 w-16 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                                        <div
                                                            class="h-full rounded-full"
                                                            :class="accuracyBarClass(row.accuracy_pct)"
                                                            :style="{ width: (row.accuracy_pct ?? 0) + '%' }"
                                                        />
                                                    </div>
                                                    <span class="min-w-[2.5rem] text-right text-sm font-semibold" :class="accuracyClass(row.accuracy_pct)">
                                                        {{ row.accuracy_pct !== null ? row.accuracy_pct + '%' : '—' }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-center text-sm text-gray-400">
                                                <span v-if="row.exact_scores > 0" class="inline-flex items-center gap-1">⚽ {{ row.exact_scores }}</span>
                                                <span v-else>—</span>
                                            </td>
                                            <td class="px-4 py-3 text-center text-sm text-gray-400">
                                                <span v-if="row.current_streak > 0">🔥 {{ row.current_streak }}</span>
                                                <span v-else>—</span>
                                            </td>
                                            <td class="px-4 py-3 text-center text-sm text-gray-400">{{ row.best_streak }}</td>
                                            <td class="px-4 py-3 text-center text-sm text-gray-400">{{ row.group_correct }}/{{ row.group_bets }}</td>
                                            <td class="px-4 py-3 text-center text-sm text-gray-400">
                                                <span v-if="row.knockout_bets > 0">{{ row.knockout_correct }}/{{ row.knockout_bets }}</span>
                                                <span v-else>—</span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Stats pagination -->
                    <div v-if="statTotalPages > 1" class="mt-4 hidden items-center justify-between text-sm text-gray-500 dark:text-gray-400 sm:flex">
                        <span>{{ filteredSortedActiveStats.length }} uczestników · strona {{ statPage }}/{{ statTotalPages }}</span>
                        <div class="flex gap-1">
                            <button
                                @click="statPage--"
                                :disabled="statPage === 1"
                                class="rounded px-3 py-1.5 font-medium transition-colors disabled:opacity-40"
                                :class="statPage > 1 ? 'hover:bg-gray-100 dark:hover:bg-gray-700' : ''"
                            >← Poprzednia</button>
                            <button
                                @click="statPage++"
                                :disabled="statPage === statTotalPages"
                                class="rounded px-3 py-1.5 font-medium transition-colors disabled:opacity-40"
                                :class="statPage < statTotalPages ? 'hover:bg-gray-100 dark:hover:bg-gray-700' : ''"
                            >Następna →</button>
                        </div>
                    </div>

                    <!-- Mobile cards -->
                    <div class="space-y-3 sm:hidden">
                        <template v-for="row in [...paginatedActiveStats, ...filteredSortedEliminatedStats]" :key="row.id">
                            <div
                                class="rounded-lg bg-white p-4 shadow dark:bg-gray-800"
                                :class="{ 'opacity-60': row.eliminated }"
                            >
                                <div class="mb-3 flex items-start justify-between">
                                    <span
                                        class="text-base font-semibold text-gray-900 dark:text-white"
                                        :class="{ 'line-through text-gray-500 dark:text-gray-400': row.eliminated }"
                                    >
                                        {{ row.name }}
                                    </span>
                                    <span class="text-2xl font-bold" :class="accuracyClass(row.accuracy_pct)">
                                        {{ row.accuracy_pct !== null ? row.accuracy_pct + '%' : '—' }}
                                    </span>
                                </div>
                                <div class="mb-3 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                    <div
                                        class="h-full rounded-full transition-all"
                                        :class="accuracyBarClass(row.accuracy_pct)"
                                        :style="{ width: (row.accuracy_pct ?? 0) + '%' }"
                                    />
                                </div>
                                <div class="grid grid-cols-3 gap-2 text-center text-xs">
                                    <div class="rounded bg-gray-50 px-2 py-1.5 dark:bg-gray-700/50">
                                        <div class="font-semibold text-gray-700 dark:text-gray-200">{{ row.bets_placed }}/{{ row.total_finished }}</div>
                                        <div class="text-gray-400">Typy</div>
                                    </div>
                                    <div class="rounded bg-gray-50 px-2 py-1.5 dark:bg-gray-700/50">
                                        <div class="font-semibold text-gray-700 dark:text-gray-200">
                                            <span v-if="row.exact_scores > 0">⚽ {{ row.exact_scores }}</span>
                                            <span v-else class="text-gray-300">—</span>
                                        </div>
                                        <div class="text-gray-400">Dokładne</div>
                                    </div>
                                    <div class="rounded bg-gray-50 px-2 py-1.5 dark:bg-gray-700/50">
                                        <div class="font-semibold text-orange-500">
                                            <span v-if="row.current_streak > 0">🔥 {{ row.current_streak }}</span>
                                            <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                                        </div>
                                        <div class="text-gray-400">Seria</div>
                                    </div>
                                    <div class="rounded bg-gray-50 px-2 py-1.5 dark:bg-gray-700/50">
                                        <div class="font-semibold text-gray-700 dark:text-gray-200">{{ row.best_streak }}</div>
                                        <div class="text-gray-400">Najlepsza</div>
                                    </div>
                                    <div class="rounded bg-gray-50 px-2 py-1.5 dark:bg-gray-700/50">
                                        <div class="font-semibold text-gray-700 dark:text-gray-200">{{ row.group_correct }}/{{ row.group_bets }}</div>
                                        <div class="text-gray-400">Grupy</div>
                                    </div>
                                    <div class="rounded bg-gray-50 px-2 py-1.5 dark:bg-gray-700/50">
                                        <div class="font-semibold text-gray-700 dark:text-gray-200">
                                            <span v-if="row.knockout_bets > 0">{{ row.knockout_correct }}/{{ row.knockout_bets }}</span>
                                            <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                                        </div>
                                        <div class="text-gray-400">Puchar</div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Stats pagination mobile -->
                    <div v-if="statTotalPages > 1" class="mt-4 flex items-center justify-between text-sm text-gray-500 dark:text-gray-400 sm:hidden">
                        <span>{{ filteredSortedActiveStats.length }} uczestników · strona {{ statPage }}/{{ statTotalPages }}</span>
                        <div class="flex gap-1">
                            <button
                                @click="statPage--"
                                :disabled="statPage === 1"
                                class="rounded px-3 py-1.5 font-medium transition-colors disabled:opacity-40"
                                :class="statPage > 1 ? 'hover:bg-gray-100 dark:hover:bg-gray-700' : ''"
                            >← Poprzednia</button>
                            <button
                                @click="statPage++"
                                :disabled="statPage === statTotalPages"
                                class="rounded px-3 py-1.5 font-medium transition-colors disabled:opacity-40"
                                :class="statPage < statTotalPages ? 'hover:bg-gray-100 dark:hover:bg-gray-700' : ''"
                            >Następna →</button>
                        </div>
                    </div>
                </div>

                <!-- Knockout Ranking -->
                <div v-if="hasKnockoutData" class="mt-10">
                    <h2 class="mb-4 text-xl font-bold text-gray-800 dark:text-gray-100">Ranking fazy pucharowej</h2>

                    <!-- Desktop table -->
                    <div class="hidden overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800 sm:block">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Uczestnik</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500">Pkt puchar</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500">Trafione (1x2)</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500">Dokładne wyniki</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr
                                    v-for="row in knockoutRanking"
                                    :key="row.id"
                                    :class="row.name === currentUserName
                                        ? 'bg-indigo-50 dark:bg-indigo-900/10 hover:bg-indigo-100 dark:hover:bg-indigo-900/20'
                                        : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'"
                                >
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ row.name }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-sm font-semibold text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300">
                                            {{ row.knockout_points }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm text-gray-600 dark:text-gray-300">
                                        {{ row.knockout_correct }}/{{ row.knockout_bets }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm text-gray-600 dark:text-gray-300">
                                        <span v-if="row.exact_scores > 0" class="inline-flex items-center gap-1">
                                            <span>⚽</span>
                                            <span class="font-medium">{{ row.exact_scores }}</span>
                                        </span>
                                        <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile cards -->
                    <div class="space-y-3 sm:hidden">
                        <div
                            v-for="(row, index) in knockoutRanking"
                            :key="row.id"
                            class="rounded-lg bg-white p-4 shadow dark:bg-gray-800"
                            :class="row.name === currentUserName ? 'ring-2 ring-indigo-400 dark:ring-indigo-500' : ''"
                        >
                            <div class="mb-2 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-gray-400 dark:text-gray-500">{{ index + 1 }}.</span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ row.name }}</span>
                                </div>
                                <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-sm font-bold text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300">
                                    {{ row.knockout_points }} pkt
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-center text-xs">
                                <div class="rounded bg-gray-50 px-2 py-1.5 dark:bg-gray-700/50">
                                    <div class="font-semibold text-gray-700 dark:text-gray-200">{{ row.knockout_correct }}/{{ row.knockout_bets }}</div>
                                    <div class="text-gray-400">Trafione (1x2)</div>
                                </div>
                                <div class="rounded bg-gray-50 px-2 py-1.5 dark:bg-gray-700/50">
                                    <div class="font-semibold text-gray-700 dark:text-gray-200">
                                        <span v-if="row.exact_scores > 0">⚽ {{ row.exact_scores }}</span>
                                        <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                                    </div>
                                    <div class="text-gray-400">Dokładne</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Eliminated -->
                <div v-if="filteredEliminated.length > 0" class="mt-8">
                    <h2 class="mb-3 text-lg font-semibold text-gray-600 dark:text-gray-400">
                        Wyeliminowani (poza oficjalnym rankingiem)
                    </h2>
                    <div class="overflow-hidden rounded-lg bg-white opacity-70 shadow dark:bg-gray-800">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="entry in filteredEliminated" :key="entry.id" class="text-gray-400">
                                    <td class="px-4 py-3 text-sm">🚫</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <Link
                                                :href="route('participants.show', entry.id)"
                                                class="font-medium line-through hover:text-indigo-400"
                                            >{{ entry.name }}</Link>
                                            <span v-if="entry.paid_entry" title="Wpłacił 10 zł">💰</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm">{{ entry.points }}</td>
                                    <td class="hidden px-4 py-3 text-center text-sm sm:table-cell">{{ entry.bets_count }}</td>
                                    <td class="hidden px-4 py-3 text-center text-sm sm:table-cell">{{ entry.missed_count }}</td>
                                    <td class="hidden px-4 py-3 text-sm text-gray-400 lg:table-cell">{{ entry.top_scorer ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
