<script setup>
import { ref, computed } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Badge from '../../Components/ui/Badge.vue';
import AppointmentDetailSheet from '../../Components/admin/AppointmentDetailSheet.vue';

const props = defineProps({
    providerName: { type: String, default: 'Pati' },
    appointments: { type: Array, required: true },
    // [{ id, clientName, clientPhone, service, provider, duration, price, status,
    //    isToday, dateLabel, dateGroupLabel, timeHour, timeMinute, timeLabel }]
});

const tabs = [
    { value: 'today', key: 'admin.tabToday' },
    { value: 'pending', key: 'admin.tabPending' },
    { value: 'closed', key: 'admin.tabClosed' },
    { value: 'cancelled', key: 'admin.tabCancelled' },
];

const activeTab = ref('today');

const counts = computed(() => ({
    today: props.appointments.filter((a) => a.isToday).length,
    pending: props.appointments.filter((a) => a.status === 'pending').length,
    closed: props.appointments.filter((a) => a.status === 'closed').length,
    cancelled: props.appointments.filter((a) => a.status === 'cancelled').length,
}));

const filtered = computed(() => {
    if (activeTab.value === 'today') return props.appointments.filter((a) => a.isToday);
    return props.appointments.filter((a) => a.status === activeTab.value);
});

const badgeVariant = { confirmed: 'confirmed', pending: 'pending', cancelled: 'cancelled', closed: 'closed' };
const statusKey = {
    confirmed: 'admin.statusConfirmed',
    pending: 'admin.statusPending',
    cancelled: 'admin.statusCancelled',
    closed: 'admin.statusClosed',
};

const selected = ref(null);
const sheetOpen = ref(false);

function openDetail(appointment) {
    selected.value = appointment;
    sheetOpen.value = true;
}

function updateStatus(status) {
    if (!selected.value) return;
    const target = props.appointments.find((a) => a.id === selected.value.id);
    if (target) target.status = status;
    sheetOpen.value = false;
}
</script>

<template>
    <AdminLayout :provider-name="providerName">
        <div class="px-4 pt-3">
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
                        class="text-[11px] font-bold"
                        :class="activeTab === tab.value ? 'font-extrabold text-[var(--text-strong)]' : 'text-[var(--text-mute)]'"
                        >{{ $t(tab.key) }}</span
                    >
                    <span
                        class="text-[9px] font-extrabold"
                        :class="tab.value === 'pending' ? 'text-[#d97706]' : tab.value === 'today' ? 'text-[var(--green-text)]' : 'text-[var(--text-faint)]'"
                        >{{ counts[tab.value] }}</span
                    >
                </button>
            </div>
        </div>

        <div class="flex flex-col gap-3 p-4">
            <div v-if="activeTab === 'today' && filtered.length" class="text-[11px] font-bold uppercase tracking-wider text-[var(--text-faint)]">
                {{ filtered[0].dateGroupLabel }}
            </div>

            <template v-if="activeTab === 'today'">
                <button
                    v-for="appt in filtered"
                    :key="appt.id"
                    type="button"
                    class="flex items-center justify-between rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4 text-left shadow-[0_1px_2px_rgba(0,0,0,0.04)] hover:border-[var(--border-strong)]"
                    @click="openDetail(appt)"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-xl bg-[var(--chip-bg)] text-[var(--chip-fg)]">
                            <span class="text-[13px] font-extrabold leading-none">{{ appt.timeLabel.split(' ')[0] }}</span>
                            <span class="text-[8px] font-extrabold opacity-70">{{ appt.timeLabel.split(' ')[1] || '' }}</span>
                        </div>
                        <div>
                            <div class="text-sm font-extrabold text-[var(--text-strong)]">{{ appt.clientName }}</div>
                            <div class="mt-0.5 text-xs font-semibold text-[var(--text-mute)]">
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
                        <div class="text-[15px] font-extrabold text-[var(--text-strong)]">{{ appt.clientName }}</div>
                        <Badge :variant="badgeVariant[appt.status]" uppercase>{{ $t(statusKey[appt.status]) }}</Badge>
                    </div>
                    <div class="mt-1.5 text-xs font-semibold text-[var(--text-mute)]">{{ appt.service }} · {{ appt.provider }}</div>
                    <div class="mt-0.5 text-xs font-semibold text-[var(--text-faint)]">
                        {{ appt.dateLabel }} · {{ appt.duration }} · ${{ appt.price }}
                    </div>
                </button>
                <p v-if="filtered.length === 0" class="py-6 text-center text-sm font-medium text-[var(--text-mute)]">
                    {{ $t('admin.noAppointments') }}
                </p>
                <p v-else class="mt-1 text-center text-[11px] font-semibold text-[var(--text-faint)]">
                    {{ $t('admin.tapForDetail') }}
                </p>
            </template>
        </div>

        <AppointmentDetailSheet
            v-model="sheetOpen"
            :appointment="selected"
            @confirm="updateStatus('confirmed')"
            @reject="updateStatus('cancelled')"
            @cancel="updateStatus('cancelled')"
        />
    </AdminLayout>
</template>
