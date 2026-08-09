<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { CalendarDays, Plus } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Badge from '../../Components/ui/Badge.vue';
import AppointmentDetailSheet from '../../Components/admin/AppointmentDetailSheet.vue';
import CreateAppointmentSheet from '../../Components/admin/CreateAppointmentSheet.vue';
import ConfirmDialog from '../../Components/ui/ConfirmDialog.vue';
import WhatsAppPromptSheet from '../../Components/admin/WhatsAppPromptSheet.vue';
import { useFormat } from '../../composables/useFormat';
import { usePreferences } from '../../composables/usePreferences';

const props = defineProps({
    providerName: { type: String, default: 'Pati' },
    appointments: { type: Array, required: true },
    // [{ id, clientName, clientPhone, clientPhoneDigits, service, provider,
    //    durationMinutes, price, status, startsAt (UTC ISO string) }]
    services: { type: Array, default: () => [] },
    // [{ id, name, durationMinutes, price }] — for the create sheet.
});

const { t } = useI18n();
const { formatDuration, formatTime, formatDayLabel, formatDateTimeLabel } = useFormat();
const { whatsappPrompt, locale } = usePreferences();

const tabs = [
    { value: 'agenda', key: 'admin.tabAgenda' },
    { value: 'pending', key: 'admin.tabPending' },
    { value: 'closed', key: 'admin.tabClosed' },
    { value: 'cancelled', key: 'admin.tabCancelled' },
];

const activeTab = ref('agenda');

// The agenda's day strip: today plus the next 13 days, tappable. Two weeks
// covers how far ahead a walk-in salon actually books by hand; anything
// further keeps living on the public page.
const selectedDate = ref(new Date());
const selectedKey = computed(() => selectedDate.value.toDateString());

const stripDays = computed(() => {
    const jsLocale = locale.value === 'es' ? 'es-ES' : 'en-US';

    return Array.from({ length: 14 }, (_, i) => {
        const date = new Date();
        date.setDate(date.getDate() + i);

        return {
            date,
            key: date.toDateString(),
            dow: date.toLocaleDateString(jsLocale, { weekday: 'short' }).replace('.', ''),
            num: date.getDate(),
            isToday: i === 0,
        };
    });
});

// startsAt is UTC; `new Date(...)` renders it in the viewer's local time,
// which is what dateLabel/timeLabel/dateKey should reflect.
const enrichedAppointments = computed(() =>
    props.appointments.map((appt) => {
        const date = new Date(appt.startsAt);
        return {
            ...appt,
            duration: formatDuration(appt.durationMinutes),
            isToday: date.toDateString() === new Date().toDateString(),
            dateKey: date.toDateString(),
            dateLabel: formatDateTimeLabel(date),
            timeLabel: formatTime(date.getHours(), date.getMinutes()),
        };
    }),
);

const dayGroupLabel = computed(() => formatDayLabel(selectedDate.value));

const counts = computed(() => ({
    agenda: enrichedAppointments.value.filter((a) => a.isToday && a.status !== 'cancelled').length,
    pending: enrichedAppointments.value.filter((a) => a.status === 'pending').length,
    closed: enrichedAppointments.value.filter((a) => a.status === 'closed').length,
    cancelled: enrichedAppointments.value.filter((a) => a.status === 'cancelled').length,
}));

// The day view shows everything that still occupies (or occupied) the day;
// cancelled ones only clutter it and keep their own tab.
const filtered = computed(() => {
    if (activeTab.value === 'agenda') {
        return enrichedAppointments.value
            .filter((a) => a.dateKey === selectedKey.value && a.status !== 'cancelled')
            .sort((a, b) => new Date(a.startsAt) - new Date(b.startsAt));
    }
    return enrichedAppointments.value.filter((a) => a.status === activeTab.value);
});

const createOpen = ref(false);

