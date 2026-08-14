<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import BottomSheet from '../ui/BottomSheet.vue';
import OutlinedInput from '../ui/OutlinedInput.vue';

/**
 * Add or edit a client card. Same sheet for both: `client` null means adding,
 * an object means editing that card. The phone is optional for the same
 * reason it is on a manual appointment — forcing it invites invented digits
 * the WhatsApp assistant would then attribute to a real person.
 */
const props = defineProps({
    // null → create; { id, name, phoneDigits, email, notes, tags } → edit.
    client: { type: Object, default: null },
});

const open = defineModel({ type: Boolean, default: false });

const isEdit = computed(() => props.client !== null);

const form = useForm({
    clientName: '',
    clientPhone: '',
    clientEmail: '',
    notes: '',
    tags: [],
});

watch(open, (isOpen) => {
    if (!isOpen) return;
    form.reset();
    form.clearErrors();
    if (props.client) {
        form.clientName = props.client.name;
        form.clientPhone = props.client.phoneDigits ?? '';
        form.clientEmail = props.client.email ?? '';
        form.notes = props.client.notes ?? '';
        form.tags = [...(props.client.tags ?? [])];
    }
});

// Same live US mask as the sign-up and appointment forms; the guard against
// reassigning an identical value keeps the watcher from looping.
watch(() => form.clientPhone, (value) => {
    const digits = (value || '').replace(/\D/g, '').replace(/^1(?=\d{10})/, '').slice(0, 10);
    let out = digits;
    if (digits.length > 6) out = `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6)}`;
    else if (digits.length > 3) out = `(${digits.slice(0, 3)}) ${digits.slice(3)}`;
    if (out !== value) form.clientPhone = out;
});

const incomplete = computed(() => !form.clientName.trim());

function submit() {
    if (incomplete.value || form.processing) return;

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (isEdit.value) {
        form.put(`/admin/clientes/${props.client.id}`, options);
    } else {
        form.post('/admin/clientes', options);
    }
}
</script>

<template>
    <BottomSheet v-model="open">
        <div class="mb-6 flex items-center justify-between">
            <div class="text-[20px] font-bold leading-tight tracking-tight text-[var(--text-strong)]">
                {{ isEdit ? $t('admin.editClient') : $t('admin.addClient') }}
            </div>
            <button
                type="button"
                :aria-label="$t('common.close')"
                class="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--surface-mute)] hover:bg-[var(--border-strong)]"
                @click="open = false"
            >
                <X :size="16" class="text-[var(--text-mute)]" />
            </button>
        </div>

        <OutlinedInput
            id="client-name"
            v-model="form.clientName"
            class="mb-5"
            :label="$t('admin.appointmentClientName')"
            :error="form.errors.clientName"
            clearable
        />

        <div class="mb-5">
            <OutlinedInput
                id="client-phone"
                v-model="form.clientPhone"
                type="tel"
                inputmode="tel"
                :label="$t('admin.appointmentPhone')"
                :error="form.errors.clientPhone"
            />
            <p class="mt-1.5 text-[12px] font-normal text-[var(--text-faint)]">{{ $t('admin.phoneOptionalHint') }}</p>
        </div>

        <OutlinedInput
            id="client-email"
            v-model="form.clientEmail"
            class="mb-5"
            type="email"
            inputmode="email"
            :label="$t('admin.clientEmail')"
            :error="form.errors.clientEmail"
        />

        <div class="mb-2">
            <label for="client-notes" class="mb-2 block text-[13px] font-medium text-[var(--text-mute)]">
                {{ $t('admin.clientNotes') }}
            </label>
            <textarea
                id="client-notes"
                v-model="form.notes"
                rows="3"
                :placeholder="$t('admin.clientNotesPlaceholder')"
                class="w-full resize-none rounded-xl border border-[var(--border-strong)] bg-[var(--surface)] px-3.5 py-3 text-[15px] text-[var(--text-strong)] placeholder:text-[var(--text-faint)] focus:border-[var(--text-strong)] focus:outline-none"
            />
            <p v-if="form.errors.notes" class="mt-1.5 text-[13px] font-normal text-[var(--danger)]">{{ form.errors.notes }}</p>
        </div>

        <div class="mt-6">
            <button
                type="button"
                :disabled="incomplete || form.processing"
                class="w-full rounded-xl bg-[var(--btn-bg)] py-3.5 text-[15px] font-semibold text-white hover:bg-[var(--btn-hover)] disabled:cursor-not-allowed disabled:opacity-60"
                @click="submit"
            >
                {{ form.processing ? $t('common.saving') : isEdit ? $t('admin.editClient') : $t('admin.addClient') }}
            </button>
        </div>
    </BottomSheet>
</template>
