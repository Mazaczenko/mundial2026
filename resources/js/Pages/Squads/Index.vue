<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface Player {
    id: number;
    name: string;
    position: string;
}

interface Props {
    squads: Record<string, Record<string, Player[]>>;
    crests: Record<string, string | null>;
    filters: { search: string };
    pagination: {
        current_page: number;
        last_page: number;
        total: number;
    };
}

const props = defineProps<Props>();

const search = ref(props.filters.search);

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

watch(search, (val) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('squads.index'), { search: val, page: 1 }, { preserveScroll: true, replace: true });
    }, 350);
});

function goToPage(page: number) {
    router.get(route('squads.index'), { search: search.value, page }, { preserveScroll: false });
}

const positionLabels: Record<string, string> = {
    Goalkeeper: 'Bramkarze',
    Defence: 'Obrońcy',
    Midfield: 'Pomocnicy',
    Offence: 'Napastnicy',
};

const positionOrder = ['Goalkeeper', 'Defence', 'Midfield', 'Offence'];
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Składy — Mundial 2026" />

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

                <div class="mb-4 flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Składy drużyn</h1>

                    <div class="relative min-w-[180px] flex-1">
                        <svg class="pointer-events-none absolute inset-y-0 left-2.5 my-auto h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                        </svg>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Szukaj drużyny lub zawodnika…"
                            class="w-full rounded-lg border border-gray-300 bg-white py-1.5 pl-8 pr-3 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        />
                    </div>
                </div>

                <div v-if="Object.keys(squads).length === 0" class="py-8 text-center text-sm text-gray-400">
                    Brak wyników dla „{{ search }}".
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="(positionGroups, teamName) in squads"
                        :key="teamName"
                        class="rounded-lg bg-white p-4 shadow dark:bg-gray-800"
                    >
                        <div class="mb-3 flex items-center gap-2">
                            <img v-if="crests[teamName]" :src="crests[teamName]!" :alt="teamName" class="h-7 w-7 object-contain" />
                            <h2 class="text-base font-bold text-gray-900 dark:text-white">{{ teamName }}</h2>
                        </div>

                        <div v-for="position in positionOrder" :key="position" class="mb-2">
                            <template v-if="positionGroups[position]?.length">
                                <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                    {{ positionLabels[position] }}
                                </div>
                                <div
                                    v-for="player in positionGroups[position]"
                                    :key="player.id"
                                    class="text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{ player.name }}
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="pagination.last_page > 1" class="mt-6 flex items-center justify-between">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Drużyny: strona {{ pagination.current_page }} z {{ pagination.last_page }} ({{ pagination.total }} łącznie)
                    </p>
                    <div class="flex gap-1">
                        <button
                            v-for="page in pagination.last_page"
                            :key="page"
                            @click="goToPage(page)"
                            :disabled="page === pagination.current_page"
                            class="min-w-[2rem] rounded px-2.5 py-1 text-sm font-medium transition-colors"
                            :class="page === pagination.current_page
                                ? 'bg-indigo-600 text-white cursor-default'
                                : 'bg-white text-gray-700 hover:bg-gray-100 shadow-sm dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'"
                        >
                            {{ page }}
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