// A phone was captured → offer to notify her by WhatsApp right away, same
// flow as confirming a pending appointment. Without a phone the prompt
// guard drops it silently.
function handleCreated(created) {
    openWaPrompt({
        clientName: created.clientName,
        clientPhone: created.clientPhone,
        phoneDigits: created.phoneDigits,
        service: created.service,
        provider: props.providerName,
        startsAt: created.startsAt,
        variant: 'confirmed',
    });
}

const badgeVariant = { confirmed: 'confirmed', pending: 'pending', cancelled: 'cancelled', closed: 'closed' };
const statusKey = {
    confirmed: 'admin.statusConfirmed',
    pending: 'admin.statusPending',
    cancelled: 'admin.statusCancelled',
    closed: 'admin.statusClosed',
};

const waMessageKey = {
    confirmed: 'admin.waMessageConfirmed',
    rejected: 'admin.waMessageRejected',
    cancelled: 'admin.waMessageCancelled',
};

const cancelDialogCopy = {
    rejected: { title: 'admin.confirmRejectTitle', body: 'admin.confirmRejectBody' },
    cancelled: { title: 'admin.confirmCancelTitle', body: 'admin.confirmCancelBody' },
};

const selectedId = ref(null);
const sheetOpen = ref(false);
const detailProcessing = ref(false);

const selected = computed(() => enrichedAppointments.value.find((a) => a.id === selectedId.value) ?? null);

function openDetail(appointment) {
    selectedId.value = appointment.id;
    sheetOpen.value = true;
}

// The prior status is what distinguishes a rejection from a cancellation,
// and the action about to run destroys it — so this must be captured before
// dispatch, never read back from the (by-then-mutated) props afterward.
function buildWaPrompt(appointment, variant) {
    return {
        clientName: appointment.clientName,
        clientPhone: appointment.clientPhone,
        phoneDigits: appointment.clientPhoneDigits,
        service: appointment.service,
        provider: appointment.provider,
        startsAt: appointment.startsAt,
        variant,
    };
}

const waPrompt = ref(null);
const waPromptOpen = ref(false);

function openWaPrompt(prompt) {
    if (whatsappPrompt.value === 'never') return;
    if (!prompt || !/^\d{10}$/.test(prompt.phoneDigits ?? '')) return;
    waPrompt.value = prompt;
    waPromptOpen.value = true;
}

const waMessage = computed(() => {
    const p = waPrompt.value;
    if (!p) return '';
    return t(waMessageKey[p.variant], {
        client: p.clientName,
        provider: p.provider,
        service: p.service,
        date: formatDateTimeLabel(new Date(p.startsAt)),
    });
});

function confirmAppointment() {
    if (!selectedId.value) return;
    const prompt = buildWaPrompt(selected.value, 'confirmed');
    detailProcessing.value = true;
    // preserveState: true is now structural, not just a nicety — it's what
    // keeps `prompt` (captured above) meaningful once onSuccess fires.
    router.patch(`/admin/citas/${selectedId.value}/confirmar`, {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            sheetOpen.value = false;
            openWaPrompt(prompt);
        },
        onFinish: () => {
            detailProcessing.value = false;
        },
    });
}

const cancelConfirmOpen = ref(false);
const cancelProcessing = ref(false);
const pendingCancel = ref(null);

// The detail sheet's `reject` (pending) and `cancel` (confirmed) both map to
// the same server transition. Close the detail sheet first, then open the
// confirmation dialog — stacking BottomSheets breaks their shared scroll lock.
function askCancel() {
    if (!selectedId.value) return;
    const variant = selected.value.status === 'pending' ? 'rejected' : 'cancelled';
    pendingCancel.value = { id: selectedId.value, prompt: buildWaPrompt(selected.value, variant) };
    sheetOpen.value = false;
    cancelConfirmOpen.value = true;
}

const cancelDialogTitle = computed(() =>
    t(cancelDialogCopy[pendingCancel.value?.prompt.variant ?? 'cancelled'].title),
);
const cancelDialogBody = computed(() =>
    t(cancelDialogCopy[pendingCancel.value?.prompt.variant ?? 'cancelled'].body),
);

