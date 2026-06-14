<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
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

interface Props {
    ranking: RankingEntry[];
    chartData: {
        labels: string[];
        datasets: ChartDataset[];
    } | [];
    playedMatchesCount: number;
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
            borderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            tension: 0.3,
            spanGaps: true,
        })),
    };
});

const showChart = ref(true);

type SortCol = 'rank' | 'name' | 'points' | 'bets_count' | 'missed_count';
type SortDir = 'asc' | 'desc';

const PER_PAGE = 25;
const search = ref('');
const sortCol = ref<SortCol>('rank');
const sortDir = ref<SortDir>('asc');
const page = ref(1);

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
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr
                                v-for="entry in paginated"
                                :key="entry.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            >
                                <td class="px-4 py-3 text-sm font-medium text-gray-500">
                                    {{ rankOf(entry) }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-900 dark:text-white">{{ entry.name }}</span>
                                        <span v-if="entry.paid_entry" title="Wpłacił 10 zł">💰</span>
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
                            </tr>

                            <tr v-if="paginated.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">
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
                                            <span class="font-medium line-through">{{ entry.name }}</span>
                                            <span v-if="entry.paid_entry" title="Wpłacił 10 zł">💰</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm">{{ entry.points }}</td>
                                    <td class="hidden px-4 py-3 text-center text-sm sm:table-cell">{{ entry.bets_count }}</td>
                                    <td class="hidden px-4 py-3 text-center text-sm sm:table-cell">{{ entry.missed_count }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
