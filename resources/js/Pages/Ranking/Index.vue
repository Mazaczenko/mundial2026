<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import type { RankingEntry } from '@/types';

interface Props {
    ranking: RankingEntry[];
}

const props = defineProps<Props>();

const active = props.ranking.filter((p) => !p.eliminated);
const eliminated = props.ranking.filter((p) => p.eliminated);
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Ranking — Mundial 2026" />

        <div class="py-6">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <h1 class="mb-6 text-2xl font-bold text-gray-800 dark:text-gray-100">Ranking</h1>

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
                                v-for="(entry, idx) in active"
                                :key="entry.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            >
                                <td class="px-4 py-3 text-sm font-medium text-gray-500">{{ idx + 1 }}</td>
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
                                        <span v-if="entry.missed_count >= 2" class="text-xs">/3</span>
                                    </span>
                                </td>
                            </tr>

                            <tr v-if="active.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">
                                    Brak uczestników w rankingu.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Eliminated section -->
                <div v-if="eliminated.length > 0" class="mt-8">
                    <h2 class="mb-3 text-lg font-semibold text-gray-600 dark:text-gray-400">
                        Wyeliminowani (poza oficjalnym rankingiem)
                    </h2>
                    <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800 opacity-70">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr
                                    v-for="entry in eliminated"
                                    :key="entry.id"
                                    class="text-gray-400"
                                >
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
