<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Menu, X, Globe, Moon, UserPlus, LogIn } from '@lucide/vue';
import { useTheme } from '../composables/useTheme';
import { usePreferences } from '../composables/usePreferences';
import FlashMessage from '../Components/ui/FlashMessage.vue';

const menuOpen = ref(false);
const { theme, toggleTheme } = useTheme();
const { locale, setLanguage } = usePreferences();

const isDark = computed(() => theme.value === 'dark');
const otherLocaleLabel = computed(() => (locale.value === 'es' ? 'English' : 'Español'));

function toggleLanguage() {
    setLanguage(locale.value === 'es' ? 'en' : 'es');
}
</script>

<template>
    <div class="mx-auto flex min-h-screen w-full max-w-[480px] flex-col bg-[var(--surface)]">
        <FlashMessage />

        <header class="relative border-b border-[var(--surface-mute)] bg-[var(--surface)] p-4">
            <div class="flex items-center justify-between">
                <Link href="/" class="flex items-center gap-1.5">
                    <span class="text-lg">💈</span>
                    <span class="text-sm font-extrabold tracking-tight text-[var(--text-strong)]">{{
                        $t('app.name')
                    }}</span>
                </Link>
                <button
                    type="button"
                    class="flex h-8.5 w-8.5 items-center justify-center rounded-[10px] bg-[var(--surface-alt)] hover:bg-[var(--surface-mute)]"
                    @click="menuOpen = !menuOpen"
                >
                    <X v-if="menuOpen" :size="18" class="text-[var(--text-strong)]" />
                    <Menu v-else :size="18" class="text-[var(--text-strong)]" />
                </button>
            </div>

            <div v-if="menuOpen" class="fixed inset-0 z-30" @click="menuOpen = false" />

            <div
                v-if="menuOpen"
                class="absolute right-4 top-[66px] z-40 w-[230px] overflow-hidden rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] shadow-[0_12px_32px_rgba(15,23,42,0.18)]"
            >
                <button
                    type="button"
                    class="flex w-full items-center justify-between border-b border-[var(--surface-mute)] px-4 py-3.5 hover:bg-[var(--surface-alt)]"
                    @click="toggleLanguage"
                >
                    <span class="flex items-center gap-2.5">
                        <Globe :size="16" class="text-[var(--text-mute)]" />
                        <span class="text-[13px] font-bold text-[var(--text-strong)]">{{ $t('menu.language') }}</span>
                    </span>
                    <span class="text-xs font-bold text-[var(--text-mute)]">{{ otherLocaleLabel }}</span>
                </button>
                <button
                    type="button"
                    class="flex w-full items-center justify-between border-b border-[var(--surface-mute)] px-4 py-3.5 hover:bg-[var(--surface-alt)]"
                    @click="toggleTheme"
                >
                    <span class="flex items-center gap-2.5">
                        <Moon :size="16" class="text-[var(--text-mute)]" />
                        <span class="text-[13px] font-bold text-[var(--text-strong)]">{{ $t('menu.darkTheme') }}</span>
                    </span>
                    <span
                        class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors"
                        :class="isDark ? 'bg-[var(--btn-green)]' : 'bg-[var(--border-strong)]'"
                    >
                        <span
                            class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform"
                            :class="isDark ? 'translate-x-4.5' : 'translate-x-0.5'"
                        />
                    </span>
                </button>
                <Link
                    href="/crear-cuenta"
                    class="flex items-center gap-2.5 border-b border-[var(--surface-mute)] px-4 py-3.5 hover:bg-[var(--surface-alt)]"
                >
                    <UserPlus :size="16" class="text-[var(--text-mute)]" />
                    <span class="text-[13px] font-bold text-[var(--text-strong)]">{{ $t('menu.createAccount') }}</span>
                </Link>
                <Link href="/iniciar-sesion" class="flex items-center gap-2.5 px-4 py-3.5 hover:bg-[var(--surface-alt)]">
                    <LogIn :size="16" class="text-[var(--btn-bg)]" />
                    <span class="text-[13px] font-extrabold text-[var(--btn-bg)]">{{ $t('menu.signIn') }}</span>
                </Link>
            </div>
        </header>

        <main class="flex-1">
            <slot />
        </main>
    </div>
</template>
