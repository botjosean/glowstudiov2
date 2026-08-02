<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, Scissors, Clock, User, Settings } from '@lucide/vue';
import Avatar from '../Components/ui/Avatar.vue';

const props = defineProps({
    providerName: { type: String, default: 'Pati' },
    avatarSrc: { type: String, default: '' },
});

const page = usePage();
const currentPath = computed(() => page.url.split('?')[0]);

const navItems = [
    { href: '/admin/citas', icon: CalendarDays, key: 'nav.appointments' },
    { href: '/admin/servicios', icon: Scissors, key: 'nav.services' },
    { href: '/admin/horario', icon: Clock, key: 'nav.schedule' },
    { href: '/admin/perfil', icon: User, key: 'nav.profile' },
];

function isActive(href) {
    return currentPath.value === href;
}
</script>

<template>
    <div class="mx-auto flex min-h-screen w-full max-w-[480px] flex-col bg-[var(--bg-canvas)]">
        <slot name="header">
            <header class="flex items-center justify-between border-b border-[var(--surface-mute)] bg-[var(--surface)] p-4">
                <div class="flex items-center gap-2.5">
                    <Avatar :src="avatarSrc" :name="providerName" :size="36" ring />
                    <div>
                        <div class="text-sm font-extrabold tracking-tight text-[var(--text-strong)]">
                            {{ $t('admin.panelOf', { name: providerName }) }}
                        </div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-[var(--text-faint)]">
                            {{ $t('app.name') }} · {{ $t('admin.badge') }}
                        </div>
                    </div>
                </div>
                <Link
                    href="/admin/ajustes"
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--surface-mute)] hover:bg-[var(--border-strong)]"
                >
                    <Settings :size="16" class="text-[var(--text-mute)]" />
                </Link>
            </header>
        </slot>

        <main class="flex-1 pb-4">
            <slot />
        </main>

        <nav class="grid grid-cols-4 border-t border-[var(--surface-mute)] bg-[var(--surface-alt)] px-2 pb-3.5 pt-2.5">
            <Link
                v-for="item in navItems"
                :key="item.href"
                :href="item.href"
                class="flex flex-col items-center gap-1"
            >
                <component
                    :is="item.icon"
                    :size="20"
                    :class="isActive(item.href) ? 'text-[var(--text-strong)]' : 'text-[var(--text-faint)]'"
                />
                <span
                    class="text-[9px] font-extrabold"
                    :class="isActive(item.href) ? 'text-[var(--text-strong)]' : 'font-bold text-[var(--text-faint)]'"
                    >{{ $t(item.key) }}</span
                >
            </Link>
        </nav>
    </div>
</template>
