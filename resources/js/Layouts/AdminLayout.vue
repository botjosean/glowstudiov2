<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, Users, Sparkles, Clock, User, Settings, ArrowLeft, X } from '@lucide/vue';
import Avatar from '../Components/ui/Avatar.vue';
import FlashMessage from '../Components/ui/FlashMessage.vue';

const props = defineProps({
    providerName: { type: String, default: 'Pati' },
    avatarSrc: { type: String, default: '' },
});

const page = usePage();
const currentPath = computed(() => page.url.split('?')[0]);

// Shared prop computed server-side; null once every onboarding step is done.
const onboarding = computed(() => page.props.onboarding ?? null);

// Collapsible, not dismissible: closing it lasts for the browser session and
// it returns on the next visit, because the steps it points to are still
// pending — sessionStorage is exactly that lifetime.
const BANNER_KEY = 'onboarding-banner-collapsed';
const bannerCollapsed = ref(sessionStorage.getItem(BANNER_KEY) === '1');

function collapseBanner() {
    bannerCollapsed.value = true;
    sessionStorage.setItem(BANNER_KEY, '1');
}

const showBanner = computed(() =>
    onboarding.value && !bannerCollapsed.value && currentPath.value !== '/admin/inicio',
);

// Citas first and Clientes second, the Booksy order the owner asked for.
// Inicio left the bar when the agenda became the landing: the checklist is
// one tap away through the onboarding banner while steps remain.
const navItems = [
    { href: '/admin/citas', icon: CalendarDays, key: 'nav.appointments' },
    { href: '/admin/clientes', icon: Users, key: 'nav.clients' },
    { href: '/admin/servicios', icon: Sparkles, key: 'nav.services' },
    { href: '/admin/horario', icon: Clock, key: 'nav.schedule' },
    { href: '/admin/perfil', icon: User, key: 'nav.profile' },
];

function isActive(href) {
    return currentPath.value === href;
}
</script>

<template>
    <div class="mx-auto flex min-h-screen w-full max-w-[480px] flex-col bg-[var(--bg-canvas)]">
        <FlashMessage />

        <slot name="header">
            <header class="flex items-center justify-between border-b border-[var(--surface-mute)] bg-[var(--surface)] px-4 py-3">
                <div class="flex min-w-0 items-center gap-3">
                    <Link
                        v-if="currentPath !== '/admin/citas'"
                        href="/admin/citas"
                        :aria-label="$t('nav.appointments')"
                        class="-ml-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-full hover:bg-[var(--surface-mute)]"
                    >
                        <ArrowLeft :size="20" class="text-[var(--text-strong)]" />
                    </Link>
                    <Avatar v-else :src="avatarSrc" :name="providerName" :size="32" />
                    <span class="truncate text-[15px] font-semibold text-[var(--text-strong)]">{{ providerName }}</span>
                </div>
                <Link
                    href="/admin/ajustes"
                    :aria-label="$t('admin.settings')"
                    class="flex h-9 w-9 items-center justify-center rounded-full hover:bg-[var(--surface-mute)]"
                >
                    <Settings :size="19" class="text-[var(--text-mute)]" />
                </Link>
            </header>
        </slot>

        <div
            v-if="showBanner"
            class="flex items-center gap-2 border-b border-[var(--green-border)] bg-[var(--green-soft)] py-2.5 pl-4 pr-2"
        >
            <Link href="/admin/inicio" class="flex min-w-0 flex-1 items-center justify-between gap-3 hover:brightness-[0.98]">
                <span class="text-[13px] font-medium text-[var(--green-deep)]">
                    {{ $t('inicio.bannerText', onboarding.pending) }}
                </span>
                <span class="shrink-0 rounded-full bg-[var(--btn-bg)] px-3.5 py-1.5 text-[12px] font-semibold text-white">
                    {{ $t('inicio.bannerCta') }}
                </span>
            </Link>
            <button
                type="button"
                :aria-label="$t('common.close')"
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full hover:bg-[var(--green-border)]"
                @click="collapseBanner"
            >
                <X :size="15" class="text-[var(--green-deep)]" />
            </button>
        </div>

        <main class="flex-1 pb-4">
            <slot />
        </main>

        <nav class="grid grid-cols-5 border-t border-[var(--surface-mute)] bg-[var(--surface)] px-2 pb-3.5 pt-2">
            <Link
                v-for="item in navItems"
                :key="item.href"
                :href="item.href"
                class="flex flex-col items-center gap-1 py-0.5"
            >
                <component
                    :is="item.icon"
                    :size="21"
                    :stroke-width="isActive(item.href) ? 2.2 : 1.8"
                    :class="isActive(item.href) ? 'text-[var(--text-strong)]' : 'text-[var(--text-faint)]'"
                />
                <span
                    class="text-[10px]"
                    :class="isActive(item.href) ? 'font-semibold text-[var(--text-strong)]' : 'font-medium text-[var(--text-faint)]'"
                    >{{ $t(item.key) }}</span
                >
            </Link>
        </nav>
    </div>
</template>
