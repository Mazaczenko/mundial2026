<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import PwaInstallBanner from '@/Components/PwaInstallBanner.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({
    email: '',
    password: '',
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login.post'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Logowanie — Mundial 2026" />
    <PwaInstallBanner />

    <div class="login-bg relative flex min-h-dvh flex-col items-center justify-center overflow-hidden px-4 py-12">

        <!-- Pitch line overlay -->
        <div class="pitch-lines pointer-events-none absolute inset-0" aria-hidden="true" />

        <!-- Stadium light glows -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <div class="glow glow-tl" />
            <div class="glow glow-br" />
        </div>

        <!-- Hero header -->
        <div class="relative z-10 mb-10 text-center">
            <!-- Ball SVG -->
            <div class="ball-float mx-auto mb-4 flex h-20 w-20 items-center justify-center">
                <svg viewBox="0 0 100 100" class="h-full w-full drop-shadow-[0_0_24px_rgba(34,197,94,0.6)]" aria-hidden="true">
                    <circle cx="50" cy="50" r="47" fill="white" />
                    <!-- Pentagons -->
                    <polygon points="50,5 62,30 88,30 67,48 75,73 50,56 25,73 33,48 12,30 38,30" fill="#111827" opacity="0.85" />
                    <circle cx="50" cy="50" r="47" fill="none" stroke="#d1d5db" stroke-width="2" />
                </svg>
            </div>

            <h1 class="font-russo text-5xl font-black uppercase tracking-widest text-white sm:text-6xl">
                Mundial
                <span class="text-green-400">2026</span>
            </h1>
            <p class="font-chakra mt-2 text-sm font-medium uppercase tracking-[0.3em] text-green-400/80">
                FIFA World Cup · Typowanie
            </p>

            <!-- Country flags strip -->
            <div class="mt-5 flex items-center justify-center gap-1 opacity-60" aria-hidden="true">
                <span class="flag-chip">🇺🇸</span>
                <span class="separator">·</span>
                <span class="flag-chip">🇲🇽</span>
                <span class="separator">·</span>
                <span class="flag-chip">🇨🇦</span>
                <span class="separator">·</span>
                <span class="flag-chip text-xs font-chakra font-medium tracking-widest text-gray-400 uppercase">USA · Meksyk · Kanada</span>
            </div>
        </div>

        <!-- Login card -->
        <div class="login-card relative z-10 w-full max-w-sm">
            <div class="card-glow" aria-hidden="true" />

            <form @submit.prevent="submit" novalidate class="relative">
                <div class="mb-2 text-center">
                    <p class="font-chakra text-xs font-medium uppercase tracking-widest text-green-400/70">
                        Zaloguj się do typowania
                    </p>
                </div>

                <div class="mt-5 space-y-4">
                    <!-- Email -->
                    <div>
                        <label for="email" class="font-chakra mb-1.5 block text-xs font-semibold uppercase tracking-widest text-gray-300">
                            Email
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-green-500/60" aria-hidden="true">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                                </svg>
                            </span>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                                autofocus
                                required
                                class="field-input pl-9"
                                :class="{ 'field-error': form.errors.email }"
                                placeholder="twoj@email.pl"
                            />
                        </div>
                        <InputError :message="form.errors.email" class="mt-1.5 font-chakra text-xs text-red-400" />
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="font-chakra mb-1.5 block text-xs font-semibold uppercase tracking-widest text-gray-300">
                            Hasło
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-green-500/60" aria-hidden="true">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </span>
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                required
                                class="field-input pl-9 pr-10"
                                :class="{ 'field-error': form.errors.password }"
                                placeholder="••••••••"
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-3 flex cursor-pointer items-center text-gray-500 transition-colors hover:text-green-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-400"
                                :aria-label="showPassword ? 'Ukryj hasło' : 'Pokaż hasło'"
                                @click="showPassword = !showPassword"
                            >
                                <svg v-if="!showPassword" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                            </button>
                        </div>
                        <InputError :message="form.errors.password" class="mt-1.5 font-chakra text-xs text-red-400" />
                    </div>
                </div>

                <!-- Submit -->
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="submit-btn font-russo mt-8 w-full cursor-pointer text-sm uppercase tracking-widest"
                >
                    <span v-if="!form.processing" class="flex items-center justify-center gap-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                        Wchodzę na boisko
                    </span>
                    <span v-else class="flex items-center justify-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                        </svg>
                        Logowanie...
                    </span>
                </button>

                <div class="mt-5 text-center">
                    <a
                        :href="route('password.request')"
                        class="font-chakra text-xs font-medium uppercase tracking-widest text-gray-500 transition-colors hover:text-green-400"
                    >
                        Nie pamiętam hasła
                    </a>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <p class="font-chakra relative z-10 mt-10 text-center text-xs tracking-widest text-gray-600 uppercase">
            Fortis &copy; 2026 · Prywatna liga typowania
        </p>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;500;600;700&family=Russo+One&display=swap');

