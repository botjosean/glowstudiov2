<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Settings, ArrowLeft, X } from '@lucide/vue';
import NavCalendar from '../Components/icons/NavCalendar.vue';
import NavClients from '../Components/icons/NavClients.vue';
import NavSales from '../Components/icons/NavSales.vue';
import NavStore from '../Components/icons/NavStore.vue';
import Avatar from '../Components/ui/Avatar.vue';
import FlashMessage from '../Components/ui/FlashMessage.vue';
import InstallPrompt from '../Components/ui/InstallPrompt.vue';

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

// The final Booksy-order bar the owner asked for: Citas · Clientas · Ventas ·
// Perfil. Servicios and Horario live on in Ajustes (and the agenda's own
// gear); Inicio through the onboarding banner while steps remain.
// Hand-drawn set after Booksy's: calendar, clients, receipt and — like
// Booksy — a storefront for Perfil, because the tab shows the business,
// not the person.
const navItems = [
    { href: '/admin/citas', icon: NavCalendar, key: 'nav.appointments' },
    { href: '/admin/clientes', icon: NavClients, key: 'nav.clients' },
    { href: '/admin/ventas', icon: NavSales, key: 'nav.sales' },
    { href: '/admin/perfil', icon: NavStore, key: 'nav.profile' },
];

function isActive(href) {
    return currentPath.value === href;
}
</script>

<template>
    <div class="mx-auto flex min-h-screen w-full max-w-[480px] flex-col bg-[var(--bg-canvas)]">
        <InstallPrompt />
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

        <!-- Booksy's black bar, always on screen: sticky so a long page
             scrolls under it, black in BOTH themes on purpose (it is the
             brand's anchor, not a surface), active item in gold. -->
        <nav class="sticky bottom-0 z-30 grid grid-cols-4 bg-[#101010] px-2 pb-3.5 pt-2.5">
            <Link
                v-for="item in navItems"
                :key="item.href"
                :href="item.href"
                class="flex flex-col items-center gap-1 py-0.5"
            >
                <component
                    :is="item.icon"
                    :size="22"
                    :class="isActive(item.href) ? 'text-[#e3c26d]' : 'text-[#9ca3af]'"
                />
                <span
                    class="text-[10px]"
                    :class="isActive(item.href) ? 'font-semibold text-[#e3c26d]' : 'font-medium text-[#9ca3af]'"
                    >{{ $t(item.key) }}</span
                >
            </Link>
        </nav>
    </div>
</template>
