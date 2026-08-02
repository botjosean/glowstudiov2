<script setup>
import { Link } from '@inertiajs/vue3';
import { MapPin } from '@lucide/vue';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import { serviceIcons } from '../../icons';

const props = defineProps({
    provider: { type: Object, required: true },
    // provider: { slug, name, bio, availableNow, bannerPhoto, avatarPhoto, location: { title, subtitle },
    //             gallery: [url], services: [{ id, icon, name, duration, price }] }
});
</script>

<template>
    <PublicLayout>
        <div class="relative">
            <div class="relative h-48 w-full overflow-hidden bg-[#131a2a]">
                <img :src="provider.bannerPhoto" :alt="`${provider.name} banner`" class="h-full w-full object-cover opacity-75" />
                <div class="absolute inset-0 bg-gradient-to-b from-black/10 via-transparent to-black/50" />
            </div>

            <div class="relative z-10 -mt-16 flex justify-center">
                <div class="h-32 w-32 rounded-full bg-[var(--surface)] p-1 shadow-[0_10px_25px_rgba(15,23,42,0.15)]">
                    <div class="box-border h-full w-full overflow-hidden rounded-full border-4 border-[#10b981] bg-[var(--surface-mute)]">
                        <img :src="provider.avatarPhoto" :alt="provider.name" class="h-full w-full object-cover" />
                    </div>
                </div>
            </div>

            <div class="px-6 pt-3 text-center">
                <div class="text-2xl font-extrabold tracking-tight text-[var(--text-strong)]">{{ provider.name }}</div>
                <div
                    v-if="provider.availableNow"
                    class="mt-2 inline-flex items-center gap-1.5 rounded-full border border-[var(--green-border)] bg-[var(--green-soft)] px-3 py-1 text-xs font-bold text-[var(--green-text)]"
                >
                    <span class="h-2 w-2 rounded-full bg-[#10b981]" />
                    {{ $t('profile.availableNow') }}
                </div>
                <p class="mx-auto mt-4 max-w-sm text-sm font-medium leading-relaxed text-[var(--text-body)]">
                    {{ provider.bio }}
                </p>
            </div>

            <div class="fixed left-1/2 top-[220px] z-20 w-full max-w-[480px] -translate-x-1/2 px-4">
                <div class="flex flex-col items-end gap-3">
                    <a
                        v-if="provider.social?.whatsapp"
                        :href="provider.social.whatsapp"
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-[#25D366] shadow-[0_4px_12px_rgba(37,211,102,0.35)]"
                        aria-label="WhatsApp"
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="#fff">
                            <path
                                d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.9 9.9 0 0 0 4.74 1.2h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.86 9.86 0 0 0 12.05 2m0 1.67a8.2 8.2 0 0 1 5.83 2.42 8.19 8.19 0 0 1 2.41 5.83c0 4.55-3.7 8.24-8.25 8.24a8.3 8.3 0 0 1-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.22 8.22 0 0 1-1.26-4.4c0-4.55 3.7-8.23 8.26-8.23m-4.57 4.7c-.16 0-.42.06-.64.3-.22.24-.85.83-.85 2.02s.87 2.35.99 2.51c.12.16 1.7 2.69 4.19 3.63 2.07.79 2.49.63 2.94.59.45-.04 1.45-.59 1.65-1.16.2-.57.2-1.06.14-1.16-.06-.1-.22-.16-.46-.28-.24-.12-1.45-.72-1.68-.8-.22-.08-.39-.12-.55.12-.16.24-.63.8-.77.96-.14.16-.28.18-.52.06-.24-.12-1.02-.38-1.94-1.2-.72-.64-1.2-1.44-1.34-1.68-.14-.24-.02-.37.1-.49.11-.11.24-.28.36-.42.12-.14.16-.24.24-.4.08-.16.04-.3-.02-.42-.06-.12-.55-1.36-.77-1.86-.2-.48-.4-.42-.55-.42h-.47Z"
                            />
                        </svg>
                    </a>
                    <a
                        v-if="provider.social?.instagram"
                        :href="provider.social.instagram"
                        class="flex h-10 w-10 items-center justify-center rounded-full shadow-[0_4px_12px_rgba(214,36,159,0.3)]"
                        style="background: radial-gradient(circle at 30% 110%, #fdf497, #fdf497 5%, #fd5949 45%, #d6249f 60%, #285aeb 90%)"
                        aria-label="Instagram"
                    >
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
                            <rect x="2" y="2" width="20" height="20" rx="5" />
                            <circle cx="12" cy="12" r="4" />
                            <circle cx="17.5" cy="6.5" r="1" fill="#fff" stroke="none" />
                        </svg>
                    </a>
                    <a
                        v-if="provider.social?.tiktok"
                        :href="provider.social.tiktok"
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-black shadow-[0_4px_12px_rgba(0,0,0,0.3)]"
                        aria-label="TikTok"
                    >
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="#fff">
                            <path
                                d="M16.6 5.82c-.9-1-1.42-2.28-1.44-3.62h-3.02v13.86c0 1.6-1.3 2.9-2.9 2.9a2.9 2.9 0 0 1-2.9-2.9 2.9 2.9 0 0 1 2.9-2.9c.28 0 .55.04.8.11V10.2c-.26-.03-.53-.05-.8-.05a5.95 5.95 0 0 0-5.95 5.95A5.95 5.95 0 0 0 9.24 22a5.95 5.95 0 0 0 5.95-5.95V9.13a8.55 8.55 0 0 0 4.98 1.6V7.7a5.65 5.65 0 0 1-3.57-1.88Z"
                            />
                        </svg>
                    </a>
                    <a
                        v-if="provider.social?.facebook"
                        :href="provider.social.facebook"
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-[#1877F2] shadow-[0_4px_12px_rgba(24,119,242,0.35)]"
                        aria-label="Facebook"
                    >
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="#fff">
                            <path
                                d="M13.5 21v-8.2h2.75l.41-3.19h-3.16V7.53c0-.92.26-1.55 1.58-1.55h1.68V3.14C15.98 3.06 15.1 3 14.06 3c-2.85 0-4.8 1.74-4.8 4.94v2.67H6.5v3.19h2.76V21h4.24Z"
                            />
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <div class="px-6 pt-4">
            <div class="flex items-start gap-3.5 rounded-2xl border border-[var(--loc-border)] bg-[var(--loc-bg)] p-4">
                <div class="shrink-0 rounded-xl bg-[var(--loc-chip)] p-2.5">
                    <MapPin :size="20" class="text-[var(--loc-text)]" />
                </div>
                <div>
                    <div class="text-sm font-extrabold text-[var(--loc-title)]">{{ provider.location.title }}</div>
                    <div class="mt-0.5 text-xs font-medium text-[var(--loc-text)]">{{ provider.location.subtitle }}</div>
                </div>
            </div>
        </div>

        <div class="px-6 pt-8">
            <div class="mb-3.5 flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-[var(--text-faint)]">{{
                    $t('profile.ourWork')
                }}</span>
                <span class="rounded-full bg-[var(--surface-mute)] px-2 py-0.5 text-[10px] font-bold text-[var(--text-mute)]">{{
                    $t('profile.tapToEnlarge')
                }}</span>
            </div>
            <div class="grid grid-cols-3 gap-2.5">
                <div
                    v-for="(photo, index) in provider.gallery"
                    :key="index"
                    class="aspect-square overflow-hidden rounded-2xl bg-[var(--surface-mute)]"
                >
                    <img :src="photo" :alt="`${provider.name} work ${index + 1}`" class="h-full w-full object-cover" />
                </div>
            </div>
        </div>

        <div class="px-6 pb-12 pt-8">
            <div class="mb-3.5 text-[11px] font-bold uppercase tracking-wider text-[var(--text-faint)]">
                {{ $t('profile.bookAppointment') }}
            </div>
            <div class="flex flex-col gap-3">
                <div
                    v-for="service in provider.services"
                    :key="service.id"
                    class="flex items-center justify-between rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[var(--chip-bg)]">
                            <component :is="serviceIcons[service.icon]" :size="20" class="text-[var(--chip-fg)]" />
                        </div>
                        <div>
                            <div class="text-sm font-extrabold text-[var(--text-strong)]">{{ service.name }}</div>
                            <div class="mt-1 flex items-center gap-1.5 text-xs font-medium text-[var(--text-mute)]">
                                {{ service.duration }}
                                <span class="text-[var(--text-faint)]">•</span>
                                <span class="font-bold text-[var(--text-strong)]">${{ service.price }}</span>
                            </div>
                        </div>
                    </div>
                    <Link
                        :href="`/reservar/${provider.slug}/${service.id}`"
                        class="rounded-xl bg-[var(--btn-bg)] px-4 py-2.5 text-xs font-bold text-white hover:bg-[var(--btn-hover)]"
                        >{{ $t('profile.book') }}</Link
                    >
                </div>
            </div>
        </div>

        <div class="border-t border-[var(--surface-mute)] bg-[var(--surface-alt)] px-4 py-6 text-center">
            <span class="text-xs font-bold tracking-wide text-[var(--text-faint)]">
                {{ $t('profile.footerCta') }}
                <span class="text-[var(--text-heading)]">{{ $t('app.name') }} 💈</span>
            </span>
        </div>
    </PublicLayout>
</template>