.font-russo  { font-family: 'Russo One', sans-serif; }
.font-chakra { font-family: 'Chakra Petch', sans-serif; }

/* ── Background ── */
.login-bg {
    background: radial-gradient(ellipse 80% 60% at 50% 0%, #0d3d1a 0%, #071209 55%, #030a04 100%);
}

.pitch-lines {
    background-image:
        /* horizontal centre line */
        linear-gradient(to bottom, transparent calc(50% - 0.5px), rgba(255,255,255,0.04) calc(50% - 0.5px), rgba(255,255,255,0.04) calc(50% + 0.5px), transparent calc(50% + 0.5px)),
        /* vertical stripes (pitch bands) */
        repeating-linear-gradient(
            90deg,
            transparent 0px,
            transparent 80px,
            rgba(255,255,255,0.015) 80px,
            rgba(255,255,255,0.015) 160px
        );
}

/* Stadium lights */
.glow {
    position: absolute;
    border-radius: 50%;
    filter: blur(100px);
    opacity: 0.25;
}
.glow-tl {
    top: -80px; left: -80px;
    width: 400px; height: 400px;
    background: radial-gradient(circle, #16a34a 0%, transparent 70%);
}
.glow-br {
    bottom: -80px; right: -80px;
    width: 350px; height: 350px;
    background: radial-gradient(circle, #eab308 0%, transparent 70%);
}

/* ── Floating ball ── */
.ball-float {
    animation: float 3.6s ease-in-out infinite;
}
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50%       { transform: translateY(-10px); }
}

/* ── Flag chips ── */
.flag-chip   { font-size: 1.1rem; }
.separator   { color: #374151; font-size: 0.6rem; }

/* ── Login card ── */
.login-card {
    background: rgba(7, 18, 9, 0.75);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(34, 197, 94, 0.18);
    border-radius: 1.25rem;
    padding: 2rem 1.75rem;
    box-shadow:
        0 0 0 1px rgba(34, 197, 94, 0.06),
        0 25px 50px -12px rgba(0, 0, 0, 0.7),
        inset 0 1px 0 rgba(255,255,255,0.04);
}
.card-glow {
    position: absolute;
    inset: -1px;
    border-radius: inherit;
    background: linear-gradient(135deg, rgba(34,197,94,0.12) 0%, transparent 60%);
    pointer-events: none;
}

/* ── Inputs ── */
.field-input {
    display: block;
    width: 100%;
    border-radius: 0.625rem;
    border: 1px solid rgba(34, 197, 94, 0.2);
    background: rgba(0, 0, 0, 0.35);
    padding: 0.65rem 0.875rem;
    font-family: 'Chakra Petch', sans-serif;
    font-size: 0.9rem;
    color: #f9fafb;
    transition: border-color 180ms ease, box-shadow 180ms ease;
    outline: none;
}
.field-input::placeholder { color: #4b5563; }
.field-input:focus {
    border-color: rgba(34, 197, 94, 0.6);
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.12);
}
.field-input.field-error {
    border-color: rgba(248, 113, 113, 0.5);
}
.field-input.field-error:focus {
    box-shadow: 0 0 0 3px rgba(248, 113, 113, 0.12);
}

/* ── Submit button ── */
.submit-btn {
    position: relative;
    overflow: hidden;
    border-radius: 0.75rem;
    padding: 0.85rem 1.5rem;
    font-weight: 400;
    color: #fff;
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    border: 1px solid rgba(34, 197, 94, 0.3);
    box-shadow: 0 4px 24px rgba(22, 163, 74, 0.35), inset 0 1px 0 rgba(255,255,255,0.1);
    transition: transform 150ms ease, box-shadow 150ms ease, background 150ms ease;
}
.submit-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.08) 0%, transparent 60%);
    pointer-events: none;
}
.submit-btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 6px 30px rgba(22, 163, 74, 0.5), inset 0 1px 0 rgba(255,255,255,0.12);
    background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
}
.submit-btn:active:not(:disabled) {
    transform: translateY(0);
    box-shadow: 0 2px 12px rgba(22, 163, 74, 0.3);
}
.submit-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

@media (prefers-reduced-motion: reduce) {
    .ball-float { animation: none; }
    .submit-btn, .field-input { transition: none; }
}
</style>
