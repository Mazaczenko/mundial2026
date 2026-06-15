<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

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
}

interface Props {
    matchesByStage: Record<string, KnockoutMatch[]>
}

const props = defineProps<Props>();

const STAGE_ORDER = ['r32', 'r16', 'qf', 'sf', 'final'] as const;

const STAGE_LABELS: Record<string, string> = {
    r32: '1/16 finału',
    r16: '1/8 finału',
    qf: 'Ćwierćfinał',
    sf: 'Półfinał',
    final: 'Finał',
};

const stages = computed(() =>
    STAGE_ORDER.filter((s) => props.matchesByStage[s]?.length > 0),
);

const hasMatches = computed(() => stages.value.length > 0);

// The column height is based on the stage with the most matches × 100px.
// Every other stage uses flex + justify-evenly so cards are spaced proportionally.
const maxMatchCount = computed(() => {
    if (!hasMatches.value) return 0;
    return Math.max(...stages.value.map((s) => props.matchesByStage[s].length));
});

const columnHeight = computed(() => `${maxMatchCount.value * 100}px`);

function isWinner(match: KnockoutMatch, side: 'home' | 'away'): boolean {
    if (match.status !== 'finished' || match.score_home === null || match.score_away === null) {
        return false;
    }
    if (side === 'home') return match.score_home > match.score_away;
    return match.score_away > match.score_home;
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
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Drabinka pucharowa" />

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h1 class="mb-6 text-2xl font-bold text-gray-800 dark:text-gray-100">Drabinka pucharowa</h1>

                <div v-if="!hasMatches" class="rounded-lg bg-white p-8 text-center text-gray-500 shadow dark:bg-gray-800">
                    Faza pucharowa jeszcze się nie rozpoczęła.
                </div>

                <div v-else class="overflow-x-auto pb-4">
                    <div class="flex gap-6" :style="{ minWidth: `${stages.length * 216}px` }">
                        <div
                            v-for="stage in stages"
                            :key="stage"
                            class="flex w-48 shrink-0 flex-col"
                        >
                            <!-- Round label -->
                            <div class="mb-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                {{ STAGE_LABELS[stage] }}
                            </div>

                            <!-- Matches column -->
                            <div
                                class="flex flex-col justify-evenly"
                                :style="{ height: columnHeight }"
                            >
                                <div
                                    v-for="match in matchesByStage[stage]"
                                    :key="match.id"
                                    class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                                >
                                    <!-- Status bar -->
                                    <div class="flex items-center justify-end px-2 pt-1.5">
                                        <span
                                            v-if="match.status === 'in_play'"
                                            class="text-xs font-bold text-red-500"
                                        >
                                            LIVE
                                        </span>
                                        <span
                                            v-else-if="match.status === 'finished'"
                                            class="text-xs text-gray-400 dark:text-gray-500"
                                        >
                                            FT
                                        </span>
                                        <span
                                            v-else
                                            class="text-xs text-gray-400 dark:text-gray-500"
                                        >
                                            {{ formatKickoff(match.kickoff_at) }}
                                        </span>
                                    </div>

                                    <!-- Home team -->
                                    <div
                                        class="flex items-center gap-2 rounded-t-md px-2 py-1.5"
                                        :class="isWinner(match, 'home') ? 'bg-green-50 dark:bg-green-900/20' : ''"
                                    >
                                        <img
                                            v-if="match.home_team_flag && match.home_team !== 'TBD'"
                                            :src="match.home_team_flag"
                                            class="h-4 w-6 shrink-0 object-contain"
                                            :alt="match.home_team"
                                        />
                                        <span
                                            v-else
                                            class="h-4 w-6 shrink-0"
                                        />
                                        <span
                                            class="flex-1 truncate text-sm text-gray-800 dark:text-gray-200"
                                            :class="isWinner(match, 'home') ? 'font-bold' : 'font-medium'"
                                        >
                                            {{ teamName(match.home_team) }}
                                        </span>
                                        <span
                                            v-if="match.score_home !== null"
                                            class="text-sm font-bold text-gray-800 dark:text-gray-200"
                                            :class="isWinner(match, 'home') ? 'text-green-700 dark:text-green-400' : ''"
                                        >
                                            {{ match.score_home }}
                                        </span>
                                    </div>

                                    <!-- Divider -->
                                    <div class="mx-2 border-t border-gray-100 dark:border-gray-700" />

                                    <!-- Away team -->
                                    <div
                                        class="flex items-center gap-2 rounded-b-md px-2 py-1.5"
                                        :class="isWinner(match, 'away') ? 'bg-green-50 dark:bg-green-900/20' : ''"
                                    >
                                        <img
                                            v-if="match.away_team_flag && match.away_team !== 'TBD'"
                                            :src="match.away_team_flag"
                                            class="h-4 w-6 shrink-0 object-contain"
                                            :alt="match.away_team"
                                        />
                                        <span
                                            v-else
                                            class="h-4 w-6 shrink-0"
                                        />
                                        <span
                                            class="flex-1 truncate text-sm text-gray-800 dark:text-gray-200"
                                            :class="isWinner(match, 'away') ? 'font-bold' : 'font-medium'"
                                        >
                                            {{ teamName(match.away_team) }}
                                        </span>
                                        <span
                                            v-if="match.score_away !== null"
                                            class="text-sm font-bold text-gray-800 dark:text-gray-200"
                                            :class="isWinner(match, 'away') ? 'text-green-700 dark:text-green-400' : ''"
                                        >
                                            {{ match.score_away }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
