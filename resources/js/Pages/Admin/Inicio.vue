<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Check, ChevronRight, CalendarDays, MessageCircle } from '@lucide/vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import PublicLinkCard from '../../Components/admin/PublicLinkCard.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    providerName: { type: String, required: true },
    avatarPhoto: { type: String, default: '' },
    publicUrl: { type: String, required: true },
    checklist: { type: Object, required: true },
    // { hasBusinessCategory, profileComplete, photoCount, photosNeeded,
    //   hasActiveServices, scheduleSaved, whatsappConnected, published,
    //   hasAppointments }
    summary: { type: Object, required: true },
    // { todayCount, pendingCount }
});

const { t } = useI18n();

const steps = computed(() => [
    { key: 'account', done: true, titleKey: 'inicio.stepAccount' },
    {
        key: 'profile',
        done: props.checklist.profileComplete,
        titleKey: 'inicio.stepProfile',
        hintKey: 'inicio.stepProfileHint',
        href: '/admin/negocio?desde=inicio',
    },
    {
        key: 'businessCategory',
        done: props.checklist.hasBusinessCategory,
        titleKey: 'inicio.stepBusinessCategory',
        hintKey: 'inicio.stepBusinessCategoryHint',
        href: '/admin/negocio?desde=inicio&abrir=categoria',
    },
    {
        // Its own step, not folded into "complete your profile": a half-filled
        // photo grid is the difference between a page that looks like a
        // business and one that looks abandoned, and it needs to be asked for
        // explicitly or nobody finishes it.
        key: 'photos',
        done: props.checklist.photoCount >= props.checklist.photosNeeded,
        titleKey: 'inicio.stepPhotos',
        hint: props.checklist.photoCount === 0
            ? t('inicio.stepPhotosHint', { total: props.checklist.photosNeeded })
            : t('inicio.stepPhotosMissing', { missing: props.checklist.photosNeeded - props.checklist.photoCount }),
        href: '/admin/perfil?desde=inicio&abrir=fotos',
    },
    {
        key: 'services',
        done: props.checklist.hasActiveServices,
        titleKey: 'inicio.stepServices',
        hintKey: 'inicio.stepServicesHint',
        href: '/admin/servicios?desde=inicio',
    },
    {
        key: 'schedule',
        done: props.checklist.scheduleSaved,
        titleKey: 'inicio.stepSchedule',
        hintKey: 'inicio.stepScheduleHint',
        href: '/admin/horario?desde=inicio',
    },
    {
        key: 'whatsapp',
        done: props.checklist.whatsappConnected,
        titleKey: 'inicio.stepWhatsapp',
        hintKey: 'inicio.stepWhatsappHint',
    },
    {
        key: 'publish',
        done: props.checklist.published,
        titleKey: 'inicio.stepPublish',
        hintKey: 'inicio.stepPublishHint',
        href: '/admin/negocio?desde=inicio&abrir=publicacion',
    },
    {
        key: 'booking',
        done: props.checklist.hasAppointments,
        titleKey: 'inicio.stepBooking',
        hintKey: 'inicio.stepBookingHint',
        external: props.checklist.published,
    },
]);

const doneCount = computed(() => steps.value.filter((step) => step.done).length);
const allDone = computed(() => doneCount.value === steps.value.length);
const progressPercent = computed(() => Math.round((doneCount.value / steps.value.length) * 100));

const botState = computed(() => {
    if (!props.checklist.whatsappConnected) return 'offline';
    if (!props.checklist.published || !props.checklist.hasActiveServices) return 'blocked';
    return 'active';
});
</script>

