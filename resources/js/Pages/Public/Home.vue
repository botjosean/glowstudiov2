<script setup>
import { Link } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import StatTile from '../../Components/ui/StatTile.vue';
import { serviceIcons } from '../../icons';

defineProps({
    stats: { type: Object, required: true }, // { services, providers }
    services: { type: Array, required: true }, // [{ id, icon, name, duration, providersCount, price }]
});
</script>

<template>
    <PublicLayout>
        <div class="relative h-[180px] overflow-hidden bg-[#131a2a]">
            <img
                src="https://images.unsplash.com/photo-1503951914875-452162b0f3f1?auto=format&fit=crop&q=80&w=800"
                alt="Barbershop"
                class="h-full w-full object-cover opacity-72"
            />
            <div class="absolute inset-0 bg-gradient-to-b from-black/15 to-black/62" />
            <div class="absolute bottom-5 left-6 right-6">
                <div class="text-2xl font-bold leading-tight tracking-tight text-white">
                    {{ $t('home.heroTitle') }}
                </div>
                <div class="mt-1.5 text-[13px] font-medium text-white/82">{{ $t('home.heroSubtitle') }}</div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2.5 px-6 pt-5">
            <StatTile :value="stats.services" :label="$t('home.statServices')" />
            <StatTile :value="stats.providers" :label="$t('home.statProviders')" />
        </div>

        <div class="px-6 pt-6">
            <div class="mb-3.5 flex items-center justify-between">
                <span class="text-[13px] font-medium text-[var(--text-mute)]">{{
                    $t('home.registeredServices')
                }}</span>
                <Link href="/proveedores" class="text-[12px] font-medium text-[var(--green-text)]">{{
                    $t('home.seeAll')
                }}</Link>
            </div>
            <div class="flex flex-col gap-3">
                <div
                    v-for="service in services"
                    :key="service.id"
                    class="flex items-center justify-between rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[var(--chip-bg)]">
                            <component :is="serviceIcons[service.icon]" :size="20" class="text-[var(--chip-fg)]" />
                        </div>
                        <div>
                            <div class="text-[15px] font-semibold text-[var(--text-strong)]">{{ service.name }}</div>
                            <div class="mt-1 text-[13px] font-normal text-[var(--text-mute)]">
                                {{ service.duration }}
                                <span class="text-[var(--text-faint)]">•</span>
                                {{ service.providersCount }} {{ $t('home.providersLabel') }}
                            </div>
                        </div>
                    </div>
                    <div class="text-[15px] font-semibold text-[var(--text-strong)]">${{ service.price }}</div>
                </div>
            </div>
        </div>

        <div class="px-6 pt-7">
            <div class="flex flex-col gap-3.5 rounded-[18px] border-[1.5px] border-[var(--green-border)] bg-[var(--green-soft)] p-[18px]">
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[var(--btn-green)]">
                        <component :is="serviceIcons.sparkles" :size="20" class="text-white" />
                    </div>
                    <div>
                        <div class="text-[15px] font-bold tracking-tight text-[var(--green-deep)]">
                            {{ $t('home.offerTitle') }}
                        </div>
                        <div class="mt-0.5 text-[13px] font-normal leading-relaxed text-[var(--green-text-strong)]">
                            {{ $t('home.offerSubtitle') }}
                        </div>
                    </div>
                </div>
                <div class="flex gap-2.5">
                    <Link
                        href="/crear-cuenta"
                        class="flex-1 rounded-xl border-[1.5px] border-[var(--green-border)] bg-[var(--surface)] py-3 text-center text-[14px] font-semibold text-[var(--green-text-strong)] hover:bg-[var(--green-soft)]"
                        >{{ $t('menu.createAccount') }}</Link
                    >
                    <Link
                        href="/iniciar-sesion"
                        class="flex-1 rounded-xl bg-[var(--btn-green)] py-3 text-center text-[14px] font-semibold text-white hover:bg-[var(--btn-green-hover)]"
                        >{{ $t('menu.signIn') }}</Link
                    >
                </div>
            </div>
        </div>

        <div class="px-6 py-8 text-center">
            <span class="text-[13px] font-normal text-[var(--text-mute)]">
                {{ $t('home.browseCta') }}
                <span class="font-bold text-[var(--text-strong)]">{{ $t('home.browseCtaStrong') }}</span>
            </span>
        </div>
    </PublicLayout>
</template>
