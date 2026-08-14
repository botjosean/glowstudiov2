<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { ArrowLeft, CalendarPlus, MessageCircle, MessageSquare, Pencil, Phone, Trash2, X } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Badge from '../../Components/ui/Badge.vue';
import ClientFormSheet from '../../Components/admin/ClientFormSheet.vue';
import ConfirmDialog from '../../Components/ui/ConfirmDialog.vue';
import { useFormat } from '../../composables/useFormat';

/**
 * One client's card: her history on this provider (linked by phone, so it is
 * the same view the WhatsApp assistant has), plus what only a card can hold —
 * notes, tags, and the shortcuts to actually reach her.
 */
const props = defineProps({
    providerName: { type: String, default: 'Pati' },
    client: { type: Object, required: true },
    // { id, name, phone, phoneDigits, email, notes, tags }
    upcoming: { type: Array, required: true },
    past: { type: Array, required: true },
    // [{ id, service, price, durationMinutes, status, startsAt }]
    stats: { type: Object, required: true },
    // { upcoming, completed, cancelled }
});

const { t } = useI18n();
const { formatDuration, formatDateTimeLabel } = useFormat();

const hasPhone = computed(() => Boolean(props.client.phoneDigits));

// US numbers: the ten stored digits plus the country code the links need.
const e164 = computed(() => (hasPhone.value ? `1${props.client.phoneDigits}` : ''));

const waHref = computed(() => {
    const message = t('admin.clientWaMessage', { client: props.client.name.split(' ')[0], provider: props.providerName });
    return `https://wa.me/${e164.value}?text=${encodeURIComponent(message)}`;
});

const newAppointmentHref = computed(() => {
    const params = new URLSearchParams({ nombre: props.client.name });
    if (hasPhone.value) params.set('tel', props.client.phoneDigits);
    return `/admin/citas?${params.toString()}`;
});

function enrich(appointment) {
    return {
        ...appointment,
        label: formatDateTimeLabel(new Date(appointment.startsAt)),
        duration: formatDuration(appointment.durationMinutes),
    };
}

const upcomingRows = computed(() => props.upcoming.map(enrich));
const pastRows = computed(() => props.past.map(enrich));

const badgeVariant = { confirmed: 'confirmed', pending: 'pending', cancelled: 'cancelled', closed: 'closed' };
const statusKey = {
    confirmed: 'admin.statusConfirmed',
    pending: 'admin.statusPending',
    cancelled: 'admin.statusCancelled',
    closed: 'admin.statusClosed',
};

const editOpen = ref(false);

// Tags save immediately: they are quick labels, not a form. The PUT carries
// the card's current fields because the endpoint validates the whole card.
const tagInput = ref('');
const tagsProcessing = ref(false);

function saveTags(tags) {
    tagsProcessing.value = true;
    router.put(`/admin/clientes/${props.client.id}`, {
        clientName: props.client.name,
        clientPhone: props.client.phoneDigits ?? '',
        clientEmail: props.client.email ?? '',
        notes: props.client.notes ?? '',
        tags,
    }, {
        preserveScroll: true,
        onFinish: () => {
            tagsProcessing.value = false;
        },
    });
}

function addTag() {
    const tag = tagInput.value.trim();
    if (!tag || tagsProcessing.value) return;
    tagInput.value = '';
    if (props.client.tags.includes(tag)) return;
    saveTags([...props.client.tags, tag]);
}

function removeTag(tag) {
    if (tagsProcessing.value) return;
    saveTags(props.client.tags.filter((item) => item !== tag));
}

const deleteOpen = ref(false);
const deleteProcessing = ref(false);

function confirmDelete() {
    deleteProcessing.value = true;
    router.delete(`/admin/clientes/${props.client.id}`, {
        onFinish: () => {
            deleteProcessing.value = false;
        },
    });
}
</script>

