<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import type { RankingEntry } from '@/types';

interface Props {
    ranking: RankingEntry[];
}

const props = defineProps<Props>();

const PER_PAGE = 25;
const search = ref('');
const sortMode = ref<'rank' | 'alpha'>('rank');
const page = ref(1);

const active = computed(() => props.ranking.filter((p) => !p.eliminated));
const eliminated = computed(() => props.ranking.filter((p) => p.eliminated));

const filteredActive = computed(() => {
    let list = active.value;

    if (search.value.trim()) {
        const q = search.value.toLowerCase();
        list = list.filter((p) => p.name.toLowerCase().includes(q));
    }

    if (sortMode.value === 'alpha') {
        list = [...list].sort((a, b) => a.name.localeCompare(b.name, 'pl'));
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

// rank position respects original order (before pagination)
function rankOf(entry: RankingEntry): number {
    return active.value.indexOf(entry) + 1;
}

function setSearch(val: string) {
    search.value = val;
    page.value = 1;
}

function setSort(mode: 'rank' | 'alpha') {
    sortMode.value = mode;
    page.value = 1;
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Ranking — Mundial 2026" />

        <div class="py-6">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">

                <!-- Header + controls -->
                <div class="mb-4 flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Ranking</h1>

                    <div class="flex flex-1 flex-wrap items-center gap-2 sm:ml-4">
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

                        <!-- Sort toggle -->
                        <div class="flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden text-sm">
                            <button
                                @click="setSort('rank')"
                                class="px-3 py-1.5 font-medium transition-colors"
                                :class="sortMode === 'rank'
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-white text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300'"
                            >Ranking</button>
                            <button
                                @click="setSort('alpha')"
                                class="px-3 py-1.5 font-medium transition-colors border-l border-gray-300 dark:border-gray-600"
                                :class="sortMode === 'alpha'
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-white text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300'"
                            >A–Z</button>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">#</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Uczestnik</th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500">Pkt</th>
                                <th class="hidden px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500 sm:table-cell">Obst.</th>
                                <th class="hidden px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500 sm:table-cell">Pominięte</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr
                                v-for="entry in paginated"
                                :key="entry.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            >
                                <td class="px-4 py-3 text-sm font-medium text-gray-500">
                                    {{ sortMode === 'rank' ? rankOf(entry) : '–' }}
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
                                        {{ entry.missed_count }}<span v-if="entry.missed_count >= 2" class="text-xs">/3</span>
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
