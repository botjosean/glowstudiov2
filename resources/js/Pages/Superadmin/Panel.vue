<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ShieldCheck, Trash2, LogOut, RefreshCw } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import ConfirmDialog from '../../Components/ui/ConfirmDialog.vue';
import FlashMessage from '../../Components/ui/FlashMessage.vue';
import Badge from '../../Components/ui/Badge.vue';
import { useFormat } from '../../composables/useFormat';

const props = defineProps({
    accounts: { type: Array, required: true },
    // [{ id, name, username, email, phone, providerName, published, servicesCount,
    //    appointmentsCount, protected, protectedReason }]
    appointments: { type: Array, required: true },
    // [{ id, clientName, clientPhone, service, provider, status, startsAt }]
});

const { t } = useI18n();
const { formatDateTimeLabel } = useFormat();

const protectedAccounts = computed(() => props.accounts.filter((account) => account.protected));
const deletableAccounts = computed(() => props.accounts.filter((account) => !account.protected));

const wipeConfirmOpen = ref(false);
const wipeProcessing = ref(false);

function confirmWipe() {
    wipeProcessing.value = true;
    router.post('/admin-general/limpiar', {}, {
        preserveScroll: true,
        onFinish: () => {
            wipeProcessing.value = false;
            wipeConfirmOpen.value = false;
        },
    });
}

const appointmentToDelete = ref(null);
const deleteProcessing = ref(false);

