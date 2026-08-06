<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Check, ChevronRight, CalendarDays, ExternalLink, MessageCircle } from '@lucide/vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

const props = defineProps({
    providerName: { type: String, required: true },
    avatarPhoto: { type: String, default: '' },
    publicUrl: { type: String, required: true },
    checklist: { type: Object, required: true },
    // { profileComplete, hasActiveServices, whatsappConnected, published, hasAppointments }
    summary: { type: Object, required: true },
    // { todayCount, pendingCount }
});

// "Reviewed the schedule" has no server-side signal (the schedule always
// exists, with defaults) — a local mark set by the Horario page is the only
// honest way to know the provider actually looked at it.
const scheduleReviewed = ref(localStorage.getItem('glow:scheduleReviewed') === '1');

const steps = computed(() => [
    { key: 'account', done: true, titleKey: 'inicio.stepAccount' },
    {
        key: 'profile',
        done: props.checklist.profileComplete,
        titleKey: 'inicio.stepProfile',
        hintKey: 'inicio.stepProfileHint',
        href: '/admin/perfil',
    },
    {
        key: 'services',
        done: props.checklist.hasActiveServices,
        titleKey: 'inicio.stepServices',
        hintKey: 'inicio.stepServicesHint',
        href: '/admin/servicios',
    },
    {
        key: 'schedule',
        done: scheduleReviewed.value,
        titleKey: 'inicio.stepSchedule',
        hintKey: 'inicio.stepScheduleHint',
        href: '/admin/horario',
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
        href: '/admin/perfil',
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

const publicPath = computed(() => props.publicUrl.replace(/^https?:\/\//, ''));
</script>

<template>
    <AdminLayout :provider-name="providerName" :avatar-src="avatarPhoto">
        <div class="flex flex-col gap-4 p-5 pb-7">
            <div class="pt-1">
                <h1 class="text-[26px] font-extrabold leading-tight tracking-tight text-[var(--text-strong)]">
                    {{ allDone ? $t('inicio.readyTitle') : $t('inicio.welcomeTitle') }}
                </h1>
                <p class="mt-1.5 text-sm font-medium leading-relaxed text-[var(--text-mute)]">
                    {{ allDone ? $t('inicio.readySubtitle') : $t('inicio.welcomeSubtitle') }}
                </p>
            </div>

            <div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-[var(--surface-mute)]">
                    <div
                        class="h-full rounded-full bg-[var(--btn-green)] transition-all duration-500"
                        :style="{ width: `${progressPercent}%` }"
                    />
                </div>
                <div class="mt-1.5 text-[11px] font-bold text-[var(--text-faint)]">
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
                            :class="step.done ? 'font-semibold text-[var(--text-faint)] line-through decoration-1' : 'font-extrabold text-[var(--text-strong)]'"
                            >{{ $t(step.titleKey) }}</span
                        >
                        <span v-if="step.hintKey && !step.done" class="mt-0.5 block text-[11px] font-semibold text-[var(--text-faint)]">{{
                            $t(step.hintKey)
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
                            <span class="text-sm font-extrabold" :class="botState === 'blocked' ? 'text-[var(--amber-text)]' : 'text-[var(--text-strong)]'">
                                {{ $t('inicio.botTitle') }}
                            </span>
                            <span
                                v-if="botState === 'active'"
                                class="inline-flex items-center gap-1 rounded-full bg-[var(--green-border)] px-2 py-0.5 text-[9px] font-extrabold uppercase tracking-wide text-[var(--green-deep)]"
                            >
                                <span class="h-1.5 w-1.5 rounded-full bg-[var(--green-text)]" />
                                {{ $t('inicio.botActive') }}
                            </span>
                        </div>
                        <p
                            class="mt-0.5 text-[11px] font-semibold leading-relaxed"
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
                        <div class="text-sm font-extrabold text-[var(--text-strong)]">{{ $t('inicio.todayTitle') }}</div>
                        <div class="mt-0.5 text-xs font-semibold text-[var(--text-mute)]">
                            {{ $t('inicio.todayAppointments', summary.todayCount) }}
                            <template v-if="summary.pendingCount > 0">
                                · <span class="font-bold text-[#d97706]">{{ $t('inicio.todayPending', summary.pendingCount) }}</span>
                            </template>
                        </div>
                    </div>
                </div>
                <ChevronRight :size="16" class="text-[var(--text-faint)]" />
            </Link>

            <a
                v-if="checklist.published"
                :href="publicUrl"
                target="_blank"
                rel="noopener"
                class="flex items-center justify-between rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface-alt)] p-4 hover:bg-[var(--surface-mute)]"
            >
                <div class="min-w-0">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-[var(--text-faint)]">
                        {{ $t('inicio.publicPageTitle') }}
                    </div>
                    <div class="mt-0.5 truncate text-sm font-extrabold text-[var(--text-strong)]">{{ publicPath }}</div>
                </div>
                <span class="flex shrink-0 items-center gap-1.5 text-xs font-bold text-[var(--green-text)]">
                    {{ $t('inicio.publicPageView') }}
                    <ExternalLink :size="14" />
                </span>
            </a>
            <div
                v-else
                class="rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface-alt)] p-4"
            >
                <div class="text-[11px] font-bold uppercase tracking-wider text-[var(--text-faint)]">
                    {{ $t('inicio.publicPageTitle') }}
                </div>
                <div class="mt-0.5 text-xs font-semibold text-[var(--text-mute)]">{{ $t('inicio.publicPageHidden') }}</div>
            </div>
        </div>
    </AdminLayout>
</template>