<template>
    <AdminLayout :provider-name="providerName" :avatar-src="avatarPhoto">
        <div class="flex flex-col gap-4 p-5 pb-7">
            <div class="pt-1">
                <h1 class="text-[26px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                    {{ allDone ? $t('inicio.readyTitle') : $t('inicio.welcomeTitle') }}
                </h1>
                <p class="mt-1.5 text-sm font-medium leading-relaxed text-[var(--text-mute)]">
                    {{ allDone ? $t('inicio.readySubtitle') : $t('inicio.welcomeSubtitle') }}
                </p>
            </div>

            <!--
                Once the checklist is all green it stops being the reason to
                open this page, and the link the provider actually sends to
                clients should be the first thing in reach. Until then the
                setup steps stay on top and the link waits at the bottom.
            -->
            <PublicLinkCard v-if="allDone" :url="publicUrl" :published="checklist.published" :provider-name="providerName" />

            <div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-[var(--surface-mute)]">
                    <div
                        class="h-full rounded-full bg-[var(--btn-green)] transition-all duration-500"
                        :style="{ width: `${progressPercent}%` }"
                    />
                </div>
                <div class="mt-1.5 text-[12px] font-medium text-[var(--text-faint)]">
                    {{ $t('inicio.progress', { done: doneCount, total: steps.length }) }}
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
                <component
                    :is="step.external ? 'a' : step.href ? Link : 'div'"
                    v-for="(step, index) in steps"
                    :key="step.key"
                    v-bind="step.external ? { href: publicUrl, target: '_blank', rel: 'noopener' } : step.href ? { href: step.href } : {}"
                    class="flex w-full items-center gap-3.5 p-4"
                    :class="[
                        index > 0 && 'border-t border-[var(--surface-mute)]',
                        (step.href || step.external) && 'hover:bg-[var(--surface-alt)]',
                    ]"
                >
                    <span
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full"
                        :class="step.done ? 'bg-[var(--btn-green)]' : 'border-2 border-[var(--border-strong)]'"
                    >
                        <Check v-if="step.done" :size="15" class="text-white" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span
                            class="block text-sm"
                            :class="step.done ? 'font-semibold text-[var(--text-mute)]' : 'font-bold text-[var(--text-strong)]'"
                            >{{ $t(step.titleKey) }}</span
                        >
                        <span v-if="(step.hint || step.hintKey) && !step.done" class="mt-0.5 block text-[12px] font-normal text-[var(--text-faint)]">{{
                            step.hint || $t(step.hintKey)
                        }}</span>
                    </span>
                    <ChevronRight
                        v-if="(step.href || step.external) && !step.done"
                        :size="16"
                        class="shrink-0 text-[var(--text-faint)]"
                    />
                </component>
            </div>

            <div
                class="rounded-2xl border p-4"
                :class="{
                    'border-[var(--green-border)] bg-[var(--green-soft)]': botState === 'active',
                    'border-[var(--amber-border)] bg-[var(--amber-soft)]': botState === 'blocked',
                    'border-[var(--surface-mute)] bg-[var(--surface)]': botState === 'offline',
                }"
            >
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
                        :class="botState === 'active' ? 'bg-[#25D366]' : 'bg-[var(--surface-mute)]'"
                    >
                        <MessageCircle :size="17" :class="botState === 'active' ? 'text-white' : 'text-[var(--text-mute)]'" />
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-[15px] font-semibold" :class="botState === 'blocked' ? 'text-[var(--amber-text)]' : 'text-[var(--text-strong)]'">
                                {{ $t('inicio.botTitle') }}
                            </span>
                            <span
                                v-if="botState === 'active'"
                                class="inline-flex items-center gap-1 rounded-full bg-[var(--green-border)] px-2 py-0.5 text-[11px] font-medium text-[var(--green-deep)]"
                            >
                                <span class="h-1.5 w-1.5 rounded-full bg-[var(--green-text)]" />
                                {{ $t('inicio.botActive') }}
                            </span>
                        </div>
                        <p
                            class="mt-0.5 text-[12px] font-normal leading-relaxed"
                            :class="{
                                'text-[var(--green-deep)]': botState === 'active',
                                'text-[var(--amber-text)]': botState === 'blocked',
                                'text-[var(--text-mute)]': botState === 'offline',
                            }"
                        >
                            <template v-if="botState === 'active'">{{ $t('inicio.botActiveHint') }}</template>
                            <template v-else-if="botState === 'blocked'">{{ $t('inicio.botBlocked') }} — {{ $t('inicio.botBlockedHint') }}</template>
                            <template v-else>{{ $t('inicio.botOffline') }} — {{ $t('inicio.botOfflineHint') }}</template>
                        </p>
                    </div>
                </div>
            </div>

            <Link
                href="/admin/citas"
                class="flex items-center justify-between rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)] hover:border-[var(--border-strong)]"
            >
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[var(--chip-bg)]">
                        <CalendarDays :size="19" class="text-[var(--chip-fg)]" />
                    </div>
                    <div>
                        <div class="text-[15px] font-semibold text-[var(--text-strong)]">{{ $t('inicio.todayTitle') }}</div>
                        <div class="mt-0.5 text-[13px] font-normal text-[var(--text-mute)]">
                            {{ $t('inicio.todayAppointments', summary.todayCount) }}
                            <template v-if="summary.pendingCount > 0">
                                · <span class="font-bold text-[var(--pending)]">{{ $t('inicio.todayPending', summary.pendingCount) }}</span>
                            </template>
                        </div>
                    </div>
                </div>
                <ChevronRight :size="16" class="text-[var(--text-faint)]" />
            </Link>

            <PublicLinkCard v-if="!allDone" :url="publicUrl" :published="checklist.published" :provider-name="providerName" />
        </div>
    </AdminLayout>
</template>
