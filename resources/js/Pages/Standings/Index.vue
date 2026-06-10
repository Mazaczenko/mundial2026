<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import type { GroupStanding } from '@/types';

interface Props {
    standings: Record<string, GroupStanding[]>;
}

defineProps<Props>();

function goalDiff(team: GroupStanding): string {
    const diff = team.goals_for - team.goals_against;
    return diff > 0 ? `+${diff}` : String(diff);
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Tabele grup — Mundial 2026" />

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h1 class="mb-6 text-2xl font-bold text-gray-800 dark:text-gray-100">Tabele grup</h1>

                <div v-if="Object.keys(standings).length === 0" class="rounded-lg bg-white p-8 text-center text-gray-500 shadow dark:bg-gray-800">
                    Tabele nie zostały jeszcze zsynchronizowane. Admin musi uruchomić synchronizację (<code>SyncStandingsJob</code>).
                </div>

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="(teams, groupName) in standings"
                        :key="groupName"
                        class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800"
                    >
                        <div class="bg-indigo-600 px-4 py-2">
                            <h2 class="font-bold text-white">Grupa {{ groupName }}</h2>
                        </div>
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-100 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:bg-gray-900">
                                    <th class="px-4 py-2.5 text-left w-6">#</th>
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
                                    <td class="px-4 py-3 text-sm text-gray-400 font-medium">{{ idx + 1 }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <img v-if="team.team_flag" :src="team.team_flag" class="h-5 w-7 object-contain flex-shrink-0" :alt="team.team_name" />
                                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ team.team_name }}</span>
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
    </AuthenticatedLayout>
</template>