function confirmDeleteAppointment() {
    if (!appointmentToDelete.value) return;
    deleteProcessing.value = true;
    router.delete(`/admin-general/citas/${appointmentToDelete.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deleteProcessing.value = false;
            appointmentToDelete.value = null;
        },
    });
}

const statusVariant = { confirmed: 'confirmed', pending: 'pending', cancelled: 'cancelled', closed: 'closed' };
const statusKey = {
    confirmed: 'admin.statusConfirmed',
    pending: 'admin.statusPending',
    cancelled: 'admin.statusCancelled',
    closed: 'admin.statusClosed',
};
</script>

<template>
    <div class="mx-auto flex min-h-screen w-full max-w-[560px] flex-col bg-[var(--bg-canvas)] min-[700px]:max-w-[820px] lg:max-w-[1024px]">
        <FlashMessage />

        <header class="flex items-center justify-between border-b border-[var(--surface-mute)] bg-[var(--surface)] px-5 py-3">
            <span class="text-[15px] font-semibold text-[var(--text-strong)]">{{ $t('superadmin.title') }}</span>
            <button
                type="button"
                class="flex items-center gap-1.5 text-[13px] font-medium text-[var(--text-mute)] hover:text-[var(--text-strong)]"
                @click="router.post('/logout')"
            >
                <LogOut :size="14" />
                {{ $t('admin.logOut') }}
            </button>
        </header>

        <main class="flex flex-1 flex-col gap-6 p-5 pb-10">
            <p class="text-[13px] font-normal leading-relaxed text-[var(--text-mute)]">
                {{ $t('superadmin.subtitle') }}
            </p>

            <section>
                <h2 class="mb-2 flex items-center gap-1.5 px-1 text-[13px] font-medium text-[var(--text-mute)]">
                    <ShieldCheck :size="15" class="text-[var(--green-text)]" />
                    {{ $t('superadmin.protectedTitle') }}
                </h2>
                <div class="overflow-hidden rounded-2xl border border-[var(--green-border)] bg-[var(--green-soft)]">
                    <div
                        v-for="(account, index) in protectedAccounts"
                        :key="account.id"
                        class="px-4 py-3"
                        :class="index > 0 && 'border-t border-[var(--green-border)]'"
                    >
                        <div class="text-[14px] font-semibold text-[var(--green-deep)]">
                            {{ account.providerName ?? account.name }}
                            <span class="font-normal opacity-75">· @{{ account.username }}</span>
                        </div>
                        <div class="mt-0.5 text-[12px] font-normal text-[var(--green-text-strong)]">
                            {{ account.protectedReason === 'whatsapp' ? $t('superadmin.protectedWhatsapp') : $t('superadmin.protectedSuperadmin') }}
                            <template v-if="account.providerName">
                                · {{ $t('superadmin.servicesCount', account.servicesCount) }}
                                · {{ $t('superadmin.appointmentsCount', account.appointmentsCount) }}
                            </template>
                        </div>
                    </div>
                </div>
            </section>

            <section>
                <h2 class="mb-2 px-1 text-[13px] font-medium text-[var(--text-mute)]">
                    {{ $t('superadmin.deletableTitle') }}
                </h2>
                <p
                    v-if="deletableAccounts.length === 0"
                    class="rounded-2xl border border-[var(--border-strong)] bg-[var(--surface)] px-4 py-5 text-center text-[13px] font-normal text-[var(--text-mute)]"
                >
                    {{ $t('superadmin.deletableEmpty') }}
                </p>
                <div v-else class="overflow-hidden rounded-2xl border border-[var(--border-strong)] bg-[var(--surface)]">
                    <div
                        v-for="(account, index) in deletableAccounts"
                        :key="account.id"
                        class="px-4 py-3"
                        :class="index > 0 && 'border-t border-[var(--surface-mute)]'"
                    >
                        <div class="text-[14px] font-semibold text-[var(--text-strong)]">
                            {{ account.providerName ?? account.name }}
                            <span class="font-normal text-[var(--text-mute)]">· @{{ account.username }}</span>
                        </div>
                        <div class="mt-0.5 text-[12px] font-normal text-[var(--text-mute)]">
                            {{ account.email }}<template v-if="account.phone"> · {{ account.phone }}</template>
                            · {{ $t('superadmin.servicesCount', account.servicesCount) }}
                            · {{ $t('superadmin.appointmentsCount', account.appointmentsCount) }}
                        </div>
                    </div>
                </div>

                <button
                    v-if="deletableAccounts.length > 0"
                    type="button"
                    class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl bg-[var(--danger)] py-3.5 text-[15px] font-semibold text-white hover:opacity-90"
                    @click="wipeConfirmOpen = true"
                >
                    <Trash2 :size="16" />
                    {{ $t('superadmin.wipeCta', deletableAccounts.length) }}
                </button>
            </section>

            <section>
                <h2 class="mb-2 px-1 text-[13px] font-medium text-[var(--text-mute)]">
                    {{ $t('superadmin.appointmentsTitle', appointments.length) }}
                </h2>
                <p
                    v-if="appointments.length === 0"
                    class="rounded-2xl border border-[var(--border-strong)] bg-[var(--surface)] px-4 py-5 text-center text-[13px] font-normal text-[var(--text-mute)]"
                >
                    {{ $t('superadmin.appointmentsEmpty') }}
                </p>
                <div v-else class="overflow-hidden rounded-2xl border border-[var(--border-strong)] bg-[var(--surface)]">
                    <div
                        v-for="(appointment, index) in appointments"
                        :key="appointment.id"
                        class="flex items-center gap-3 px-4 py-3"
                        :class="index > 0 && 'border-t border-[var(--surface-mute)]'"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="truncate text-[14px] font-semibold text-[var(--text-strong)]">{{
                                    appointment.clientName
                                }}</span>
                                <Badge :variant="statusVariant[appointment.status]">{{ $t(statusKey[appointment.status]) }}</Badge>
                            </div>
                            <div class="mt-0.5 truncate text-[12px] font-normal text-[var(--text-mute)]">
                                {{ appointment.service }} · {{ appointment.provider }} ·
                                {{ formatDateTimeLabel(new Date(appointment.startsAt)) }}
                            </div>
                        </div>
                        <button
                            type="button"
                            :aria-label="$t('superadmin.deleteAppointment')"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full hover:bg-[var(--danger-hover)]"
                            @click="appointmentToDelete = appointment"
                        >
                            <Trash2 :size="15" class="text-[var(--danger)]" />
                        </button>
                    </div>
                </div>
            </section>

            <button
                type="button"
                class="flex items-center justify-center gap-1.5 text-[13px] font-medium text-[var(--text-mute)] hover:text-[var(--text-strong)]"
                @click="router.reload()"
            >
                <RefreshCw :size="13" />
                {{ $t('superadmin.refresh') }}
            </button>
        </main>

        <ConfirmDialog
            v-model="wipeConfirmOpen"
            :title="$t('superadmin.wipeConfirmTitle')"
            :body="$t('superadmin.wipeConfirmBody', deletableAccounts.length)"
            :detail="$t('superadmin.wipeConfirmDetail')"
            :confirm-label="$t('superadmin.wipeConfirmCta')"
            :processing="wipeProcessing"
            variant="danger"
            @confirm="confirmWipe"
        />

        <ConfirmDialog
            :model-value="appointmentToDelete !== null"
            :title="$t('superadmin.confirmDeleteApptTitle')"
            :body="appointmentToDelete ? `${appointmentToDelete.clientName} — ${appointmentToDelete.service} (${appointmentToDelete.provider})` : ''"
            :confirm-label="$t('superadmin.deleteAppointment')"
            :processing="deleteProcessing"
            variant="danger"
            @update:model-value="appointmentToDelete = null"
            @confirm="confirmDeleteAppointment"
        />
    </div>
</template>
