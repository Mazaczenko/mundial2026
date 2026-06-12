<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { teamCode } from '@/utils/teamCode';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import type { GroupStanding } from '@/types';

interface Props {
    standings: Record<string, GroupStanding[]>;
}

const props = defineProps<Props>();

const activeGroup = ref<string | null>(null);

const groups = computed(() => Object.keys(props.standings).sort());

const visibleStandings = computed(() =>
    activeGroup.value
        ? { [activeGroup.value]: props.standings[activeGroup.value] }
        : props.standings
);
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Tabele grup" />

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

                <div class="mb-6 flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Tabele grup</h1>

                    <!-- Group filters -->
                    <div v-if="groups.length" class="flex flex-wrap gap-1.5 sm:ml-4">
                        <button
                            @click="activeGroup = null"
                            class="filter-btn"
                            :class="activeGroup === null ? 'filter-active' : 'filter-inactive'"
                        >
                            Wszystkie
                        </button>
                        <button
                            v-for="g in groups"
                            :key="g"
                            @click="activeGroup = activeGroup === g ? null : g"
                            class="filter-btn"
                            :class="activeGroup === g ? 'filter-active' : 'filter-inactive'"
                        >
                            Gr. {{ g }}
                        </button>
                    </div>
                </div>

                <div v-if="Object.keys(standings).length === 0" class="rounded-lg bg-white p-8 text-center text-gray-500 shadow dark:bg-gray-800">
                    Tabele nie zostały jeszcze zsynchronizowane. Admin musi uruchomić synchronizację (<code>mundial:sync --standings</code>).
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <div
                        v-for="(teams, groupName) in visibleStandings"
                        :key="groupName"
                        class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800"
                    >
                        <div class="bg-indigo-600 px-4 py-2.5">
                            <h2 class="font-bold text-white tracking-wide">Grupa {{ groupName }}</h2>
                        </div>
                        <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-100 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:bg-gray-900">
                                    <th class="px-4 py-2.5 text-left">Drużyna</th>
                                    <th class="px-3 py-2.5 text-center" title="Mecze">M</th>
                                    <th class="px-3 py-2.5 text-center" title="Wygrane">W</th>
                                    <th class="px-3 py-2.5 text-center" title="Remisy">R</th>
                                    <th class="px-3 py-2.5 text-center" title="Porażki">P</th>
                                    <th class="px-3 py-2.5 text-center" title="Bramki">G</th>
                                    <th class="px-3 py-2.5 text-center" title="Punkty">PKT</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr
                                    v-for="(team, idx) in teams"
                                    :key="team.api_team_id"
                                    class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                    :class="idx < 2 ? 'bg-green-50 dark:bg-green-900/10' : ''"
                                >
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <img v-if="team.team_flag" :src="team.team_flag" class="h-5 w-7 flex-shrink-0 object-contain" :alt="team.team_name" />
                                            <abbr class="cursor-default text-sm font-semibold text-gray-900 no-underline dark:text-white" :title="team.team_name">{{ teamCode(team.team_name) }}</abbr>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-center text-sm text-gray-600 dark:text-gray-400">{{ team.played }}</td>
                                    <td class="px-3 py-3 text-center text-sm text-gray-600 dark:text-gray-400">{{ team.won }}</td>
                                    <td class="px-3 py-3 text-center text-sm text-gray-600 dark:text-gray-400">{{ team.drawn }}</td>
                                    <td class="px-3 py-3 text-center text-sm text-gray-600 dark:text-gray-400">{{ team.lost }}</td>
                                    <td class="px-3 py-3 text-center text-sm text-gray-500">{{ team.goals_for }}:{{ team.goals_against }}</td>
                                    <td class="px-3 py-3 text-center text-sm font-bold text-gray-900 dark:text-white">{{ team.points }}</td>
                                </tr>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.filter-btn {
    @apply rounded-full px-3 py-1 text-xs font-semibold transition-colors cursor-pointer;
}
.filter-active {
    @apply bg-indigo-600 text-white;
}
.filter-inactive {
    @apply bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600;
}
</style>