function confirmCancel() {
    if (!pendingCancel.value) return;
    const pending = pendingCancel.value;
    cancelProcessing.value = true;
    router.patch(`/admin/citas/${pending.id}/cancelar`, {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            openWaPrompt(pending.prompt);
        },
        onFinish: () => {
            cancelProcessing.value = false;
            cancelConfirmOpen.value = false;
            pendingCancel.value = null;
        },
    });
}
</script>

<template>
    <AdminLayout :provider-name="providerName">
        <div class="px-4 pt-4">
            <h1 class="px-1 pb-3 text-[22px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                {{ $t('admin.appointmentsTitle') }}
            </h1>
            <div class="grid grid-cols-4 gap-1 rounded-xl bg-[var(--surface-mute)] p-1">
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    type="button"
                    class="flex flex-col items-center gap-0.5 rounded-lg py-2"
                    :class="activeTab === tab.value && 'bg-[var(--surface)] shadow-[0_1px_2px_rgba(0,0,0,0.06)]'"
                    @click="activeTab = tab.value"
                >
                    <span
                        class="text-[12px] font-medium"
                        :class="activeTab === tab.value ? 'font-bold text-[var(--text-strong)]' : 'text-[var(--text-mute)]'"
                        >{{ $t(tab.key) }}</span
                    >
                    <span
                        class="text-[9px] font-bold"
                        :class="tab.value === 'pending' ? 'text-[#d97706]' : tab.value === 'agenda' ? 'text-[var(--green-text)]' : 'text-[var(--text-faint)]'"
                        >{{ counts[tab.value] }}</span
                    >
                </button>
            </div>

            <div v-if="activeTab === 'agenda'" class="-mx-4 mt-3 flex gap-1.5 overflow-x-auto px-4 pb-1">
                <button
                    v-for="day in stripDays"
                    :key="day.key"
                    type="button"
                    class="flex w-11 shrink-0 flex-col items-center gap-0.5 rounded-2xl py-2 transition-colors"
                    :class="selectedKey === day.key
                        ? 'bg-[var(--chip-bg)] text-[var(--chip-fg)]'
                        : 'text-[var(--text-mute)] hover:bg-[var(--surface-mute)]'"
                    @click="selectedDate = day.date"
                >
                    <span class="text-[10px] font-medium uppercase">{{ day.dow }}</span>
                    <span class="text-[15px] font-bold" :class="selectedKey !== day.key && 'text-[var(--text-strong)]'">{{ day.num }}</span>
                    <span
                        class="h-1 w-1 rounded-full"
                        :class="day.isToday ? (selectedKey === day.key ? 'bg-[var(--chip-fg)]' : 'bg-[var(--text-strong)]') : 'bg-transparent'"
                    />
                </button>
            </div>
        </div>

        <div class="flex flex-col gap-3 p-4">
            <div v-if="activeTab === 'agenda' && filtered.length" class="text-[13px] font-medium capitalize text-[var(--text-mute)]">
                {{ dayGroupLabel }}
            </div>

            <template v-if="activeTab === 'agenda'">
                <div v-if="filtered.length === 0" class="flex flex-col items-center px-8 pb-6 pt-10 text-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-[var(--surface-mute)]">
                        <CalendarDays :size="24" class="text-[var(--text-faint)]" />
                    </div>
                    <p class="mt-4 text-base font-bold text-[var(--text-strong)]">{{ $t('admin.dayEmpty') }}</p>
                    <p class="mt-1 text-[13px] font-normal text-[var(--text-mute)]">{{ $t('admin.dayEmptyHint') }}</p>
                </div>
                <button
                    v-for="appt in filtered"
                    :key="appt.id"
                    type="button"
                    class="flex items-center justify-between rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4 text-left shadow-[0_1px_2px_rgba(0,0,0,0.04)] hover:border-[var(--border-strong)]"
                    @click="openDetail(appt)"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-xl bg-[var(--chip-bg)] text-[var(--chip-fg)]">
                            <span class="text-[14px] font-semibold leading-none">{{ appt.timeLabel.split(' ')[0] }}</span>
                            <span class="text-[8px] font-bold opacity-70">{{ appt.timeLabel.split(' ')[1] || '' }}</span>
                        </div>
                        <div>
                            <div class="text-[15px] font-semibold text-[var(--text-strong)]">{{ appt.clientName }}</div>
                            <div class="mt-0.5 text-[13px] font-normal text-[var(--text-mute)]">
                                {{ appt.service }} · {{ appt.duration }} · ${{ appt.price }}
                            </div>
                        </div>
                    </div>
                    <Badge :variant="badgeVariant[appt.status]">{{ $t(statusKey[appt.status]) }}</Badge>
                </button>
            </template>

            <template v-else>
                <button
                    v-for="appt in filtered"
                    :key="appt.id"
                    type="button"
                    class="rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4 text-left shadow-[0_1px_2px_rgba(0,0,0,0.04)] hover:border-[var(--border-strong)]"
                    @click="openDetail(appt)"
                >
                    <div class="flex items-start justify-between">
                        <div class="text-[15px] font-bold text-[var(--text-strong)]">{{ appt.clientName }}</div>
                        <Badge :variant="badgeVariant[appt.status]" uppercase>{{ $t(statusKey[appt.status]) }}</Badge>
                    </div>
                    <div class="mt-1.5 text-[13px] font-normal text-[var(--text-mute)]">{{ appt.service }} · {{ appt.provider }}</div>
                    <div class="mt-0.5 text-[13px] font-normal text-[var(--text-faint)]">
                        {{ appt.dateLabel }} · {{ appt.duration }} · ${{ appt.price }}
                    </div>
                </button>
                <p v-if="filtered.length === 0" class="py-6 text-center text-sm font-medium text-[var(--text-mute)]">
                    {{ $t('admin.noAppointments') }}
                </p>
                <p v-else class="mt-1 text-center text-[12px] font-normal text-[var(--text-faint)]">
                    {{ $t('admin.tapForDetail') }}
                </p>
            </template>
        </div>

        <!-- Fixed on every viewport; on wide screens the right offset pins it
             to the centered 480px column's edge (same pattern as Servicios). -->
        <button
            v-if="activeTab === 'agenda' && services.length > 0"
            type="button"
            :aria-label="$t('admin.addAppointment')"
            class="fixed bottom-24 right-4 z-20 flex h-13 w-13 items-center justify-center rounded-full bg-[var(--btn-bg)] shadow-[0_8px_20px_rgba(0,0,0,0.25)] hover:bg-[var(--btn-hover)] sm:right-[calc(50vw-224px)]"
            @click="createOpen = true"
        >
            <Plus :size="22" class="text-white" />
        </button>

        <CreateAppointmentSheet
            v-model="createOpen"
            :services="services"
            :date="selectedDate"
            @created="handleCreated"
        />

        <AppointmentDetailSheet
            v-model="sheetOpen"
            :appointment="selected"
            :processing="detailProcessing"
            @confirm="confirmAppointment"
            @reject="askCancel"
            @cancel="askCancel"
        />

        <ConfirmDialog
            v-model="cancelConfirmOpen"
            :title="cancelDialogTitle"
            :body="cancelDialogBody"
            :confirm-label="$t('admin.cancelAppointment')"
            :processing="cancelProcessing"
            variant="danger"
            @confirm="confirmCancel"
        />

        <WhatsAppPromptSheet
            v-model="waPromptOpen"
            :client-name="waPrompt?.clientName"
            :client-phone="waPrompt?.clientPhone"
            :phone-digits="waPrompt?.phoneDigits"
            :message="waMessage"
        />
    </AdminLayout>
</template>
