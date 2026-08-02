<script setup>
import { ref, computed, watch } from 'vue';
import { X, Check, Ban } from '@lucide/vue';
import BottomSheet from '../ui/BottomSheet.vue';
import Input from '../ui/Input.vue';
import Select from '../ui/Select.vue';
import { useFormat } from '../../composables/useFormat';

const props = defineProps({
    mode: { type: String, default: 'create' }, // create | edit
    service: { type: Object, default: () => ({ name: '', durationMinutes: 45, price: 35, category: 'fade' }) },
});

const open = defineModel({ type: Boolean, default: false });
const emit = defineEmits(['save', 'delete']);

const { formatDuration } = useFormat();

const form = ref({ ...props.service });

watch(open, (isOpen) => {
    if (isOpen) form.value = { ...props.service };
});

const durationOptions = computed(() =>
    Array.from({ length: 36 }, (_, i) => (i + 1) * 5).map((minutes) => ({
        value: minutes,
        label: formatDuration(minutes),
    })),
);

const categoryOptions = [
    { value: 'fade', label: 'Fade' },
    { value: 'classic', label: 'Clásico' },
    { value: 'beard', label: 'Barba' },
    { value: 'kids', label: 'Niños' },
    { value: 'color', label: 'Color' },
];
</script>

<template>
    <BottomSheet v-model="open">
        <div class="mb-5 flex items-center justify-between">
            <div>
                <div class="text-lg font-extrabold text-[var(--text-strong)]">
                    {{ mode === 'create' ? $t('admin.newService') : $t('admin.editService') }}
                </div>
                <div v-if="mode === 'edit'" class="mt-0.5 text-[11px] font-bold text-[var(--text-faint)]">{{ service.name }}</div>
            </div>
            <button
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--surface-mute)]"
                @click="open = false"
            >
                <X :size="16" class="text-[var(--text-mute)]" />
            </button>
        </div>

        <div class="mb-4">
            <Input v-model="form.name" :label="$t('admin.serviceName')" :placeholder="$t('admin.serviceNamePlaceholder')" />
        </div>

        <div class="mb-4 grid grid-cols-2 gap-3">
            <div>
                <Select v-model="form.durationMinutes" :label="$t('admin.duration')" :options="durationOptions" />
                <div class="mt-1.5 text-[10px] font-semibold text-[var(--text-faint)]">{{ $t('admin.durationHint') }}</div>
            </div>
            <label class="flex flex-col gap-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-[var(--text-faint)]">{{ $t('admin.price') }}</span>
                <div class="flex items-center gap-1 rounded-xl border-[1.5px] border-[var(--border-strong)] bg-[var(--surface-alt)] px-4 py-3.5">
                    <span class="text-sm font-bold text-[var(--text-faint)]">$</span>
                    <input
                        v-model.number="form.price"
                        type="number"
                        min="0"
                        step="1"
                        class="w-full bg-transparent text-sm font-bold text-[var(--text-strong)] focus:outline-none"
                    />
                </div>
            </label>
        </div>

        <div class="mb-6">
            <Select v-model="form.category" :label="$t('admin.category')" :options="categoryOptions" />
        </div>

        <div class="flex gap-3" :class="mode === 'edit' && 'mb-3'">
            <button
                type="button"
                class="w-2/5 rounded-xl bg-[var(--surface-mute)] py-3.5 text-sm font-bold text-[var(--text-body)] hover:bg-[var(--border-strong)]"
                @click="open = false"
            >
                {{ $t('common.cancel') }}
            </button>
            <button
                type="button"
                class="flex w-3/5 items-center justify-center gap-1.5 rounded-xl bg-[var(--btn-green)] py-3.5 text-sm font-bold text-white hover:bg-[var(--btn-green-hover)]"
                @click="emit('save', { ...form })"
            >
                <Check :size="15" />
                {{ mode === 'create' ? $t('admin.saveService') : $t('admin.saveChanges') }}
            </button>
        </div>

        <button
            v-if="mode === 'edit'"
            type="button"
            class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-[var(--danger-border)] py-3.5 text-[13px] font-bold text-[var(--danger)] hover:bg-[var(--danger-hover)]"
            @click="emit('delete')"
        >
            <Ban :size="15" />
            {{ $t('admin.deleteService') }}
        </button>
    </BottomSheet>
</template>
