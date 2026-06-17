<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Bar } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    BarController,
    Title,
    Tooltip,
    Legend,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, BarElement, BarController, Title, Tooltip, Legend);

interface Scorer {
    rank: number;
    name: string;
    team: string;
    flag: string | null;
    goals: number;
    own_goals: number;
}

interface CountryGoals {
    team: string;
    flag: string | null;
    goals: number;
}

interface MinuteBucket {
    bucket: string;
    count: number;
}

interface HatTrick {
    name: string;
    match: string;
    goals: number;
}

interface MostGoalsMatch {
    match: string;
    score: string;
    goals: number;
}

interface Stats {
    total_goals: number;
    finished_matches: number;
    avg_per_match: number;
    hat_tricks: HatTrick[];
    most_goals_match: MostGoalsMatch | null;
    late_drama: number;
}

interface Pagination {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
    from: number;
    to: number;
}

interface Props {
    topScorers: Scorer[];
    podiumScorers: Scorer[];
    goalsByCountry: CountryGoals[];
    goalsByMinute: MinuteBucket[];
    stats: Stats;
    pagination: Pagination;
}

const props = defineProps<Props>();

const PER_PAGE_OPTIONS = [5, 10, 15, 20, 25, 50];
const perPage = ref(props.pagination.per_page);

function goToPage(p: number) {
    router.get(route('scorers.index'), { page: p, per_page: perPage.value }, { preserveScroll: true });
}

function changePerPage() {
    router.get(route('scorers.index'), { page: 1, per_page: perPage.value }, { preserveScroll: true });
}

const visiblePages = computed(() => {
    const { current_page, last_page } = props.pagination;
    if (last_page <= 7) {
        return Array.from({ length: last_page }, (_, i) => i + 1);
    }
    const pages: (number | '...')[] = [1];
    if (current_page > 3) pages.push('...');
    const start = Math.max(2, current_page - 1);
    const end   = Math.min(last_page - 1, current_page + 1);
    for (let i = start; i <= end; i++) pages.push(i);
    if (current_page < last_page - 2) pages.push('...');
    pages.push(last_page);
    return pages;
});

const leader = computed(() => props.podiumScorers[0] ?? null);

const podium = computed(() => {
    const top = props.podiumScorers.slice(0, 3);
    if (top.length < 2) return top;
    // reorder: [2nd, 1st, 3rd] for visual podium effect
    if (top.length === 3) return [top[1], top[0], top[2]];
    if (top.length === 2) return [top[1], top[0]];
    return top;
});

const podiumHeights = ['h-28', 'h-36', 'h-24'];
const podiumBorders = ['border-gray-400', 'border-yellow-400', 'border-orange-400'];
const podiumBgs = ['bg-gray-50 dark:bg-gray-700/60', 'bg-yellow-50 dark:bg-yellow-900/20', 'bg-orange-50 dark:bg-orange-900/20'];
const podiumRankColors = ['text-gray-500', 'text-yellow-500', 'text-orange-500'];

function podiumStyle(scorer: Scorer) {
    const i = scorer.rank === 1 ? 1 : scorer.rank === 2 ? 0 : 2;
    return { height: podiumHeights[i], border: podiumBorders[i], bg: podiumBgs[i], rank: podiumRankColors[i] };
}

const countryChartData = computed(() => {
    const slice = props.goalsByCountry.slice(0, 16);
    return {
        labels: slice.map((c) => c.team),
        datasets: [
            {
                label: 'Gole',
                data: slice.map((c) => c.goals),
                backgroundColor: slice.map((_, i) => `rgba(99,102,241,${1 - i * 0.045})`),
                borderColor: 'rgba(99,102,241,0.8)',
                borderWidth: 1,
                borderRadius: 4,
            },
        ],
    };
});

const minuteColors = [
    'rgba(16,185,129,0.8)',
    'rgba(52,211,153,0.8)',
    'rgba(251,191,36,0.8)',
    'rgba(245,158,11,0.8)',
    'rgba(249,115,22,0.8)',
    'rgba(239,68,68,0.8)',
    'rgba(185,28,28,0.8)',
];

const minuteChartData = computed(() => ({
    labels: props.goalsByMinute.map((b) => b.bucket),
    datasets: [
        {
            label: 'Gole',
            data: props.goalsByMinute.map((b) => b.count),
            backgroundColor: minuteColors,
            borderColor: minuteColors.map((c) => c.replace('0.8', '1')),
            borderWidth: 1,
            borderRadius: 4,
        },
    ],
}));

const horizontalBarOptions = {
    indexAxis: 'y' as const,
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (item: any) => ` ${item.raw} goli`,
            },
        },
    },
    scales: {
        x: {
            beginAtZero: true,
            ticks: { stepSize: 1, font: { size: 11 } },
            grid: { color: 'rgba(156,163,175,0.15)' },
        },
        y: {
            ticks: { font: { size: 11 } },
            grid: { display: false },
        },
    },
};

const verticalBarOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (item: any) => ` ${item.raw} goli`,
            },
        },
    },
    scales: {
        x: {
            ticks: { font: { size: 11 } },
            grid: { display: false },
        },
        y: {
            beginAtZero: true,
            ticks: { stepSize: 1, font: { size: 11 } },
            grid: { color: 'rgba(156,163,175,0.15)' },
        },
    },
};

const countryChartHeight = computed(() =>
    Math.max(220, Math.min(props.goalsByCountry.slice(0, 16).length * 30, 480))
);
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Strzelcy" />

        <div class="py-6">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">

                <h1 class="mb-6 text-2xl font-bold text-gray-800 dark:text-gray-100">Klasyfikacja strzelców</h1>

                <!-- Empty state -->
                <div v-if="stats.total_goals === 0" class="rounded-lg bg-white px-8 py-16 text-center shadow dark:bg-gray-800">
                    <svg class="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    <p class="text-lg font-medium text-gray-500 dark:text-gray-400">Brak danych o bramkach — pojawią się po zakończeniu meczów</p>
                </div>

                <template v-else>

                    <!-- Hero stats -->
                    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div class="rounded-lg bg-white px-5 py-4 shadow dark:bg-gray-800">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Łącznie bramek</p>
                            <p class="mt-1 text-3xl font-bold text-indigo-600 dark:text-indigo-400">{{ stats.total_goals }}</p>
                        </div>
                        <div class="rounded-lg bg-white px-5 py-4 shadow dark:bg-gray-800">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Rozegranych meczów</p>
                            <p class="mt-1 text-3xl font-bold text-indigo-600 dark:text-indigo-400">{{ stats.finished_matches }}</p>
                        </div>
                        <div class="rounded-lg bg-white px-5 py-4 shadow dark:bg-gray-800">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Średnia goli/mecz</p>
                            <p class="mt-1 text-3xl font-bold text-indigo-600 dark:text-indigo-400">{{ stats.avg_per_match }}</p>
                        </div>
                        <div class="rounded-lg bg-white px-5 py-4 shadow dark:bg-gray-800">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Lider strzelców</p>
                            <template v-if="leader">
                                <p class="mt-1 truncate text-lg font-bold text-gray-900 dark:text-white">{{ leader.name }}</p>
                                <p class="text-sm text-indigo-600 dark:text-indigo-400">{{ leader.goals }} goli</p>
                            </template>
                            <p v-else class="mt-1 text-sm text-gray-400">—</p>
                        </div>
                    </div>

                    <!-- Podium top 3 -->
                    <div v-if="topScorers.length > 0" class="mb-6 overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                        <div class="bg-indigo-600 px-4 py-2.5">
                            <h2 class="font-bold tracking-wide text-white">Podium</h2>
                        </div>
                        <div class="flex items-end justify-center gap-4 px-6 py-6">
                            <div
                                v-for="scorer in podium"
                                :key="scorer.rank"
                                class="flex flex-col items-center rounded-lg border-2 px-4 py-4 text-center transition-shadow hover:shadow-md"
                                :class="[
                                    podiumStyle(scorer).bg,
                                    podiumStyle(scorer).border,
                                    podiumStyle(scorer).height,
                                    'justify-end',
                                    scorer.rank === 1 ? 'min-w-[130px]' : 'min-w-[110px]',
                                ]"
                            >
                                <span class="text-2xl font-black leading-none" :class="podiumStyle(scorer).rank">
                                    {{ scorer.rank === 1 ? '🥇' : scorer.rank === 2 ? '🥈' : '🥉' }}
                                </span>
                                <img v-if="scorer.flag" :src="scorer.flag" :alt="scorer.team" class="mt-2 h-6 w-9 object-contain" />
                                <p class="mt-1 text-sm font-semibold leading-tight text-gray-900 dark:text-white">{{ scorer.name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ scorer.team }}</p>
                                <p class="mt-1 text-xl font-black text-indigo-600 dark:text-indigo-400">{{ scorer.goals }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Full scorers table -->
                    <div v-if="pagination.total > 0" class="mb-6 overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                        <div class="bg-indigo-600 px-4 py-2.5">
                            <h2 class="font-bold tracking-wide text-white">Pełna tabela strzelców</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">#</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Zawodnik</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Drużyna</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500">Gole</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr
                                        v-for="scorer in topScorers"
                                        :key="scorer.rank"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                        :class="scorer.rank <= 3 ? 'bg-indigo-50/40 dark:bg-indigo-900/10' : ''"
                                    >
                                        <td class="px-4 py-3 text-sm font-medium text-gray-500">{{ scorer.rank }}</td>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ scorer.name }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <img v-if="scorer.flag" :src="scorer.flag" :alt="scorer.team" class="h-4 w-6 flex-shrink-0 object-contain" />
                                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ scorer.team }}</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-sm font-semibold text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300">
                                                {{ scorer.goals }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="flex flex-col gap-3 border-t border-gray-100 px-4 py-3 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                                <span>{{ pagination.from }}–{{ pagination.to }} z {{ pagination.total }} strzelców</span>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs">Pokaż:</span>
                                    <select
                                        v-model="perPage"
                                        @change="changePerPage"
                                        class="rounded border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                    >
                                        <option v-for="n in PER_PAGE_OPTIONS" :key="n" :value="n">{{ n }}</option>
                                    </select>
                                </div>
                            </div>

                            <div v-if="pagination.last_page > 1" class="flex items-center gap-1">
                                <button
                                    @click="goToPage(pagination.current_page - 1)"
                                    :disabled="pagination.current_page === 1"
                                    class="rounded px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-30 dark:text-gray-400 dark:hover:bg-gray-700"
                                >‹</button>
                                <template v-for="p in visiblePages" :key="p">
                                    <span v-if="p === '...'" class="px-1 text-sm text-gray-400">…</span>
                                    <button
                                        v-else
                                        @click="goToPage(p as number)"
                                        class="min-w-[2rem] rounded px-2 py-1 text-sm transition-colors"
                                        :class="p === pagination.current_page
                                            ? 'bg-indigo-600 font-semibold text-white'
                                            : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'"
                                    >{{ p }}</button>
                                </template>
                                <button
                                    @click="goToPage(pagination.current_page + 1)"
                                    :disabled="pagination.current_page === pagination.last_page"
                                    class="rounded px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-30 dark:text-gray-400 dark:hover:bg-gray-700"
                                >›</button>
                            </div>
                        </div>
                    </div>

                    <!-- Charts -->
                    <div class="mb-6 grid gap-6 lg:grid-cols-2">

                        <!-- Goals by country -->
                        <div v-if="goalsByCountry.length > 0" class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                            <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Gole według kraju</h2>
                            </div>
                            <div class="p-4">
                                <div :style="{ height: countryChartHeight + 'px' }">
                                    <Bar :data="countryChartData" :options="horizontalBarOptions" />
                                </div>
                            </div>
                        </div>

                        <!-- Goals by minute -->
                        <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                            <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Rozkład bramek w minutach</h2>
                            </div>
                            <div class="p-4">
                                <div class="relative h-64">
                                    <Bar :data="minuteChartData" :options="verticalBarOptions" />
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Curiosities -->
                    <div class="mb-6">
                        <h2 class="mb-3 text-lg font-semibold text-gray-700 dark:text-gray-300">Statystyki turnieju</h2>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">

                            <!-- Hat-tricks -->
                            <div v-if="stats.hat_tricks.length > 0" class="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                                <div class="mb-3 flex items-center gap-2">
                                    <span class="text-xl">🎩</span>
                                    <h3 class="font-semibold text-gray-800 dark:text-gray-200">Hat-tricki</h3>
                                </div>
                                <ul class="space-y-2">
                                    <li v-for="ht in stats.hat_tricks" :key="ht.name + ht.match" class="text-sm">
                                        <span class="font-medium text-gray-900 dark:text-white">{{ ht.name }}</span>
                                        <span class="text-indigo-600 dark:text-indigo-400"> ({{ ht.goals }} gole)</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ ht.match }}</p>
                                    </li>
                                </ul>
                            </div>

                            <!-- Most goals match -->
                            <div v-if="stats.most_goals_match" class="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                                <div class="mb-3 flex items-center gap-2">
                                    <span class="text-xl">🔥</span>
                                    <h3 class="font-semibold text-gray-800 dark:text-gray-200">Najbardziej bramkowy mecz</h3>
                                </div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ stats.most_goals_match.match }}</p>
                                <p class="mt-1 text-2xl font-black text-indigo-600 dark:text-indigo-400">{{ stats.most_goals_match.score }}</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ stats.most_goals_match.goals }} bramek łącznie</p>
                            </div>

                            <!-- Late drama -->
                            <div class="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                                <div class="mb-3 flex items-center gap-2">
                                    <span class="text-xl">⏱️</span>
                                    <h3 class="font-semibold text-gray-800 dark:text-gray-200">Gole w końcówce (80+)</h3>
                                </div>
                                <p class="text-3xl font-black text-amber-500">{{ stats.late_drama }}</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ stats.late_drama === 0 ? 'Brak dramatycznych końcówek' : stats.late_drama === 1 ? 'Jeden gol w doliczonym czasie' : `Bramki po 80. minucie` }}
                                </p>
                            </div>

                        </div>
                    </div>

                </template>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
