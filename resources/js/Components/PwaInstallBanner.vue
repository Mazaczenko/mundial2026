<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';

const show = ref(false);
const dismissed = ref(false);
let deferredPrompt: BeforeInstallPromptEvent | null = null;

interface BeforeInstallPromptEvent extends Event {
    prompt(): Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

function onBeforeInstallPrompt(e: Event) {
    e.preventDefault();
    deferredPrompt = e as BeforeInstallPromptEvent;
    if (!dismissed.value) {
        show.value = true;
    }
}

function onAppInstalled() {
    show.value = false;
    deferredPrompt = null;
}

async function install() {
    if (!deferredPrompt) return;
    await deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;
    if (outcome === 'dismissed') {
        dismissed.value = true;
    }
    show.value = false;
    deferredPrompt = null;
}

function dismiss() {
    show.value = false;
    dismissed.value = true;
}

onMounted(() => {
    window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);
    window.addEventListener('appinstalled', onAppInstalled);
});

onUnmounted(() => {
    window.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt);
    window.removeEventListener('appinstalled', onAppInstalled);
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-full opacity-0"
    >
        <div
            v-if="show"
            class="fixed bottom-0 left-0 right-0 z-50 flex items-center gap-3 border-t border-green-500/20 bg-gray-900/95 px-4 py-3 shadow-2xl backdrop-blur-sm"
        >
            <!-- Icon -->
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-green-500/20">
                <svg class="h-5 w-5 text-green-400" viewBox="0 0 100 100" aria-hidden="true">
                    <circle cx="50" cy="50" r="47" fill="white" />
                    <polygon points="50,5 62,30 88,30 67,48 75,73 50,56 25,73 33,48 12,30 38,30" fill="#111827" opacity="0.85" />
                </svg>
            </div>

            <!-- Text -->
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-white">Zainstaluj Mundial 2026</p>
                <p class="text-xs text-gray-400">Dodaj do ekranu głównego</p>
            </div>

            <!-- Buttons -->
            <div class="flex shrink-0 items-center gap-2">
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium text-gray-400 hover:text-gray-200"
                    @click="dismiss"
                >
                    Nie teraz
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-green-500 px-3 py-1.5 text-xs font-bold text-white hover:bg-green-400 active:bg-green-600"
                    @click="install"
                >
                    Instaluj
                </button>
            </div>
        </div>
    </Transition>
</template>