<template>
    <AdminLayout :provider-name="providerName">
        <template #header>
            <header class="flex items-center justify-between border-b border-[var(--surface-mute)] bg-[var(--surface)] px-4 py-3">
                <div class="flex min-w-0 items-center gap-3">
                    <Link
                        href="/admin/clientes"
                        :aria-label="$t('admin.clientsTitle')"
                        class="-ml-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-full hover:bg-[var(--surface-mute)]"
                    >
                        <ArrowLeft :size="20" class="text-[var(--text-strong)]" />
                    </Link>
                    <span class="truncate text-[15px] font-semibold text-[var(--text-strong)]">{{ client.name }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        :aria-label="$t('admin.editClient')"
                        class="flex h-9 w-9 items-center justify-center rounded-full hover:bg-[var(--surface-mute)]"
                        @click="editOpen = true"
                    >
                        <Pencil :size="17" class="text-[var(--text-mute)]" />
                    </button>
                    <button
                        type="button"
                        :aria-label="$t('admin.clientDeleteTitle')"
                        class="flex h-9 w-9 items-center justify-center rounded-full hover:bg-[var(--danger-hover)]"
                        @click="deleteOpen = true"
                    >
                        <Trash2 :size="17" class="text-[var(--danger)]" />
                    </button>
                </div>
            </header>
        </template>

        <div class="flex flex-col items-center px-4 pt-6">
            <span class="flex h-16 w-16 items-center justify-center rounded-full bg-[var(--surface-mute)] text-[24px] font-bold text-[var(--text-mute)]">
                {{ client.name[0]?.toUpperCase() }}
            </span>
            <h1 class="mt-3 text-center text-[22px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                {{ client.name }}
            </h1>
            <p class="mt-0.5 text-[13px] font-normal text-[var(--text-mute)]">
                {{ client.phone ?? $t('admin.phoneOptionalHint') }}
            </p>

            <!-- Tags -->
            <div class="mt-3 flex flex-wrap items-center justify-center gap-1.5">
                <span
                    v-for="tag in client.tags"
                    :key="tag"
                    class="flex items-center gap-1 rounded-full bg-[var(--gold-soft)] py-1 pl-2.5 pr-1.5 text-[12px] font-semibold text-[var(--gold-text)]"
                >
                    {{ tag }}
                    <button
                        type="button"
                        :aria-label="`${$t('common.clear')} ${tag}`"
                        class="flex h-4 w-4 items-center justify-center rounded-full hover:bg-[var(--gold-border)]"
                        @click="removeTag(tag)"
                    >
                        <X :size="10" />
                    </button>
                </span>
                <form class="flex items-center" @submit.prevent="addTag">
                    <input
                        v-model="tagInput"
                        :placeholder="$t('admin.clientTagPlaceholder')"
                        maxlength="24"
                        :aria-label="$t('admin.clientTags')"
                        class="w-20 rounded-full border border-dashed border-[var(--border-strong)] bg-transparent px-2.5 py-1 text-center text-[12px] text-[var(--text-strong)] placeholder:text-[var(--text-faint)] focus:border-[var(--text-strong)] focus:outline-none"
                    />
                </form>
            </div>

            <!-- Contact actions -->
            <div class="mt-5 flex items-start justify-center gap-6">
                <a
                    :href="hasPhone ? `tel:+${e164}` : undefined"
                    class="flex flex-col items-center gap-1.5"
                    :class="!hasPhone && 'pointer-events-none opacity-35'"
                >
                    <span class="flex h-11 w-11 items-center justify-center rounded-full bg-[var(--chip-bg)]">
                        <Phone :size="18" class="text-[var(--chip-fg)]" />
                    </span>
                    <span class="text-[11px] font-medium text-[var(--text-mute)]">{{ $t('admin.clientCall') }}</span>
                </a>
                <a
                    :href="hasPhone ? `sms:+${e164}` : undefined"
                    class="flex flex-col items-center gap-1.5"
                    :class="!hasPhone && 'pointer-events-none opacity-35'"
                >
                    <span class="flex h-11 w-11 items-center justify-center rounded-full bg-[var(--chip-bg)]">
                        <MessageSquare :size="18" class="text-[var(--chip-fg)]" />
                    </span>
                    <span class="text-[11px] font-medium text-[var(--text-mute)]">{{ $t('admin.clientSms') }}</span>
                </a>
                <a
                    :href="hasPhone ? waHref : undefined"
                    target="_blank"
                    rel="noopener"
                    class="flex flex-col items-center gap-1.5"
                    :class="!hasPhone && 'pointer-events-none opacity-35'"
                >
                    <span class="flex h-11 w-11 items-center justify-center rounded-full bg-[var(--chip-bg)]">
                        <MessageCircle :size="18" class="text-[var(--chip-fg)]" />
                    </span>
                    <span class="text-[11px] font-medium text-[var(--text-mute)]">{{ $t('admin.clientWhatsapp') }}</span>
                </a>
            </div>

            <!-- Stats -->
            <div class="mt-6 grid w-full grid-cols-3 divide-x divide-[var(--surface-mute)] rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] py-3">
                <div class="flex flex-col items-center">
                    <span class="text-[18px] font-bold text-[var(--text-strong)]">{{ stats.upcoming }}</span>
                    <span class="text-[11px] font-medium text-[var(--text-mute)]">{{ $t('admin.clientStatUpcoming') }}</span>
                </div>
                <div class="flex flex-col items-center">
                    <span class="text-[18px] font-bold text-[var(--text-strong)]">{{ stats.completed }}</span>
                    <span class="text-[11px] font-medium text-[var(--text-mute)]">{{ $t('admin.clientStatDone') }}</span>
                </div>
                <div class="flex flex-col items-center">
                    <span class="text-[18px] font-bold text-[var(--text-strong)]">{{ stats.cancelled }}</span>
                    <span class="text-[11px] font-medium text-[var(--text-mute)]">{{ $t('admin.tabCancelled') }}</span>
                </div>
            </div>

            <Link
                :href="newAppointmentHref"
                class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-[var(--btn-bg)] py-3.5 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)]"
            >
                <CalendarPlus :size="18" />
                {{ $t('admin.clientNewAppointment') }}
            </Link>
        </div>

        <div class="flex flex-col gap-5 p-4 pt-6">
            <!-- Notes -->
            <div v-if="client.notes" class="rounded-2xl border border-[var(--surface-mute)] bg-[var(--surface)] p-4">
                <div class="text-[13px] font-semibold text-[var(--text-mute)]">{{ $t('admin.clientNotes') }}</div>
                <p class="mt-1.5 whitespace-pre-line text-[14px] font-normal leading-relaxed text-[var(--text-strong)]">{{ client.notes }}</p>
            </div>

            <!-- Upcoming -->
            <div>
                <div class="pb-2 text-[13px] font-semibold text-[var(--text-mute)]">{{ $t('admin.clientUpcomingTitle') }}</div>
                <p v-if="upcomingRows.length === 0" class="text-[13px] font-normal text-[var(--text-faint)]">
                    {{ $t('admin.clientNoUpcoming') }}
                </p>
                <div
                    v-for="appt in upcomingRows"
                    :key="appt.id"
                    class="flex items-center justify-between border-b border-[var(--surface-mute)] py-3 last:border-b-0"
                >
                    <div>
                        <div class="text-[14px] font-semibold capitalize text-[var(--text-strong)]">{{ appt.label }}</div>
                        <div class="mt-0.5 text-[13px] font-normal text-[var(--text-mute)]">
                            {{ appt.service }} · {{ appt.duration }} · ${{ appt.price }}
                        </div>
                    </div>
                    <Badge :variant="badgeVariant[appt.status]">{{ $t(statusKey[appt.status]) }}</Badge>
                </div>
            </div>

            <!-- Past -->
            <div>
                <div class="pb-2 text-[13px] font-semibold text-[var(--text-mute)]">{{ $t('admin.clientPastTitle') }}</div>
                <p v-if="pastRows.length === 0" class="text-[13px] font-normal text-[var(--text-faint)]">
                    {{ $t('admin.clientNoPast') }}
                </p>
                <div
                    v-for="appt in pastRows"
                    :key="appt.id"
                    class="flex items-center justify-between border-b border-[var(--surface-mute)] py-3 last:border-b-0"
                >
                    <div>
                        <div class="text-[14px] font-medium capitalize text-[var(--text-mute)]">{{ appt.label }}</div>
                        <div class="mt-0.5 text-[13px] font-normal text-[var(--text-faint)]">
                            {{ appt.service }} · {{ appt.duration }} · ${{ appt.price }}
                        </div>
                    </div>
                    <Badge :variant="badgeVariant[appt.status]">{{ $t(statusKey[appt.status]) }}</Badge>
                </div>
            </div>
        </div>

        <ClientFormSheet v-model="editOpen" :client="client" />

        <ConfirmDialog
            v-model="deleteOpen"
            :title="$t('admin.clientDeleteTitle')"
            :body="$t('admin.clientDeleteBody')"
            :confirm-label="$t('admin.clientDeleteTitle')"
            :processing="deleteProcessing"
            variant="danger"
            @confirm="confirmDelete"
        />
    </AdminLayout>
</template>
